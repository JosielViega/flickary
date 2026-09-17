<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Profiles\ProfileStore;

final class ProfileController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly ProfileStore $profiles,
    ) {
    }

    public function show(): Response
    {
        $resolved = $this->resolveAuthenticatedProfile();
        if ($resolved instanceof Response) {
            return $resolved;
        }

        [, $profile] = $resolved;

        return $this->page($profile, messages: $this->session->consumeFlash());
    }

    public function update(Request $request): Response
    {
        $resolved = $this->resolveAuthenticatedProfile(303);
        if ($resolved instanceof Response) {
            return $resolved;
        }

        [$userId, $profile] = $resolved;

        if (!$this->csrf->verify($request->input('_token'))) {
            return $this->page(
                $profile,
                errors: ['form' => 'Sua sessão de formulário expirou. Atualize a página e tente novamente.'],
                status: 403,
            );
        }

        [$form, $errors] = $this->validatedForm($request);
        if ($errors !== []) {
            return $this->page($profile, form: $form, errors: $errors, status: 422);
        }

        $this->profiles->updateProfile(
            $userId,
            $form['display_name'],
            $form['bio'],
            $form['is_private'],
        );
        $this->session->flash('success', 'Perfil atualizado com sucesso.');

        return Response::redirect('/perfil', 303);
    }

    private function resolveAuthenticatedProfile(int $redirectStatus = 302): array|Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', $redirectStatus);
        }

        $profile = $this->profiles->findByUserId($userId);
        if ($profile === null) {
            $this->auth->logout();
            $this->session->flash('error', 'Não foi possível recuperar seu perfil. Entre novamente para continuar.');

            return Response::redirect('/login', $redirectStatus);
        }

        return [$userId, $this->presentableProfile($profile)];
    }

    private function validatedForm(Request $request): array
    {
        $errors = [];
        $displayNameInput = $request->input('display_name');
        $displayName = is_string($displayNameInput) ? trim($displayNameInput) : '';
        if ($displayName === '') {
            $errors['display_name'] = 'Informe seu nome de exibição.';
        } elseif ($this->length($displayName) > 100) {
            $errors['display_name'] = 'O nome de exibição deve ter no máximo 100 caracteres.';
        } elseif (preg_match('/[\x00-\x1F\x7F]/u', $displayName) === 1) {
            $errors['display_name'] = 'O nome de exibição contém caracteres não permitidos.';
        }

        $bioInput = $request->input('bio');
        $bio = is_string($bioInput) ? trim(str_replace(["\r\n", "\r"], "\n", $bioInput)) : '';
        if (!is_string($bioInput)) {
            $errors['bio'] = 'A bio deve ser um texto válido.';
        } elseif ($this->length($bio) > 500) {
            $errors['bio'] = 'A bio deve ter no máximo 500 caracteres.';
        } elseif (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $bio) === 1) {
            $errors['bio'] = 'A bio contém caracteres não permitidos.';
        }

        return [[
            'display_name' => $displayName,
            'bio' => $bio === '' ? null : $bio,
            'is_private' => $request->input('is_private') === '1',
        ], $errors];
    }

    private function presentableProfile(array $profile): array
    {
        $createdAt = null;
        $createdAtValue = $profile['created_at'] ?? null;
        if (is_string($createdAtValue) && $createdAtValue !== '') {
            try {
                $createdAt = (new \DateTimeImmutable($createdAtValue))->format('d/m/Y');
            } catch (\Throwable) {
                // A data é metadado auxiliar; uma inconsistência não deve derrubar o perfil.
            }
        }

        return [
            'username' => (string) ($profile['username'] ?? ''),
            'display_name' => (string) ($profile['display_name'] ?? ''),
            'bio' => is_string($profile['bio'] ?? null) ? $profile['bio'] : null,
            'avatar_url' => $this->safeHttpsUrl($profile['avatar_url'] ?? null),
            'cover_url' => $this->safeHttpsUrl($profile['cover_url'] ?? null),
            'is_private' => (bool) ($profile['is_private'] ?? false),
            'created_at' => $createdAt,
        ];
    }

    private function safeHttpsUrl(mixed $value): ?string
    {
        if (!is_string($value) || strlen($value) > 2048 || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https' ? $value : null;
    }

    private function page(
        array $profile,
        ?array $form = null,
        array $errors = [],
        array $messages = [],
        int $status = 200,
    ): Response {
        return Response::html($this->view->render('pages/profile', [
            'title' => 'Seu perfil · Flickary',
            'currentRoute' => 'profile',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'profile' => $profile,
            'form' => $form ?? [
                'display_name' => $profile['display_name'],
                'bio' => $profile['bio'],
                'is_private' => $profile['is_private'],
            ],
            'errors' => $errors,
            'messages' => $messages,
        ]), $status);
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}
