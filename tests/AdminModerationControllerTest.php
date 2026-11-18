<?php

declare(strict_types=1);

namespace Tests;

use App\Administration\AdminAccess;
use App\Authentication\UsernamePolicy;
use App\Controllers\AdminModerationController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Moderation\ModeratedUser;
use App\Moderation\ModerationActionPage;
use App\Moderation\ModerationActionReader;
use App\Moderation\ModerationActionResult;
use App\Moderation\ModerationActionStore;
use App\Moderation\ModerationNote;
use App\Moderation\SocialReport;
use App\Moderation\SuspensionDuration;
use PHPUnit\Framework\TestCase;

final class AdminModerationControllerTest extends TestCase
{
    private Session$session;private Auth$auth;private Csrf$csrf;private AdminModerationAccessFake$admins;private AdminModerationFake$moderation;
    protected function setUp():void{$_SESSION=[];$this->session=new Session(false);$this->auth=new Auth($this->session);$this->csrf=new Csrf($this->session);$this->admins=new AdminModerationAccessFake();$this->moderation=new AdminModerationFake();}
    protected function tearDown():void{unset($_SESSION);}

    public function testGuestRedirectsNormalUserGets404AndAdminCanViewAudit():void
    {
        self::assertSame(303,$this->controller()->actions(new Request())->status());$this->auth->login(3);self::assertSame(404,$this->controller()->actions(new Request())->status());$this->admins->admin=true;self::assertSame(200,$this->controller()->actions(new Request())->status());
    }

    public function testUserPageShowsOnlyAdministrativeStateAndEscapesIdentity():void
    {
        $this->auth->login(3);$this->admins->admin=true;$this->moderation->user=new ModeratedUser(9,'target.user','<Target>',false,'2026-12-01 10:00:00',true);$body=$this->controller()->user(new Request(),'target.user')->body();self::assertStringContainsString('&lt;Target&gt;',$body);self::assertStringNotContainsString('email',$body);self::assertStringContainsString('Encerrar suspensão',$body);
    }

    public function testReactivationRequiresCsrfAndMandatoryNote():void
    {
        $this->auth->login(3);$this->admins->admin=true;$this->moderation->user=new ModeratedUser(9,'target.user','Target',false,'2026-12-01 10:00:00',true);
        self::assertSame(403,$this->controller()->reactivate(new Request(),'target.user')->status());self::assertSame(422,$this->controller()->reactivate(new Request(parsedBody:['_token'=>$this->csrf->token(),'moderator_note'=>'short']),'target.user')->status());
        self::assertSame(303,$this->controller()->reactivate(new Request(parsedBody:['_token'=>$this->csrf->token(),'moderator_note'=>'Reactivate after corrected decision.']),'target.user')->status());self::assertSame([['target.user',3]],$this->moderation->unsuspensions);
    }

    private function controller():AdminModerationController{return new AdminModerationController(new View(dirname(__DIR__).'/resources/views'),$this->auth,$this->csrf,$this->session,$this->admins,new UsernamePolicy(),$this->moderation,$this->moderation);}
}

final class AdminModerationAccessFake implements AdminAccess{public bool$admin=false;public function isAdmin(int$userId):bool{return$this->admin;}}
final class AdminModerationFake implements ModerationActionReader,ModerationActionStore
{
    public ?ModeratedUser$user=null;public array$unsuspensions=[];
    public function paginateAll(int$page,int$perPage):ModerationActionPage{return new ModerationActionPage([],0,$page,$perPage);}
    public function paginateForUser(int$userId,int$page,int$perPage):ModerationActionPage{return new ModerationActionPage([],0,$page,$perPage);}
    public function findUserByUsername(string$username):?ModeratedUser{return$this->user;}
    public function findUserById(int$userId):?ModeratedUser{return$this->user;}
    public function liveCommentMatchesReport(SocialReport$report):bool{return false;}
    public function suspendFromReport(int$reportId,int$adminUserId,SuspensionDuration$duration,ModerationNote$note):ModerationActionResult{return ModerationActionResult::Applied;}
    public function unsuspend(string$username,int$adminUserId,ModerationNote$note):ModerationActionResult{$this->unsuspensions[]=[$username,$adminUserId];return ModerationActionResult::Applied;}
    public function removeReportedComment(int$reportId,int$adminUserId,ModerationNote$note):ModerationActionResult{return ModerationActionResult::Applied;}
}
