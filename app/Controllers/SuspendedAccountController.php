<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Moderation\AccountSuspensionGuard;

final class SuspendedAccountController
{
    public function __construct(private readonly View $view, private readonly Session $session) {}

    public function show(): Response
    {
        $until = $this->session->get(AccountSuspensionGuard::CONTEXT_KEY);
        if (is_string($until)) {
            try {
                if (new \DateTimeImmutable($until) <= new \DateTimeImmutable('now')) {
                    $this->session->forget(AccountSuspensionGuard::CONTEXT_KEY);
                    $until = null;
                }
            } catch (\Throwable) {
                $this->session->forget(AccountSuspensionGuard::CONTEXT_KEY);
                $until = null;
            }
        }
        return Response::html($this->view->render('pages/account-suspended', [
            'title' => 'Conta temporariamente suspensa · Flickary',
            'suspendedUntil' => is_string($until) ? $until : null,
        ], 'layouts/auth'));
    }
}
