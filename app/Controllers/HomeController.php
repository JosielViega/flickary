<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Home\HomeEditorialService;
use App\Home\HomePersonalService;

final class HomeController
{
    public function __construct(
        private readonly View $view,
        private readonly array $appConfig,
        private readonly ?Auth $auth = null,
        private readonly ?Csrf $csrf = null,
        private readonly ?HomeEditorialService $editorial = null,
        private readonly ?HomePersonalService $personal = null,
    ) {
    }

    public function index(): Response
    {
        $editorialPage = $this->editorial?->page();
        $userId = $this->auth?->id();
        $personalPage = $userId === null ? null : $this->personal?->page($userId);

        return Response::html($this->view->render('pages/home', [
            'title' => 'Flickary',
            'appName' => $this->appConfig['name'],
            'currentRoute' => 'home',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'editorial' => $editorialPage,
            'personal' => $personalPage,
        ]));
    }
}
