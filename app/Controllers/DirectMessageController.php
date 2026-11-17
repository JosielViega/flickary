<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\UsernamePolicy;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Messaging\DirectMessageBody;
use App\Messaging\DirectMessageReader;
use App\Messaging\DirectMessageStore;
use App\Social\FollowStore;
use App\Social\PublicProfile;
use App\Social\PublicProfileReader;
use App\Social\UserSocialControlStore;

final class DirectMessageController
{
    private const INBOX_PER_PAGE = 30;
    private const MESSAGES_PER_PAGE = 50;

    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly UsernamePolicy $usernamePolicy,
        private readonly PublicProfileReader $profiles,
        private readonly FollowStore $follows,
        private readonly DirectMessageReader $messages,
        private readonly DirectMessageStore $store,
        private readonly ?UserSocialControlStore $socialControls=null,
    ) {}

    public function index(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) { return Response::redirect('/login', 303); }
        return Response::html($this->view->render('pages/messages', [
            'title' => 'Mensagens · Flickary',
            'currentRoute' => 'social',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'conversations' => $this->messages->paginateConversations($userId, $this->page($request->query('page', 1)), self::INBOX_PER_PAGE),
            'currentUserId' => $userId,
        ]));
    }

    public function show(Request $request, string $username): Response
    {
        $resolved = $this->resolve($username);
        if ($resolved instanceof Response) { return $resolved; }
        [$userId, $other] = $resolved;
        return $this->conversationPage($userId, $other, $this->page($request->query('page', 1)));
    }

    public function send(Request $request, string $username): Response
    {
        $resolved = $this->resolveMutation($request, $username);
        if ($resolved instanceof Response) { return $resolved; }
        [$userId, $other] = $resolved;
        if ($this->socialControls?->isBlockedEitherDirection($userId,$other->userId)===true||!$this->follows->areMutualFollowers($userId, $other->userId)) {
            return $this->notFound('/mensagens/com/' . $username);
        }
        $submitted = $request->input('body');
        [$body, $error] = DirectMessageBody::validate($submitted);
        if (!$body instanceof DirectMessageBody) {
            return $this->conversationPage(
                $userId,
                $other,
                1,
                is_string($submitted) ? str_replace(["\r\n", "\r"], "\n", $submitted) : '',
                $error ?? 'Escreva uma mensagem válida.',
                422,
            );
        }
        if (!$this->store->send($userId, $other->userId, $body)) {
            return $this->notFound('/mensagens/com/' . $username);
        }
        return Response::redirect('/mensagens/com/' . rawurlencode($other->username), 303);
    }

    public function markRead(Request $request, string $username): Response
    {
        $resolved = $this->resolveMutation($request, $username);
        if ($resolved instanceof Response) { return $resolved; }
        [$userId, $other] = $resolved;
        if (!$this->store->markRead($userId, $other->userId)) {
            return $this->notFound('/mensagens/com/' . $username);
        }
        return Response::redirect('/mensagens/com/' . rawurlencode($other->username), 303);
    }

    public function open(Request $request, string $username): Response
    {
        return $this->markRead($request, $username);
    }

    /** @return array{0:int,1:PublicProfile}|Response */
    private function resolve(string $username): array|Response
    {
        $userId = $this->auth->id();
        if ($userId === null) { return Response::redirect('/login', 303); }
        $normalized = $this->usernamePolicy->normalize($username);
        $other = $normalized !== null && $normalized === $username && $this->usernamePolicy->error($normalized) === null
            ? $this->profiles->findByUsername($normalized) : null;
        if (!$other instanceof PublicProfile || $other->userId === $userId) {
            return $this->notFound('/mensagens/com/' . $username);
        }
        $exists = $this->messages->conversationExists($userId, $other->userId);
        if (!$exists && !$this->follows->areMutualFollowers($userId, $other->userId)) {
            return $this->notFound('/mensagens/com/' . $username);
        }
        return [$userId, $other];
    }

    /** @return array{0:int,1:PublicProfile}|Response */
    private function resolveMutation(Request $request, string $username): array|Response
    {
        if ($this->auth->id() === null) { return Response::redirect('/login', 303); }
        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('A sessão de formulário expirou.', 403);
        }
        return $this->resolve($username);
    }

    private function conversationPage(int $userId, PublicProfile $other, int $page, string $body = '', ?string $error = null, int $status = 200): Response
    {
        $exists = $this->messages->conversationExists($userId, $other->userId);
        $blocked=$this->socialControls?->isBlockedEitherDirection($userId,$other->userId)===true;
        $canSend=!$blocked&&$this->follows->areMutualFollowers($userId,$other->userId);
        if (!$exists && !$canSend) { return $this->notFound('/mensagens/com/' . $other->username); }
        return Response::html($this->view->render('pages/direct-conversation', [
            'title' => $other->displayName . ' · Mensagens · Flickary',
            'currentRoute' => 'social',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'other' => $other,
            'currentUserId' => $userId,
            'conversationExists' => $exists,
            'canSend' => $canSend,
            'messagesPage' => $exists ? $this->messages->paginateMessages($userId, $other->userId, $page, self::MESSAGES_PER_PAGE) : null,
            'unreadCount' => $exists ? $this->messages->countUnreadForConversation($userId, $other->userId) : 0,
            'messageBody' => $body,
            'messageError' => $error,
            'pairBlocked'=>$blocked,
        ]), $status);
    }

    private function page(mixed $value): int
    {
        $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        return $page === false ? 1 : (int) $page;
    }

    private function notFound(string $path): Response
    {
        return Response::html($this->view->render('pages/404', [
            'title' => 'Conversa não encontrada', 'path' => $path, 'currentRoute' => 'social',
            'auth' => $this->auth, 'csrf' => $this->csrf,
        ]), 404);
    }
}
