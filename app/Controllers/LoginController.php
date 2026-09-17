<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

final class LoginController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Session $session,
        private readonly array $googleConfig,
        private readonly array $facebookConfig,
    ) {
    }

    public function show(): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('/');
        }

        $clientId = trim((string) ($this->googleConfig['client_id'] ?? ''));
        $facebookEnabled = trim((string) ($this->facebookConfig['app_id'] ?? '')) !== ''
            && trim((string) ($this->facebookConfig['app_secret'] ?? '')) !== '';

        return Response::html($this->view->render('pages/login', [
            'title' => 'Entrar · Flickary',
            'googleEnabled' => $clientId !== '',
            'googleClientId' => $clientId,
            'googleLoginUri' => (string) ($this->googleConfig['login_uri'] ?? ''),
            'facebookEnabled' => $facebookEnabled,
            'messages' => $this->session->consumeFlash(),
        ], 'layouts/auth'));
    }
}
