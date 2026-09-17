<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\AccountCreationResult;
use App\Authentication\AccountCreator;
use App\Authentication\ExternalIdentity;
use App\Authentication\PendingExternalOnboarding;
use App\Authentication\UsernamePolicy;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

final class OnboardingController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly PendingExternalOnboarding $pendingOnboarding,
        private readonly UsernamePolicy $usernamePolicy,
        private readonly AccountCreator $accountCreator,
    ) {
    }

    public function show(): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('/');
        }

        $identity = $this->pendingOnboarding->current();
        if ($identity === null) {
            return $this->restartLogin();
        }

        return $this->form($identity);
    }

    public function store(Request $request): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('/', 303);
        }

        $identity = $this->pendingOnboarding->current();
        if ($identity === null) {
            return $this->restartLogin(303);
        }

        if (!$this->csrf->verify($request->input('_token'))) {
            return $this->form($identity, '', 'Sua sessão de formulário expirou. Atualize a página e tente novamente.', 403);
        }

        $username = $this->usernamePolicy->normalize($request->input('username'));
        $validationError = $this->usernamePolicy->error($username);
        if ($validationError !== null) {
            return $this->form($identity, $username ?? '', $validationError, 422);
        }

        $result = $this->accountCreator->createFromExternalIdentity($identity, $username);

        if (in_array($result->status, [AccountCreationResult::CREATED, AccountCreationResult::IDENTITY_EXISTS], true)) {
            if ($result->userId === null) {
                throw new \RuntimeException('Account creation result is missing a user ID.');
            }

            $this->pendingOnboarding->clear();
            $this->auth->login($result->userId);

            return Response::redirect('/', 303);
        }

        if ($result->status === AccountCreationResult::USERNAME_TAKEN) {
            return $this->form($identity, $username, 'Esse username já está em uso. Escolha outro.', 422);
        }

        if ($result->status === AccountCreationResult::EMAIL_CONFLICT) {
            return $this->form(
                $identity,
                $username,
                'Já existe uma conta associada a este e-mail. A vinculação segura entre contas ainda não está disponível.',
                409,
            );
        }

        throw new \RuntimeException('Unknown account creation result.');
    }

    private function form(
        ExternalIdentity $identity,
        string $username = '',
        ?string $error = null,
        int $status = 200,
    ): Response {
        return Response::html($this->view->render('pages/onboarding-username', [
            'title' => 'Escolha seu username · Flickary',
            'identity' => $identity,
            'username' => $username,
            'error' => $error,
            'csrfField' => $this->csrf->field(),
        ], 'layouts/auth'), $status);
    }

    private function restartLogin(int $status = 302): Response
    {
        $this->session->flash('error', 'Inicie novamente o acesso com Google ou Facebook para continuar.');

        return Response::redirect('/login', $status);
    }
}
