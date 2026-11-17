<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\UsernamePolicy;
use App\Controllers\ReportController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Moderation\AdminReportReader;
use App\Moderation\ReportCreateResult;
use App\Moderation\ReportReader;
use App\Moderation\ReportStatus;
use App\Moderation\ReportStore;
use App\Moderation\ReportSubmission;
use App\Moderation\ReportTarget;
use App\Moderation\ReportTargetType;
use App\Moderation\SocialReport;
use App\Moderation\SocialReportPage;
use App\Social\PublicFavorite;
use App\Social\PublicProfile;
use App\Social\PublicProfilePage;
use App\Social\PublicProfileReader;
use App\Social\PublicProfileStatistics;
use PHPUnit\Framework\TestCase;

final class ReportControllerTest extends TestCase
{
    private bool $existed;
    private array $previous;
    private Session $session;
    private Auth $auth;
    private Csrf $csrf;
    private ReportStoreFake $reports;
    private ReportProfileFake $profiles;

    protected function setUp(): void
    {
        $this->existed=isset($_SESSION);$this->previous=$_SESSION??[];$_SESSION=[];
        $this->session=new Session(false);$this->auth=new Auth($this->session);$this->csrf=new Csrf($this->session);
        $this->reports=new ReportStoreFake();$this->profiles=new ReportProfileFake();
    }

    protected function tearDown(): void { if($this->existed){$_SESSION=$this->previous;}else{unset($_SESSION);} }

    public function testGuestRedirectsAndCsrfIsRequired(): void
    {
        self::assertSame(303,$this->controller()->profileForm('target.user')->status());
        $this->auth->login(1);$this->profiles->profile=$this->profile();
        self::assertSame(403,$this->controller()->profileSubmit(new Request(),'target.user')->status());
        self::assertSame([],$this->reports->submissions);
    }

    public function testProfileTargetIsResolvedServerSideAndSelfIsRejected(): void
    {
        $this->auth->login(2);$this->profiles->profile=$this->profile(2,'self.user');
        self::assertSame(422,$this->controller()->profileForm('self.user')->status());
        $this->profiles->profile=$this->profile(3,'target.user');
        $response=$this->controller()->profileSubmit($this->post(['reason'=>'spam','reported_user_id'=>'999','content_snapshot'=>'forged']),'target.user');
        self::assertSame(303,$response->status());
        $submission=$this->reports->submissions[0];self::assertSame(2,$submission->reporterUserId);self::assertSame(3,$submission->target->reportedUserId);self::assertSame('<bio>', $submission->target->contentSnapshot);
    }

    public function testOtherRequiresDetailsAndRenderingEscapesText(): void
    {
        $this->auth->login(1);$this->profiles->profile=$this->profile();
        $response=$this->controller()->profileSubmit($this->post(['reason'=>'other','details'=>'<script>']),'target.user');
        self::assertSame(422,$response->status());self::assertStringContainsString('&lt;script&gt;',$response->body());self::assertStringNotContainsString('<script>',$response->body());
    }

    public function testCommentAndMessageAuthorizationComeFromStoreAndOwnContentIsRejected(): void
    {
        $this->auth->login(1);
        self::assertSame(404,$this->controller()->commentForm('9')->status());
        $this->reports->comment=$this->target(ReportTargetType::Comment,9,2);
        self::assertSame(200,$this->controller()->commentForm('9')->status());
        $this->reports->message=$this->target(ReportTargetType::DirectMessage,10,1);
        self::assertSame(422,$this->controller()->messageForm('10')->status());
    }

    public function testDuplicateAndRateLimitHaveControlledBehavior(): void
    {
        $this->auth->login(1);$this->profiles->profile=$this->profile();
        $this->reports->result=ReportCreateResult::Duplicate;
        self::assertSame(303,$this->controller()->profileSubmit($this->post(['reason'=>'privacy']),'target.user')->status());
        self::assertStringContainsString('já enviou',$this->controller()->own(new Request())->body());
        $this->reports->result=ReportCreateResult::RateLimited;
        self::assertSame(429,$this->controller()->profileSubmit($this->post(['reason'=>'spam']),'target.user')->status());
    }

    private function controller(): ReportController { return new ReportController(new View(dirname(__DIR__).'/resources/views'),$this->auth,$this->csrf,$this->session,new UsernamePolicy(),$this->profiles,$this->reports,$this->reports); }
    private function post(array $data): Request { return new Request(parsedBody:['_token'=>$this->csrf->token(),...$data]); }
    private function profile(int$id=2,string$username='target.user'): PublicProfile { return new PublicProfile($id,$username,'<Target>','<bio>',null,null,true,'2026-11-17 10:00:00'); }
    private function target(ReportTargetType$type,int$id,int$user): ReportTarget { return new ReportTarget($type,$id,$user,'target.user','<Target>',null,'<script>alert(1)</script>'); }
}

final class ReportProfileFake implements PublicProfileReader
{
    public ?PublicProfile $profile=null;
    public function findByUsername(string$username):?PublicProfile{return$this->profile?->username===$username?$this->profile:null;}
    public function statisticsForUser(int$userId):PublicProfileStatistics{return new PublicProfileStatistics(0,0,0,null,0,0,0);}
    public function recentFavorites(int$userId,int$limit=6):array{return[];}
    public function search(string$query,int$page,int$perPage):PublicProfilePage{return new PublicProfilePage([],0,$page,$perPage);}
}

final class ReportStoreFake implements ReportStore, ReportReader
{
    public ?ReportTarget$comment=null;public ?ReportTarget$message=null;public ReportCreateResult$result=ReportCreateResult::Created;public array$submissions=[];
    public function reportableComment(int$viewerUserId,int$commentId):?ReportTarget{return$this->comment;}
    public function reportableMessage(int$viewerUserId,int$messageId):?ReportTarget{return$this->message;}
    public function create(ReportSubmission$submission):ReportCreateResult{$this->submissions[]=$submission;return$this->result;}
    public function paginateOwn(int$reporterUserId,int$page,int$perPage):SocialReportPage{return new SocialReportPage([],0,$page,$perPage);}
}
