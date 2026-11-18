<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Moderation\AccountAccessReader;
use App\Moderation\AccountSuspensionGuard;
use PHPUnit\Framework\TestCase;

final class AccountSuspensionGuardTest extends TestCase
{
    protected function setUp():void{$_SESSION=[];}
    protected function tearDown():void{unset($_SESSION);}

    public function testGuestPerformsNoLookupAndActiveAccountContinues():void
    {
        $session=new Session(false);$auth=new Auth($session);$reader=new GuardAccessFake(null);$guard=new AccountSuspensionGuard($auth,$session,$reader);
        self::assertNull($guard->intercept(new Request()));self::assertSame(0,$reader->calls);
        $auth->login(7);self::assertNull($guard->intercept(new Request()));self::assertSame(1,$reader->calls);self::assertSame(7,$auth->id());
    }

    public function testExistingSuspendedSessionIsInvalidatedOnNextRequest():void
    {
        $session=new Session(false);$auth=new Auth($session);$auth->login(9);$guard=new AccountSuspensionGuard($auth,$session,new GuardAccessFake('2026-12-01 12:00:00'));
        $response=$guard->intercept(new Request());self::assertSame(303,$response?->status());self::assertSame('/conta/suspensa',$response?->headers()['Location']);
        self::assertFalse($auth->check());self::assertSame('2026-12-01 12:00:00',$session->get(AccountSuspensionGuard::CONTEXT_KEY));
    }
}

final class GuardAccessFake implements AccountAccessReader
{
    public int$calls=0;public function __construct(private ?string$until){}public function activeSuspensionUntil(int$userId):?string{$this->calls++;return$this->until;}
}
