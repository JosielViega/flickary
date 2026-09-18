<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;

final class AboutController
{
    public function __construct(
        private readonly View $view,
        private readonly ?Auth $auth = null,
        private readonly ?Csrf $csrf = null,
    ) {
    }

    public function index(): Response
    {
        return Response::html($this->view->render('pages/about', [
            'title' => 'Sobre o Flickary',
            'currentRoute' => 'about',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
        ]));
    }
}
