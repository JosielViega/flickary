<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\UsernamePolicy;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Moderation\ReportCreateResult;
use App\Moderation\ReportReader;
use App\Moderation\ReportReason;
use App\Moderation\ReportStore;
use App\Moderation\ReportSubmission;
use App\Moderation\ReportTarget;
use App\Moderation\ReportTargetType;
use App\Moderation\ReportText;
use App\Social\PublicProfile;
use App\Social\PublicProfileReader;

final class ReportController
{
    private const OWN_PER_PAGE = 30;

    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly UsernamePolicy $usernames,
        private readonly PublicProfileReader $profiles,
        private readonly ReportStore $store,
        private readonly ReportReader $reader,
    ) {
    }

    public function profileForm(string $username): Response
    {
        return $this->form($this->profileTarget($username), '/u/' . rawurlencode($username) . '/reportar');
    }

    public function profileSubmit(Request $request, string $username): Response
    {
        return $this->submit($request, $this->profileTarget($username), '/u/' . rawurlencode($username) . '/reportar');
    }

    public function commentForm(string $id): Response
    {
        return $this->form($this->storedTarget($id, true), '/social/comentarios/' . rawurlencode($id) . '/reportar');
    }

    public function commentSubmit(Request $request, string $id): Response
    {
        return $this->submit($request, $this->storedTarget($id, true), '/social/comentarios/' . rawurlencode($id) . '/reportar');
    }

    public function messageForm(string $id): Response
    {
        return $this->form($this->storedTarget($id, false), '/mensagens/' . rawurlencode($id) . '/reportar');
    }

    public function messageSubmit(Request $request, string $id): Response
    {
        return $this->submit($request, $this->storedTarget($id, false), '/mensagens/' . rawurlencode($id) . '/reportar');
    }

    public function own(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) { return Response::redirect('/login', 303); }
        return Response::html($this->view->render('pages/reports', [
            'title' => 'Minhas denúncias · Flickary',
            'currentRoute' => 'profile',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'reports' => $this->reader->paginateOwn($userId, $this->page($request->query('page', 1)), self::OWN_PER_PAGE),
            'messages' => $this->session->consumeFlash(),
        ]));
    }

    private function form(ReportTarget|Response $resolved, string $action): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) { return Response::redirect('/login', 303); }
        if ($resolved instanceof Response) { return $resolved; }
        if ($resolved->reportedUserId === $userId) {
            return Response::html('Você não pode denunciar conteúdo próprio.', 422);
        }

        return $this->formPage($resolved, $action);
    }

    private function submit(Request $request, ReportTarget|Response $resolved, string $action): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) { return Response::redirect('/login', 303); }
        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('A sessão de formulário expirou.', 403);
        }
        if ($resolved instanceof Response) { return $resolved; }
        if ($resolved->reportedUserId === $userId) {
            return Response::html('Você não pode denunciar conteúdo próprio.', 422);
        }
        $reasonInput = $request->input('reason');
        $reason = is_string($reasonInput) ? ReportReason::tryFrom($reasonInput) : null;
        [$details, $detailsError] = ReportText::optional($request->input('details'), 'Os detalhes');
        $errors = [];
        if (!$reason instanceof ReportReason) { $errors['reason'] = 'Escolha um motivo válido.'; }
        if ($detailsError !== null) { $errors['details'] = $detailsError; }
        if ($reason === ReportReason::Other && ($details === null || mb_strlen($details) < 10)) {
            $errors['details'] = 'Descreva o motivo em pelo menos 10 caracteres quando escolher Outro.';
        }
        if ($errors !== []) {
            return $this->formPage($resolved, $action, $reasonInput, is_string($request->input('details')) ? $request->input('details') : '', $errors, 422);
        }

        $result = $this->store->create(new ReportSubmission($userId, $resolved, $reason, $details));
        if ($result === ReportCreateResult::RateLimited) {
            return $this->formPage($resolved, $action, $reason->value, $details ?? '', [
                'form' => 'Você atingiu o limite de 20 denúncias em 24 horas. Tente novamente mais tarde.',
            ], 429);
        }
        $this->session->flash(
            $result === ReportCreateResult::Duplicate ? 'info' : 'success',
            $result === ReportCreateResult::Duplicate
                ? 'Você já enviou uma denúncia sobre este conteúdo.'
                : 'Denúncia enviada para análise.',
        );

        return Response::redirect('/perfil/denuncias', 303);
    }

    private function formPage(ReportTarget $target, string $action, mixed $reason = null, string $details = '', array $errors = [], int $status = 200): Response
    {
        return Response::html($this->view->render('pages/report-form', [
            'title' => 'Enviar denúncia · Flickary',
            'currentRoute' => 'profile',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'target' => $target,
            'action' => $action,
            'reasons' => ReportReason::cases(),
            'selectedReason' => is_string($reason) ? $reason : '',
            'details' => $details,
            'errors' => $errors,
        ]), $status);
    }

    private function profileTarget(string $username): ReportTarget|Response
    {
        $normalized = $this->usernames->normalize($username);
        $profile = $normalized !== null && $normalized === $username && $this->usernames->error($normalized) === null
            ? $this->profiles->findByUsername($normalized) : null;
        if (!$profile instanceof PublicProfile) { return $this->notFound('/u/' . $username . '/reportar'); }

        return new ReportTarget(
            ReportTargetType::Profile,
            $profile->userId,
            $profile->userId,
            $profile->username,
            $profile->displayName,
            $profile->avatarUrl,
            $profile->bio,
        );
    }

    private function storedTarget(string $id, bool $comment): ReportTarget|Response
    {
        $userId = $this->auth->id();
        if ($userId === null) { return Response::redirect('/login', 303); }
        $targetId = $this->positiveId($id);
        if ($targetId === null) { return $this->notFound($comment ? '/social/comentarios/' . $id : '/mensagens/' . $id); }
        $target = $comment
            ? $this->store->reportableComment($userId, $targetId)
            : $this->store->reportableMessage($userId, $targetId);

        return $target ?? $this->notFound($comment ? '/social/comentarios/' . $id : '/mensagens/' . $id);
    }

    private function positiveId(string $value): ?int
    {
        return preg_match('/^[1-9][0-9]*$/D', $value) === 1 && (int) $value > 0 ? (int) $value : null;
    }

    private function page(mixed $value): int
    {
        $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        return $page === false ? 1 : (int) $page;
    }

    private function notFound(string $path): Response
    {
        return Response::html($this->view->render('pages/404', [
            'title' => 'Conteúdo não encontrado', 'path' => $path,
            'currentRoute' => null, 'auth' => $this->auth, 'csrf' => $this->csrf,
        ]), 404);
    }
}
