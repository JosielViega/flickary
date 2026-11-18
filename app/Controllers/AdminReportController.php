<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Administration\AdminAccess;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Moderation\AdminReportReader;
use App\Moderation\ReportStatus;
use App\Moderation\ReportText;
use App\Moderation\ModerationActionReader;
use App\Moderation\ModerationActionResult;
use App\Moderation\ModerationActionStore;
use App\Moderation\ModerationNote;
use App\Moderation\SuspensionDuration;

final class AdminReportController
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AdminAccess $admins,
        private readonly AdminReportReader $reports,
        private readonly ModerationActionReader $actions,
        private readonly ModerationActionStore $moderation,
    ) {
    }

    public function index(Request $request): Response
    {
        if (($guard = $this->guard()) instanceof Response) { return $guard; }
        $filter = $request->query('status', 'pending');
        $status = is_string($filter) ? ReportStatus::tryFrom($filter) : null;
        if ($filter !== 'all' && !$status instanceof ReportStatus) { $status = ReportStatus::Pending; $filter = 'pending'; }
        return Response::html($this->view->render('pages/admin-reports', [
            'title' => 'Moderação · Flickary', 'currentRoute' => 'profile',
            'auth' => $this->auth, 'csrf' => $this->csrf,
            'reports' => $this->reports->paginateAdmin($filter === 'all' ? null : $status, $this->page($request->query('page', 1)), self::PER_PAGE),
            'filter' => $filter, 'messages' => $this->session->consumeFlash(),
        ]));
    }

    public function show(string $id): Response
    {
        if (($guard = $this->guard()) instanceof Response) { return $guard; }
        $report = $this->reports->findAdmin($this->positiveId($id));
        if ($report === null) { return $this->notFound('/admin/denuncias/' . $id); }
        return Response::html($this->view->render('pages/admin-report', [
            'title' => 'Denúncia #' . $report->id . ' · Moderação · Flickary', 'currentRoute' => 'profile',
            'auth' => $this->auth, 'csrf' => $this->csrf, 'report' => $report,
            'note' => '', 'errors' => [], 'messages' => $this->session->consumeFlash(),
            'targetUser' => $report->reportedUserId === null ? null : $this->actions->findUserById($report->reportedUserId),
            'commentLive' => $this->actions->liveCommentMatchesReport($report),
            'durations' => SuspensionDuration::cases(), 'sanctionNote' => '', 'sanctionErrors' => [],
        ]));
    }

    public function resolve(Request $request, string $id): Response
    {
        return $this->review($request, $id, ReportStatus::Resolved);
    }

    public function dismiss(Request $request, string $id): Response
    {
        return $this->review($request, $id, ReportStatus::Dismissed);
    }

    public function suspend(Request $request, string $id): Response
    {
        if (($guard = $this->guard()) instanceof Response) { return $guard; }
        if (!$this->csrf->verify($request->input('_token'))) { return Response::html('A sessão de formulário expirou.', 403); }
        $durationInput = $request->input('duration');
        $duration = is_string($durationInput) ? SuspensionDuration::tryFrom($durationInput) : null;
        [$note, $noteError] = ModerationNote::validate($request->input('moderator_note'));
        $errors = [];
        if ($duration === null) { $errors['duration'] = 'Escolha uma duração permitida.'; }
        if ($noteError !== null) { $errors['moderator_note'] = $noteError; }
        if ($errors !== [] || $note === null || $duration === null) {
            return $this->sanctionError($request, $id, $errors);
        }
        $result = $this->moderation->suspendFromReport($this->positiveId($id), (int) $this->auth->id(), $duration, $note);
        if ($result === ModerationActionResult::ProtectedTarget) {
            return Response::html('Administradores não podem suspender a própria conta nem outro administrador.', 422);
        }
        if ($result !== ModerationActionResult::Applied) { return $this->notFound('/admin/denuncias/' . $id); }
        $this->session->flash('success', 'Conta suspensa temporariamente e denúncia resolvida.');
        return Response::redirect('/admin/denuncias/' . rawurlencode($id), 303);
    }

    public function removeComment(Request $request, string $id): Response
    {
        if (($guard = $this->guard()) instanceof Response) { return $guard; }
        if (!$this->csrf->verify($request->input('_token'))) { return Response::html('A sessão de formulário expirou.', 403); }
        [$note, $error] = ModerationNote::validate($request->input('moderator_note'));
        if ($error !== null || $note === null) {
            return $this->sanctionError($request, $id, ['moderator_note' => $error ?? 'Nota inválida.']);
        }
        $result = $this->moderation->removeReportedComment($this->positiveId($id), (int) $this->auth->id(), $note);
        if ($result !== ModerationActionResult::Applied) { return $this->notFound('/admin/denuncias/' . $id); }
        $this->session->flash('success', 'Comentário removido e denúncia resolvida.');
        return Response::redirect('/admin/denuncias/' . rawurlencode($id), 303);
    }

    private function review(Request $request, string $id, ReportStatus $status): Response
    {
        if (($guard = $this->guard()) instanceof Response) { return $guard; }
        if (!$this->csrf->verify($request->input('_token'))) { return Response::html('A sessão de formulário expirou.', 403); }
        $reportId = $this->positiveId($id);
        $report = $this->reports->findAdmin($reportId);
        if ($report === null || $report->status !== ReportStatus::Pending) { return $this->notFound('/admin/denuncias/' . $id); }
        [$note, $error] = ReportText::optional($request->input('moderator_note'), 'A nota interna');
        if ($error !== null) {
            return Response::html($this->view->render('pages/admin-report', [
                'title' => 'Denúncia #' . $report->id . ' · Moderação · Flickary', 'currentRoute' => 'profile',
                'auth' => $this->auth, 'csrf' => $this->csrf, 'report' => $report,
                'note' => is_string($request->input('moderator_note')) ? $request->input('moderator_note') : '',
                'errors' => ['moderator_note' => $error], 'messages' => [],
                'targetUser' => $report->reportedUserId === null ? null : $this->actions->findUserById($report->reportedUserId),
                'commentLive' => $this->actions->liveCommentMatchesReport($report),
                'durations' => SuspensionDuration::cases(), 'sanctionNote' => '', 'sanctionErrors' => [],
            ]), 422);
        }
        if (!$this->reports->review($reportId, (int) $this->auth->id(), $status, $note)) {
            return $this->notFound('/admin/denuncias/' . $id);
        }
        $this->session->flash('success', $status === ReportStatus::Resolved ? 'Denúncia marcada como resolvida.' : 'Denúncia arquivada.');
        return Response::redirect('/admin/denuncias/' . $reportId, 303);
    }

    /** @param array<string,string> $errors */
    private function sanctionError(Request $request, string $id, array $errors): Response
    {
        $report = $this->reports->findAdmin($this->positiveId($id));
        if ($report === null || $report->status !== ReportStatus::Pending) { return $this->notFound('/admin/denuncias/' . $id); }
        return Response::html($this->view->render('pages/admin-report', [
            'title' => 'Denúncia #' . $report->id . ' · Moderação · Flickary', 'currentRoute' => 'profile',
            'auth' => $this->auth, 'csrf' => $this->csrf, 'report' => $report,
            'note' => '', 'errors' => [], 'messages' => [],
            'targetUser' => $report->reportedUserId === null ? null : $this->actions->findUserById($report->reportedUserId),
            'commentLive' => $this->actions->liveCommentMatchesReport($report),
            'durations' => SuspensionDuration::cases(),
            'sanctionNote' => is_string($request->input('moderator_note')) ? $request->input('moderator_note') : '',
            'selectedDuration' => is_string($request->input('duration')) ? $request->input('duration') : '',
            'sanctionErrors' => $errors,
        ]), 422);
    }

    private function guard(): ?Response
    {
        $userId = $this->auth->id();
        if ($userId === null) { return Response::redirect('/login', 303); }
        return $this->admins->isAdmin($userId) ? null : $this->notFound('/admin/denuncias');
    }

    private function positiveId(string $value): int
    {
        return preg_match('/^[1-9][0-9]*$/D', $value) === 1 ? (int) $value : 0;
    }

    private function page(mixed $value): int
    {
        $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        return $page === false ? 1 : (int) $page;
    }

    private function notFound(string $path): Response
    {
        return Response::html($this->view->render('pages/404', [
            'title' => 'Página não encontrada', 'path' => $path,
            'currentRoute' => null, 'auth' => $this->auth, 'csrf' => $this->csrf,
        ]), 404);
    }
}
