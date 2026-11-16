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
use App\Social\FollowRequestStore;
use App\Social\PublicProfile;
use App\Social\PublicProfileReader;

final class FollowRequestController
{
    private const PER_PAGE = 30;

    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly UsernamePolicy $usernamePolicy,
        private readonly PublicProfileReader $profiles,
        private readonly FollowRequestStore $requests,
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }
        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>10000]]);
        return Response::html($this->view->render('pages/follow-requests', [
            'title'=>'Solicitações de seguidores · Flickary', 'currentRoute'=>'profile',
            'auth'=>$this->auth, 'csrf'=>$this->csrf,
            'requests'=>$this->requests->paginateIncoming($userId, $page === false ? 1 : (int) $page, self::PER_PAGE),
            'messages'=>$this->session->consumeFlash(),
        ]));
    }

    public function accept(Request $request, string $username): Response
    {
        return $this->mutate($request, $username, true);
    }

    public function reject(Request $request, string $username): Response
    {
        return $this->mutate($request, $username, false);
    }

    private function mutate(Request $request, string $username, bool $accept): Response
    {
        $targetUserId = $this->auth->id();
        if ($targetUserId === null) {
            return Response::redirect('/login', 303);
        }
        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('A sessão de formulário expirou.', 403);
        }
        $requester = $this->resolve($username);
        if ($requester === null || $requester->userId === $targetUserId) {
            return $this->notFound();
        }
        $changed = $accept
            ? $this->requests->accept($requester->userId, $targetUserId)
            : $this->requests->reject($requester->userId, $targetUserId);
        if (!$changed) {
            return $this->notFound();
        }
        $this->session->flash('success', $accept
            ? 'Solicitação de @' . $requester->username . ' aceita.'
            : 'Solicitação de @' . $requester->username . ' recusada.');
        return Response::redirect('/perfil/solicitacoes', 303);
    }

    private function resolve(string $username): ?PublicProfile
    {
        $normalized = $this->usernamePolicy->normalize($username);
        if ($normalized === null || $normalized !== $username || $this->usernamePolicy->error($normalized) !== null) {
            return null;
        }
        return $this->profiles->findByUsername($normalized);
    }

    private function notFound(): Response
    {
        return Response::html($this->view->render('pages/404', [
            'title'=>'Solicitação não encontrada','path'=>'/perfil/solicitacoes',
            'currentRoute'=>'profile','auth'=>$this->auth,'csrf'=>$this->csrf,
        ]), 404);
    }
}
