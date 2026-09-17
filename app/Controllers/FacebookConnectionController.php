<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\ConnectedProviderReader;
use App\Authentication\FacebookIdentityProvider;
use App\Authentication\FacebookOAuthState;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class FacebookConnectionController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly FacebookIdentityProvider $facebook,
        private readonly FacebookOAuthState $state,
        private readonly ConnectedProviderReader $providers,
    ) {
    }

    public function store(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }
        if (!$this->csrf->verify($request->input('_token'))) {
            $this->session->flash('error', 'Sua sessão de formulário expirou. Tente novamente.');
            return Response::redirect('/perfil', 303);
        }
        if (!$this->facebook->configured()) {
            $this->session->flash('error', 'A conexão com Facebook não está configurada neste ambiente.');
            return Response::redirect('/perfil', 303);
        }
        if (in_array('facebook', $this->providers->providersForUser($userId), true)) {
            $this->session->flash('success', 'Uma conta Facebook já está conectada ao seu perfil.');
            return Response::redirect('/perfil', 303);
        }

        return Response::redirect($this->facebook->authorizationUrl($this->state->issue('link', $userId)), 303);
    }
}
