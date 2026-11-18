<?php

declare(strict_types=1);

namespace App\Moderation;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AccountSuspensionGuard
{
    public const CONTEXT_KEY = '_account_suspended_until';

    public function __construct(
        private readonly Auth $auth,
        private readonly Session $session,
        private readonly AccountAccessReader $accounts,
    ) {}

    public function intercept(Request $request): ?Response
    {
        $userId = $this->auth->id();
        if ($userId === null) { return null; }
        return $this->denyLoginIfSuspended($userId);
    }

    public function denyLoginIfSuspended(int $userId): ?Response
    {
        $until = $this->accounts->activeSuspensionUntil($userId);
        if ($until === null) { $this->session->forget(self::CONTEXT_KEY); return null; }
        if ($this->auth->check()) { $this->auth->logout(); }
        $this->session->put(self::CONTEXT_KEY, $until);
        return Response::redirect('/conta/suspensa', 303);
    }
}
