<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\ProfileController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Profiles\ProfileStore;
use PHPUnit\Framework\TestCase;

final class ProfileControllerTest extends TestCase
{
    private bool $sessionExisted;
    private array $previousSession;
    private Session $session;
    private Auth $auth;
    private Csrf $csrf;

    protected function setUp(): void
    {
        $this->sessionExisted = isset($_SESSION);
        $this->previousSession = $_SESSION ?? [];
        $_SESSION = [];
        $this->session = new Session(false);
        $this->auth = new Auth($this->session);
        $this->csrf = new Csrf($this->session);
    }

    protected function tearDown(): void
    {
        if ($this->sessionExisted) {
            $_SESSION = $this->previousSession;
        } else {
            unset($_SESSION);
        }
    }

    public function testVisitorIsRedirectedToLogin(): void
    {
        $store = $this->store($this->profile());
        $response = $this->controller($store)->show();

        self::assertSame(302, $response->status());
        self::assertSame('/login', $response->headers()['Location']);
        self::assertSame([], $store->findUserIds);
    }

    public function testAuthenticatedUserSeesOnlyOwnProfileDataAndActiveNavigation(): void
    {
        $this->auth->login(41);
        $store = $this->store($this->profile());
        $response = $this->controller($store)->show();

        self::assertSame(200, $response->status());
        self::assertSame([41], $store->findUserIds);
        self::assertStringContainsString('Cinema Person', $response->body());
        self::assertStringContainsString('@cinema.person', $response->body());
        self::assertStringContainsString('Histórias &lt;script&gt; especiais.', $response->body());
        self::assertStringContainsString('Perfil privado', $response->body());
        self::assertStringContainsString('href="/perfil" aria-current="page"', $response->body());
        self::assertStringNotContainsString('person@example.com', $response->body());
        self::assertStringNotContainsString('google-secret-subject', $response->body());
        self::assertStringNotContainsString('<script> especiais', $response->body());
    }

    public function testPostWithoutCsrfReturnsForbiddenWithoutUpdate(): void
    {
        $this->auth->login(9);
        $store = $this->store($this->profile());
        $response = $this->controller($store)->update(new Request(parsedBody: [
            'display_name' => 'Changed',
            'bio' => 'Changed bio',
            'is_private' => '1',
        ]));

        self::assertSame(403, $response->status());
        self::assertSame([], $store->updates);
        self::assertStringContainsString('sessão de formulário expirou', $response->body());
    }

    public function testInvalidDisplayNameIsRejected(): void
    {
        $this->auth->login(9);
        $store = $this->store($this->profile());
        $response = $this->controller($store)->update($this->request([
            'display_name' => "  \n  ",
            'bio' => '',
        ]));

        self::assertSame(422, $response->status());
        self::assertSame([], $store->updates);
        self::assertStringContainsString('Informe seu nome de exibição', $response->body());
    }

    public function testOversizedBioIsRejected(): void
    {
        $this->auth->login(9);
        $store = $this->store($this->profile());
        $response = $this->controller($store)->update($this->request([
            'display_name' => 'Valid Name',
            'bio' => str_repeat('a', 501),
        ]));

        self::assertSame(422, $response->status());
        self::assertSame([], $store->updates);
        self::assertStringContainsString('bio deve ter no máximo 500 caracteres', $response->body());
    }

    public function testValidUpdatePersistsAllowedFieldsAndUsesPrg(): void
    {
        $this->auth->login(23);
        $store = $this->store($this->profile());
        $controller = $this->controller($store);
        $response = $controller->update($this->request([
            'display_name' => '  Nome Unicode ✦  ',
            'bio' => "  Linha um\r\nLinha dois  ",
            'is_private' => '1',
            'user_id' => '999',
            'username' => 'attempted-change',
        ]));

        self::assertSame(303, $response->status());
        self::assertSame('/perfil', $response->headers()['Location']);
        self::assertSame([[
            'user_id' => 23,
            'display_name' => 'Nome Unicode ✦',
            'bio' => "Linha um\nLinha dois",
            'is_private' => true,
        ]], $store->updates);

        $followed = $controller->show();
        self::assertStringContainsString('Perfil atualizado com sucesso', $followed->body());
        self::assertStringContainsString('Nome Unicode ✦', $followed->body());
    }

    public function testMissingCheckboxExplicitlyUpdatesProfileToPublic(): void
    {
        $this->auth->login(23);
        $store = $this->store($this->profile());
        $response = $this->controller($store)->update($this->request([
            'display_name' => 'Public Name',
            'bio' => '',
            'is_private' => 'arbitrary-value',
        ]));

        self::assertSame(303, $response->status());
        self::assertFalse($store->updates[0]['is_private']);
        self::assertNull($store->updates[0]['bio']);
    }

    public function testMissingAuthenticatedUserLogsOutSafely(): void
    {
        $this->auth->login(77);
        $store = $this->store(null);
        $response = $this->controller($store)->show();

        self::assertSame(302, $response->status());
        self::assertSame('/login', $response->headers()['Location']);
        self::assertFalse($this->auth->check());
        self::assertSame(
            ['Não foi possível recuperar seu perfil. Entre novamente para continuar.'],
            $this->session->consumeFlash()['error'],
        );
    }

    private function controller(ProfileStore $store): ProfileController
    {
        return new ProfileController(
            new View(dirname(__DIR__) . '/resources/views'),
            $this->auth,
            $this->csrf,
            $this->session,
            $store,
        );
    }

    private function request(array $body): Request
    {
        return new Request(parsedBody: ['_token' => $this->csrf->token(), ...$body]);
    }

    private function profile(): array
    {
        return [
            'username' => 'cinema.person',
            'email' => 'person@example.com',
            'display_name' => 'Cinema Person',
            'bio' => 'Histórias <script> especiais.',
            'avatar_url' => 'https://example.test/avatar.jpg',
            'cover_url' => null,
            'is_private' => 1,
            'created_at' => '2026-09-17 10:00:00',
            'provider_user_id' => 'google-secret-subject',
        ];
    }

    private function store(?array $profile): ProfileStore
    {
        return new class($profile) implements ProfileStore {
            public array $findUserIds = [];
            public array $updates = [];

            public function __construct(private ?array $profile)
            {
            }

            public function findByUserId(int $userId): ?array
            {
                $this->findUserIds[] = $userId;
                return $this->profile;
            }

            public function updateProfile(int $userId, string $displayName, ?string $bio, bool $isPrivate): void
            {
                $this->updates[] = [
                    'user_id' => $userId,
                    'display_name' => $displayName,
                    'bio' => $bio,
                    'is_private' => $isPrivate,
                ];
                if ($this->profile !== null) {
                    $this->profile['display_name'] = $displayName;
                    $this->profile['bio'] = $bio;
                    $this->profile['is_private'] = $isPrivate;
                }
            }
        };
    }
}
