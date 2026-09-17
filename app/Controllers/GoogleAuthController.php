<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\ExternalIdentityFinder;
use App\Authentication\GoogleIdentityVerifier;
use App\Authentication\PendingExternalOnboarding;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class GoogleAuthController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Session $session,
        private readonly GoogleIdentityVerifier $verifier,
        private readonly ExternalIdentityFinder $externalIdentities,
        private readonly PendingExternalOnboarding $pendingOnboarding,
        private readonly string $clientId,
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('/', 303);
        }

        if ($this->clientId === '') {
            return $this->reject('O acesso com Google não está configurado neste ambiente.');
        }

        if (!$this->validGoogleCsrf($request)) {
            return $this->reject('Não foi possível validar a solicitação de acesso. Tente novamente.');
        }

        $credential = $request->input('credential');
        if (!is_string($credential) || $credential === '' || strlen($credential) > 16384) {
            return $this->reject('Não foi possível validar sua conta Google. Tente novamente.');
        }

        $identity = $this->verifier->verify($credential);
        if ($identity === null) {
            return $this->reject('Não foi possível validar sua conta Google. Tente novamente.');
        }

        $userId = $this->externalIdentities->findUserId($identity->provider, $identity->providerUserId);
        if ($userId !== null) {
            $this->pendingOnboarding->clear();
            $this->auth->login($userId);

            return Response::redirect('/', 303);
        }

        $this->pendingOnboarding->store($identity);

        return Response::redirect('/onboarding/username', 303);
    }

    private function validGoogleCsrf(Request $request): bool
    {
        $cookieToken = $request->cookie('g_csrf_token');
        $bodyToken = $request->input('g_csrf_token');

        return is_string($cookieToken)
            && is_string($bodyToken)
            && $cookieToken !== ''
            && strlen($cookieToken) <= 4096
            && strlen($bodyToken) <= 4096
            && hash_equals($cookieToken, $bodyToken);
    }

    private function reject(string $message): Response
    {
        $this->session->flash('error', $message);

        return Response::redirect('/login', 303);
    }
}
