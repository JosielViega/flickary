<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\AccountCreationResult;
use App\Authentication\AccountOnboardingService;
use App\Authentication\ExternalIdentity;
use App\Authentication\ExternalIdentityLinker;
use App\Core\Database;
use App\Repositories\ExternalIdentityRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class AccountOnboardingDatabaseTest extends TestCase
{
    private Database $database;
    private AccountOnboardingService $service;
    private ExternalIdentityRepository $externalIdentities;

    protected function setUp(): void
    {
        $databaseName = getenv('FLICKARY_TEST_DB_DATABASE');
        if (!is_string($databaseName) || !str_ends_with($databaseName, '_test')) {
            self::markTestSkipped('Disposable Flickary database was not configured.');
        }

        $this->database = new Database([
            'host' => getenv('FLICKARY_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('FLICKARY_TEST_DB_PORT') ?: 3306),
            'database' => $databaseName,
            'username' => getenv('FLICKARY_TEST_DB_USERNAME') ?: '',
            'password' => getenv('FLICKARY_TEST_DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
        ]);
        $users = new UserRepository($this->database);
        $profiles = new UserProfileRepository($this->database);
        $this->externalIdentities = new ExternalIdentityRepository($this->database);
        $this->service = new AccountOnboardingService(
            $this->database,
            $users,
            $profiles,
            $this->externalIdentities,
        );
        $this->clearAccounts();
    }

    protected function tearDown(): void
    {
        if (isset($this->database)) {
            $this->clearAccounts();
        }
    }

    public function testCreatesCompleteAccountAndHandlesExpectedConflictsAtomically(): void
    {
        $identity = new ExternalIdentity(
            'google',
            'google-subject-1',
            'person@example.com',
            true,
            'Person Name',
            'https://example.test/avatar.jpg',
        );

        $created = $this->service->createFromExternalIdentity($identity, 'person.one');

        self::assertSame(AccountCreationResult::CREATED, $created->status);
        self::assertNotNull($created->userId);
        self::assertSame(
            $created->userId,
            $this->externalIdentities->findUserId('google', 'google-subject-1'),
        );
        self::assertSame([
            'username' => 'person.one',
            'email' => 'person@example.com',
            'display_name' => 'Person Name',
            'avatar_url' => 'https://example.test/avatar.jpg',
            'cover_url' => null,
            'is_private' => 0,
        ], $this->accountRow($created->userId));
        self::assertSame(1, $this->countRows('users'));
        self::assertSame(1, $this->countRows('user_profiles'));
        self::assertSame(1, $this->countRows('user_external_identities'));

        $usernameConflict = $this->service->createFromExternalIdentity(
            new ExternalIdentity('google', 'google-subject-2', 'second@example.com', true, null, null),
            'PERSON.ONE',
        );
        self::assertSame(AccountCreationResult::USERNAME_TAKEN, $usernameConflict->status);

        $emailConflict = $this->service->createFromExternalIdentity(
            new ExternalIdentity('google', 'google-subject-3', 'PERSON@example.com', true, null, null),
            'person.three',
        );
        self::assertSame(AccountCreationResult::EMAIL_CONFLICT, $emailConflict->status);

        $existingIdentity = $this->service->createFromExternalIdentity($identity, 'another.username');
        self::assertSame(AccountCreationResult::IDENTITY_EXISTS, $existingIdentity->status);
        self::assertSame($created->userId, $existingIdentity->userId);
        self::assertSame(1, $this->countRows('users'));
        self::assertSame(1, $this->countRows('user_profiles'));
        self::assertSame(1, $this->countRows('user_external_identities'));
    }

    public function testRollsBackUserWhenProfileCreationFails(): void
    {
        $identity = new ExternalIdentity(
            'google',
            'rollback-subject',
            null,
            false,
            'Rollback',
            'https://example.test/' . str_repeat('a', 2100),
        );

        try {
            $this->service->createFromExternalIdentity($identity, 'rollback.user');
            self::fail('An oversized avatar URL should fail profile persistence.');
        } catch (PDOException) {
            self::assertSame(0, $this->countRows('users'));
            self::assertSame(0, $this->countRows('user_profiles'));
            self::assertSame(0, $this->countRows('user_external_identities'));
        }
    }

    public function testCreatesFacebookAccountWithoutEmailAndLinksProviderIdempotently(): void
    {
        $facebook = new ExternalIdentity('facebook', 'facebook-subject-1', null, false, 'Facebook Person', null);
        $created = $this->service->createFromExternalIdentity($facebook, 'facebook.person');

        self::assertSame(AccountCreationResult::CREATED, $created->status);
        self::assertNotNull($created->userId);
        self::assertNull($this->accountRow($created->userId)['email']);
        self::assertSame(
            ExternalIdentityLinker::LINKED,
            $this->externalIdentities->link(
                $created->userId,
                new ExternalIdentity('google', 'linked-google-subject', null, false, null, null),
            ),
        );
        self::assertSame(
            ExternalIdentityLinker::ALREADY_LINKED,
            $this->externalIdentities->link(
                $created->userId,
                new ExternalIdentity('google', 'linked-google-subject', null, false, null, null),
            ),
        );
        self::assertSame(['facebook', 'google'], $this->externalIdentities->providersForUser($created->userId));
    }

    public function testRejectsIdentityAlreadyOwnedByAnotherAccount(): void
    {
        $first = $this->service->createFromExternalIdentity(
            new ExternalIdentity('facebook', 'owned-facebook', null, false, 'One', null),
            'owner.one',
        );
        $second = $this->service->createFromExternalIdentity(
            new ExternalIdentity('google', 'google-two', null, false, 'Two', null),
            'owner.two',
        );

        self::assertSame(
            ExternalIdentityLinker::CONFLICT,
            $this->externalIdentities->link(
                $second->userId,
                new ExternalIdentity('facebook', 'owned-facebook', null, false, null, null),
            ),
        );
        self::assertSame($first->userId, $this->externalIdentities->findUserId('facebook', 'owned-facebook'));
    }

    private function accountRow(int $userId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT u.username, u.email, p.display_name, p.avatar_url, p.cover_url, p.is_private '
            . 'FROM users u INNER JOIN user_profiles p ON p.user_id = u.id WHERE u.id = :user_id',
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    private function countRows(string $table): int
    {
        if (!in_array($table, ['users', 'user_profiles', 'user_external_identities'], true)) {
            throw new \InvalidArgumentException('Unsupported test table.');
        }

        return (int) $this->database->connection()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    private function clearAccounts(): void
    {
        $this->database->connection()->exec('DELETE FROM user_external_identities');
        $this->database->connection()->exec('DELETE FROM user_profiles');
        $this->database->connection()->exec('DELETE FROM users');
    }
}
