<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\ExternalIdentityFinder;
use App\Authentication\ExternalIdentityLinker;
use App\Authentication\FacebookIdentityProvider;
use App\Authentication\FacebookOAuthState;
use App\Authentication\PendingExternalOnboarding;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class FacebookAuthController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Session $session,
        private readonly FacebookIdentityProvider $facebook,
        private readonly FacebookOAuthState $state,
        private readonly ExternalIdentityFinder $externalIdentities,
        private readonly ExternalIdentityLinker $identityLinker,
        private readonly PendingExternalOnboarding $pendingOnboarding,
    ) {
    }

    public function start(): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('/perfil');
        }
        if (!$this->facebook->configured()) {
            return $this->reject('/login', 'O acesso com Facebook não está configurado neste ambiente.');
        }

        return Response::redirect($this->facebook->authorizationUrl($this->state->issue('login')));
    }

    public function callback(Request $request): Response
    {
        $state = $this->state->consume($request->query('state'));
        $fallback = $this->auth->check() ? '/perfil' : '/login';
        if ($state === null) {
            return $this->reject($fallback, 'Não foi possível validar a solicitação do Facebook. Tente novamente.');
        }

        $destination = $state['intent'] === 'link' ? '/perfil' : '/login';
        if (is_string($request->query('error')) && $request->query('error') !== '') {
            return $this->reject($destination, 'O acesso com Facebook foi cancelado. Nenhuma alteração foi feita.');
        }

        $code = $request->query('code');
        if (!is_string($code) || $code === '' || strlen($code) > 4096) {
            return $this->reject($destination, 'Não foi possível validar sua conta Facebook. Tente novamente.');
        }

        $identity = $this->facebook->identityFromCode($code);
        if ($identity === null) {
            return $this->reject($destination, 'Não foi possível validar sua conta Facebook. Tente novamente.');
        }

        if ($state['intent'] === 'link') {
            $userId = $this->auth->id();
            if ($userId === null || $userId !== $state['user_id']) {
                return $this->reject('/perfil', 'Sua sessão mudou durante a conexão. Tente novamente.');
            }

            $result = $this->identityLinker->link($userId, $identity);
            if ($result === ExternalIdentityLinker::CONFLICT) {
                return $this->reject('/perfil', 'Esta conta Facebook já está conectada a outra conta Flickary.');
            }

            $this->session->flash('success', $result === ExternalIdentityLinker::LINKED
                ? 'Conta Facebook conectada com sucesso.'
                : 'Esta conta Facebook já está conectada ao seu perfil.');
            return Response::redirect('/perfil', 303);
        }

        if ($this->auth->check()) {
            return $this->reject('/perfil', 'Você já está autenticado. Use a área de conexões do perfil.');
        }

        $userId = $this->externalIdentities->findUserId('facebook', $identity->providerUserId);
        if ($userId !== null) {
            $this->pendingOnboarding->clear();
            $this->auth->login($userId);
            return Response::redirect('/', 303);
        }

        $this->pendingOnboarding->store($identity);
        return Response::redirect('/onboarding/username', 303);
    }

    private function reject(string $location, string $message): Response
    {
        $this->session->flash('error', $message);
        return Response::redirect($location, 303);
    }
}
