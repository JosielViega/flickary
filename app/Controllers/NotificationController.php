<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Notifications\SocialNotificationReader;
use App\Notifications\SocialNotificationStore;

final class NotificationController
{
    private const PER_PAGE = 30;

    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly SocialNotificationReader $notifications,
        private readonly SocialNotificationStore $store,
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }
        $page = filter_var(
            $request->query('page', 1),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 10000]],
        );

        return Response::html($this->view->render('pages/notifications', [
            'title' => 'Notificações · Flickary',
            'currentRoute' => 'social',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'notifications' => $this->notifications->paginateForUser(
                $userId,
                $page === false ? 1 : (int) $page,
                self::PER_PAGE,
            ),
            'unreadCount' => $this->notifications->countUnread($userId),
        ]));
    }

    public function open(Request $request, string $id): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }
        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('A sessão de formulário expirou.', 403);
        }
        $notificationId = $this->positiveId($id);
        if ($notificationId === null) {
            return $this->notFound($id);
        }
        $notification = $this->notifications->findForRecipient($userId, $notificationId);
        if ($notification === null || !$this->store->markRead($userId, $notificationId)) {
            return $this->notFound($id);
        }

        return Response::redirect($notification->destination(), 303);
    }

    public function markAllRead(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }
        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('A sessão de formulário expirou.', 403);
        }
        $this->store->markAllRead($userId);

        return Response::redirect('/notificacoes', 303);
    }

    private function positiveId(string $value): ?int
    {
        if ($value === '' || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : (int) $id;
    }

    private function notFound(string $id): Response
    {
        return Response::html($this->view->render('pages/404', [
            'title' => 'Notificação não encontrada',
            'path' => '/notificacoes/' . $id,
            'currentRoute' => 'social',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
        ]), 404);
    }
}
