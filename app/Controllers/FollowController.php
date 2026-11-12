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
use App\Social\FollowStore;
use App\Social\PublicProfile;
use App\Social\PublicProfileReader;

final class FollowController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly UsernamePolicy $usernamePolicy,
        private readonly PublicProfileReader $profiles,
        private readonly FollowStore $follows,
    ) {
    }

    public function follow(Request $request, string $username): Response
    {
        return $this->mutate($request, $username, true);
    }

    public function unfollow(Request $request, string $username): Response
    {
        return $this->mutate($request, $username, false);
    }

    private function mutate(Request $request, string $username, bool $follow): Response
    {
        $viewerUserId = $this->auth->id();
        if ($viewerUserId === null) {
            return Response::redirect('/login', 303);
        }
        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('A sessão de formulário expirou.', 403);
        }
        $profile = $this->resolve($username);
        if ($profile === null) {
            return Response::html($this->view->render('pages/404', [
                'title'=>'Perfil não encontrado','path'=>'/u/'.$username,'currentRoute'=>null,'auth'=>$this->auth,'csrf'=>$this->csrf,
            ]), 404);
        }
        if ($viewerUserId === $profile->userId) {
            return Response::html('Você não pode seguir o próprio perfil.', 422);
        }
        if ($follow) {
            $this->follows->follow($viewerUserId, $profile->userId);
            $this->session->flash('success', 'Agora você segue @' . $profile->username . '.');
        } else {
            $this->follows->unfollow($viewerUserId, $profile->userId);
            $this->session->flash('success', 'Você deixou de seguir @' . $profile->username . '.');
        }
        return Response::redirect('/u/' . $profile->username, 303);
    }

    private function resolve(string $username): ?PublicProfile
    {
        $normalized = $this->usernamePolicy->normalize($username);
        if ($normalized === null || $normalized !== $username || $this->usernamePolicy->error($normalized) !== null) {
            return null;
        }
        return $this->profiles->findByUsername($normalized);
    }
}
