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
use PHPUnit\Framework\TestCase;

final class AdminReportControllerTest extends TestCase
{
    private bool$existed;private array$previous;private Session$session;private Auth$auth;private Csrf$csrf;private AdminAccessFake$admins;private AdminReportFake$reports;
    protected function setUp():void{$this->existed=isset($_SESSION);$this->previous=$_SESSION??[];$_SESSION=[];$this->session=new Session(false);$this->auth=new Auth($this->session);$this->csrf=new Csrf($this->session);$this->admins=new AdminAccessFake();$this->reports=new AdminReportFake();}
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

    private function controller():AdminReportController{return new AdminReportController(new View(dirname(__DIR__).'/resources/views'),$this->auth,$this->csrf,$this->session,$this->admins,$this->reports);}
    private function report(ReportStatus$status=ReportStatus::Pending):SocialReport{return new SocialReport(8,1,'reporter',2,ReportTargetType::Comment,9,ReportReason::Harassment,'<img onerror=x>','target','<Target>','<script>alert(1)</script>',$status,null,null,null,'2026-11-17 10:00:00');}
}

final class AdminAccessFake implements AdminAccess{public bool$admin=false;public array$checks=[];public function isAdmin(int$userId):bool{$this->checks[]=$userId;return$this->admin;}}
final class AdminReportFake implements AdminReportReader
{
    public ?SocialReport$report=null;public array$reviews=[];
    public function paginateAdmin(?ReportStatus$status,int$page,int$perPage):SocialReportPage{return new SocialReportPage($this->report===null?[]:[$this->report],$this->report===null?0:1,$page,$perPage);}
    public function findAdmin(int$reportId):?SocialReport{return$this->report;}
    public function review(int$reportId,int$reviewerUserId,ReportStatus$status,?string$moderatorNote):bool{$this->reviews[]=[$reportId,$reviewerUserId,$status,$moderatorNote];return true;}
}
