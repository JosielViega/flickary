<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Administration\AdminAccess;
use App\Authentication\UsernamePolicy;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Moderation\ModerationActionReader;
use App\Moderation\ModerationActionResult;
use App\Moderation\ModerationActionStore;
use App\Moderation\ModerationNote;

final class AdminModerationController
{
    public function __construct(
        private readonly View $view, private readonly Auth $auth, private readonly Csrf $csrf,
        private readonly Session $session, private readonly AdminAccess $admins,
        private readonly UsernamePolicy $usernames, private readonly ModerationActionReader $actions,
        private readonly ModerationActionStore $service,
    ) {}

    public function actions(Request $request): Response
    {
        if (($guard = $this->guard()) instanceof Response) { return $guard; }
        return Response::html($this->view->render('pages/admin-actions', [
            'title' => 'Ações de moderação · Flickary', 'currentRoute' => 'profile',
            'auth' => $this->auth, 'csrf' => $this->csrf,
            'actions' => $this->actions->paginateAll($this->page($request->query('page', 1)), 50),
        ]));
    }

    public function user(Request $request, string $username): Response
    {
        if (($guard = $this->guard()) instanceof Response) { return $guard; }
        $normalized = $this->usernames->normalize($username);
        $user = $normalized === $username && $this->usernames->error($normalized) === null
            ? $this->actions->findUserByUsername($username) : null;
        if ($user === null) { return $this->notFound('/admin/usuarios/' . $username); }
        return Response::html($this->view->render('pages/admin-user', [
            'title' => '@' . $user->username . ' · Administração · Flickary', 'currentRoute' => 'profile',
            'auth' => $this->auth, 'csrf' => $this->csrf, 'user' => $user,
            'actions' => $this->actions->paginateForUser($user->id, $this->page($request->query('page', 1)), 30),
            'note' => '', 'errors' => [], 'messages' => $this->session->consumeFlash(),
        ]));
    }

    public function reactivate(Request $request, string $username): Response
    {
        if (($guard = $this->guard()) instanceof Response) { return $guard; }
        if (!$this->csrf->verify($request->input('_token'))) { return Response::html('A sessão de formulário expirou.', 403); }
        [$note, $error] = ModerationNote::validate($request->input('moderator_note'));
        if ($error !== null || $note === null) { return $this->userError($request, $username, $error ?? 'Nota inválida.'); }
        $result = $this->service->unsuspend($username, (int) $this->auth->id(), $note);
        if ($result === ModerationActionResult::ProtectedTarget) { return Response::html('Administradores não podem aplicar esta ação à própria conta.', 422); }
        if ($result !== ModerationActionResult::Applied) { return $this->notFound('/admin/usuarios/' . $username); }
        $this->session->flash('success', 'Suspensão encerrada antecipadamente.');
        return Response::redirect('/admin/usuarios/' . rawurlencode($username), 303);
    }

    private function userError(Request $request, string $username, string $error): Response
    {
        $user = $this->actions->findUserByUsername($username);
        if ($user === null) { return $this->notFound('/admin/usuarios/' . $username); }
        return Response::html($this->view->render('pages/admin-user', [
            'title' => '@' . $user->username . ' · Administração · Flickary', 'currentRoute' => 'profile',
            'auth' => $this->auth, 'csrf' => $this->csrf, 'user' => $user,
            'actions' => $this->actions->paginateForUser($user->id, $this->page($request->query('page', 1)), 30),
            'note' => is_string($request->input('moderator_note')) ? $request->input('moderator_note') : '',
            'errors' => ['moderator_note' => $error], 'messages' => [],
        ]), 422);
    }

    private function guard(): ?Response
    {
        $id = $this->auth->id();
        if ($id === null) { return Response::redirect('/login', 303); }
        return $this->admins->isAdmin($id) ? null : $this->notFound('/admin');
    }

    private function page(mixed $value): int
    {
        $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        return $page === false ? 1 : (int) $page;
    }

    private function notFound(string $path): Response
    {
        return Response::html($this->view->render('pages/404', [
            'title' => 'Página não encontrada', 'path' => $path, 'currentRoute' => null,
            'auth' => $this->auth, 'csrf' => $this->csrf,
        ]), 404);
    }
}
