<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Social\SocialActivityCommentReader;

final class ProfileCommentsController
{
    private const PER_PAGE = 30;

    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly SocialActivityCommentReader $comments,
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }
        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 10000],
        ]);
        $page = $page === false ? 1 : (int) $page;

        return Response::html($this->view->render('pages/profile-comments', [
            'title' => 'Meus comentários · Flickary',
            'currentRoute' => 'profile',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'comments' => $this->comments->paginateForAuthor($userId, $page, self::PER_PAGE),
        ]));
    }
}
