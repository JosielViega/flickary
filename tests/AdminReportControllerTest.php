<?php

declare(strict_types=1);

namespace Tests;

use App\Administration\AdminAccess;
use App\Controllers\AdminReportController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Moderation\AdminReportReader;
use App\Moderation\ReportReason;
use App\Moderation\ReportStatus;
use App\Moderation\ReportTargetType;
use App\Moderation\SocialReport;
use App\Moderation\SocialReportPage;
use App\Moderation\ModerationActionReader;
use App\Moderation\ModerationActionPage;
use App\Moderation\ModerationActionStore;
use App\Moderation\ModerationActionResult;
use App\Moderation\ModerationNote;
use App\Moderation\SuspensionDuration;
use App\Moderation\ModeratedUser;
use PHPUnit\Framework\TestCase;

final class AdminReportControllerTest extends TestCase
{
    private bool$existed;private array$previous;private Session$session;private Auth$auth;private Csrf$csrf;private AdminAccessFake$admins;private AdminReportFake$reports;private ModerationFake$moderation;
    protected function setUp():void{$this->existed=isset($_SESSION);$this->previous=$_SESSION??[];$_SESSION=[];$this->session=new Session(false);$this->auth=new Auth($this->session);$this->csrf=new Csrf($this->session);$this->admins=new AdminAccessFake();$this->reports=new AdminReportFake();$this->moderation=new ModerationFake();}
    protected function tearDown():void{if($this->existed){$_SESSION=$this->previous;}else{unset($_SESSION);}}

    public function testAdminAccessIsDatabaseBackedAndHiddenFromNormalUsers():void
    {
        self::assertSame(303,$this->controller()->index(new Request())->status());
        $this->auth->login(5);self::assertSame(404,$this->controller()->index(new Request())->status());
        $this->admins->admin=true;self::assertSame(200,$this->controller()->index(new Request())->status());self::assertSame([5,5],$this->admins->checks);
    }

    public function testReviewRequiresCsrfAndOnlyPendingCanChange():void
    {
        $this->auth->login(5);$this->admins->admin=true;$this->reports->report=$this->report();
        self::assertSame(403,$this->controller()->resolve(new Request(),'8')->status());
        $response=$this->controller()->resolve(new Request(parsedBody:['_token'=>$this->csrf->token(),'moderator_note'=>'<script>alert(1)</script>']),'8');
        self::assertSame(303,$response->status());self::assertSame([[8,5,ReportStatus::Resolved,'<script>alert(1)</script>']],$this->reports->reviews);
        $this->reports->report=$this->report(ReportStatus::Resolved);self::assertSame(404,$this->controller()->dismiss(new Request(parsedBody:['_token'=>$this->csrf->token()]),'8')->status());
    }

    public function testAdminDetailEscapesSnapshotsAndNoteValidation():void
    {
        $this->auth->login(5);$this->admins->admin=true;$this->reports->report=$this->report();
        $body=$this->controller()->show('8')->body();self::assertStringContainsString('&lt;script&gt;',$body);self::assertStringNotContainsString('<script>',$body);
        $invalid=$this->controller()->resolve(new Request(parsedBody:['_token'=>$this->csrf->token(),'moderator_note'=>str_repeat('x',1001)]),'8');self::assertSame(422,$invalid->status());self::assertSame([],$this->reports->reviews);
    }

    public function testSuspensionRequiresCsrfAllowlistedDurationAndMandatoryNote():void
    {
        $this->auth->login(5);$this->admins->admin=true;$this->reports->report=$this->report();
        self::assertSame(403,$this->controller()->suspend(new Request(),'8')->status());
        self::assertSame(422,$this->controller()->suspend(new Request(parsedBody:['_token'=>$this->csrf->token(),'duration'=>'forever','moderator_note'=>'short']),'8')->status());
        $response=$this->controller()->suspend(new Request(parsedBody:['_token'=>$this->csrf->token(),'duration'=>'7d','moderator_note'=>'A justified temporary suspension.']),'8');
        self::assertSame(303,$response->status());self::assertSame('7d',$this->moderation->suspensions[0][2]->value);
    }

    public function testAdminTargetHidesSuspensionAndDirectPostIsControlled():void
    {
        $this->auth->login(5);$this->admins->admin=true;$this->reports->report=$this->report();$this->moderation->user=new ModeratedUser(2,'target','Target',true,null,false);
        $body=$this->controller()->show('8')->body();self::assertStringContainsString('Conta protegida',$body);self::assertStringNotContainsString('Suspender temporariamente',$body);
        $this->moderation->result=ModerationActionResult::ProtectedTarget;$response=$this->controller()->suspend(new Request(parsedBody:['_token'=>$this->csrf->token(),'duration'=>'24h','moderator_note'=>'Admin target must remain protected.']),'8');self::assertSame(422,$response->status());
    }

    public function testCommentRemovalRequiresNoteAndUsesDedicatedAction():void
    {
        $this->auth->login(5);$this->admins->admin=true;$this->reports->report=$this->report();
        self::assertSame(422,$this->controller()->removeComment(new Request(parsedBody:['_token'=>$this->csrf->token(),'moderator_note'=>'tiny']),'8')->status());
        self::assertSame(303,$this->controller()->removeComment(new Request(parsedBody:['_token'=>$this->csrf->token(),'moderator_note'=>'Remove only the reported comment.']),'8')->status());self::assertSame([[8,5]],$this->moderation->removals);
    }

    private function controller():AdminReportController{return new AdminReportController(new View(dirname(__DIR__).'/resources/views'),$this->auth,$this->csrf,$this->session,$this->admins,$this->reports,$this->moderation,$this->moderation);}
    private function report(ReportStatus$status=ReportStatus::Pending):SocialReport{return new SocialReport(8,1,'reporter',2,ReportTargetType::Comment,9,ReportReason::Harassment,'<img onerror=x>','target','<Target>','<script>alert(1)</script>',$status,null,null,null,'2026-11-17 10:00:00');}
}

final class ModerationFake implements ModerationActionReader,ModerationActionStore
{
    public ?ModeratedUser$user=null;public ModerationActionResult$result=ModerationActionResult::Applied;public array$suspensions=[];public array$removals=[];
    public function paginateAll(int$page,int$perPage):ModerationActionPage{return new ModerationActionPage([],0,$page,$perPage);}
    public function paginateForUser(int$userId,int$page,int$perPage):ModerationActionPage{return new ModerationActionPage([],0,$page,$perPage);}
    public function findUserByUsername(string$username):?ModeratedUser{return null;}
    public function findUserById(int$userId):?ModeratedUser{return$this->user??new ModeratedUser($userId,'target','Target',false,null,false);}
    public function liveCommentMatchesReport(SocialReport$report):bool{return true;}
    public function suspendFromReport(int$reportId,int$adminUserId,SuspensionDuration$duration,ModerationNote$note):ModerationActionResult{$this->suspensions[]=[$reportId,$adminUserId,$duration,$note];return$this->result;}
    public function unsuspend(string$username,int$adminUserId,ModerationNote$note):ModerationActionResult{return ModerationActionResult::Applied;}
    public function removeReportedComment(int$reportId,int$adminUserId,ModerationNote$note):ModerationActionResult{$this->removals[]=[$reportId,$adminUserId];return$this->result;}
}

final class AdminAccessFake implements AdminAccess{public bool$admin=false;public array$checks=[];public function isAdmin(int$userId):bool{$this->checks[]=$userId;return$this->admin;}}
final class AdminReportFake implements AdminReportReader
{
    public ?SocialReport$report=null;public array$reviews=[];
    public function paginateAdmin(?ReportStatus$status,int$page,int$perPage):SocialReportPage{return new SocialReportPage($this->report===null?[]:[$this->report],$this->report===null?0:1,$page,$perPage);}
    public function findAdmin(int$reportId):?SocialReport{return$this->report;}
    public function review(int$reportId,int$reviewerUserId,ReportStatus$status,?string$moderatorNote):bool{$this->reviews[]=[$reportId,$reviewerUserId,$status,$moderatorNote];return true;}
}
