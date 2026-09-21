<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Media\UserMediaStatus;
use App\Statistics\StatisticsReader;

final class StatisticsController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly StatisticsReader $statistics,
    ) {
    }

    public function index(): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }

        return Response::html($this->view->render('pages/statistics', [
            'title' => 'Estatísticas — Flickary',
            'currentRoute' => 'statistics',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'searchQuery' => '',
            'statistics' => $this->statistics->readForUser($userId, date('Y-m-d')),
            'statusOptions' => UserMediaStatus::options(),
        ]));
    }
}
