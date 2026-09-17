<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;

final class LogoutController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Csrf $csrf,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('Solicitação inválida.', 403);
        }

        $this->auth->logout();

        return Response::redirect('/', 303);
    }
}
