<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Moderation\ReportCreateResult;
use App\Moderation\ReportReason;
use App\Moderation\ReportStatus;
use App\Moderation\ReportSubmission;
use App\Moderation\ReportTarget;
use App\Moderation\ReportTargetType;
use App\Repositories\SocialReportRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

final class SocialReportRepositoryDatabaseTest extends TestCase
{
    private Database$database;private SocialReportRepository$reports;private UserRepository$users;private UserProfileRepository$profiles;
    protected function setUp():void{$name=getenv('FLICKARY_TEST_DB_DATABASE');if(!is_string($name)||!str_ends_with($name,'_test')){self::markTestSkipped('Disposable Flickary database was not configured.');}$this->database=new Database(['host'=>getenv('FLICKARY_TEST_DB_HOST')?:'127.0.0.1','port'=>(int)(getenv('FLICKARY_TEST_DB_PORT')?:3306),'database'=>$name,'username'=>getenv('FLICKARY_TEST_DB_USERNAME')?:'','password'=>getenv('FLICKARY_TEST_DB_PASSWORD')?:'','charset'=>'utf8mb4']);$this->database->connection()->exec('DELETE FROM users');$this->reports=new SocialReportRepository($this->database);$this->users=new UserRepository($this->database);$this->profiles=new UserProfileRepository($this->database);}
    protected function tearDown():void{if(isset($this->database)){$this->database->connection()->exec('DELETE FROM users');}}

    public function testDuplicatePendingReviewAndRereportLifecycle():void
    {
        $a=$this->user('reporter.a');$b=$this->user('reported.b');$admin=$this->user('admin.user');$target=$this->profileTarget($b,'reported.b','Original bio');$submission=new ReportSubmission($a,$target,ReportReason::Harassment,'Details');
        self::assertSame(ReportCreateResult::Created,$this->reports->create($submission));self::assertSame(ReportCreateResult::Duplicate,$this->reports->create($submission));self::assertSame(1,$this->reports->paginateOwn($a,1,30)->total);
        $report=$this->reports->paginateAdmin(ReportStatus::Pending,1,50)->items[0];self::assertSame('Original bio',$report->contentSnapshot);self::assertTrue($this->reports->review($report->id,$admin,ReportStatus::Resolved,'Reviewed'));
        self::assertFalse($this->reports->review($report->id,$admin,ReportStatus::Dismissed,null));$closed=$this->reports->findAdmin($report->id);self::assertSame(ReportStatus::Resolved,$closed?->status);self::assertSame($admin,$closed?->reviewedByUserId);self::assertNotNull($closed?->reviewedAt);self::assertSame('Reviewed',$closed?->moderatorNote);
        self::assertSame(ReportCreateResult::Created,$this->reports->create($submission));self::assertSame(2,$this->reports->paginateOwn($a,1,30)->total);
    }

    public function testCommentAccessSnapshotsAndOwnerBlockException():void
    {
        $owner=$this->user('owner.user');$author=$this->user('author.user');$follower=$this->user('follower.user');$pdo=$this->database->connection();
        $pdo->prepare('UPDATE user_profiles SET share_watch_activity=1,share_watch_activity_since=DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 1 DAY) WHERE user_id=?')->execute([$owner]);
        $pdo->prepare('INSERT INTO user_follows(follower_user_id,followed_user_id) VALUES (?,?)')->execute([$follower,$owner]);
        $pdo->prepare("INSERT INTO watch_history(user_id,source,entry_type,source_id,title,watched_on,request_key) VALUES (?,'tmdb','movie',1,'Film',CURRENT_DATE,?)")->execute([$owner,str_repeat('r',32)]);$history=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO social_activity_comments(history_id,user_id,body) VALUES (?,?,'Original <script>')")->execute([$history,$author]);$comment=(int)$pdo->lastInsertId();
        self::assertSame('Original <script>',$this->reports->reportableComment($follower,$comment)?->contentSnapshot);
        $pdo->prepare('INSERT INTO user_blocks(blocker_user_id,blocked_user_id) VALUES (?,?),(?,?)')->execute([$owner,$author,$follower,$author]);
        self::assertNotNull($this->reports->reportableComment($owner,$comment));self::assertNull($this->reports->reportableComment($follower,$comment));
        $target=$this->reports->reportableComment($owner,$comment);self::assertSame(ReportCreateResult::Created,$this->reports->create(new ReportSubmission($owner,$target,ReportReason::Inappropriate,null)));
        $pdo->prepare('DELETE FROM social_activity_comments WHERE id=?')->execute([$comment]);self::assertSame('Original <script>',$this->reports->paginateOwn($owner,1,30)->items[0]->contentSnapshot);
    }

    public function testMessageRequiresParticipantAndBlockedHistoryRemainsReportable():void
    {
        $a=$this->user('message.a');$b=$this->user('message.b');$c=$this->user('message.c');$pdo=$this->database->connection();
        $pdo->prepare('INSERT INTO direct_conversations(user_low_id,user_high_id) VALUES (?,?)')->execute([min($a,$b),max($a,$b)]);$conversation=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO direct_messages(conversation_id,sender_user_id,body) VALUES (?,?,'Private <img>')")->execute([$conversation,$b]);$message=(int)$pdo->lastInsertId();
        self::assertNull($this->reports->reportableMessage($c,$message));self::assertSame($b,$this->reports->reportableMessage($a,$message)?->reportedUserId);
        $pdo->prepare('INSERT INTO user_blocks(blocker_user_id,blocked_user_id) VALUES (?,?)')->execute([$a,$b]);self::assertNotNull($this->reports->reportableMessage($a,$message));
    }

    public function testReportedDeletionPreservesSnapshotAndOwnershipIsolation():void
    {
        $a=$this->user('owner.a');$other=$this->user('owner.other');$b=$this->user('deleted.b');
        self::assertSame(ReportCreateResult::Created,$this->reports->create(new ReportSubmission($a,$this->profileTarget($b,'deleted.b','Frozen'),ReportReason::Privacy,null)));
        self::assertSame(0,$this->reports->paginateOwn($other,1,30)->total);$this->database->connection()->prepare('DELETE FROM users WHERE id=?')->execute([$b]);
        $report=$this->reports->paginateOwn($a,1,30)->items[0];self::assertNull($report->reportedUserId);self::assertSame('deleted.b',$report->targetUsername);self::assertSame('Frozen',$report->contentSnapshot);
    }

    public function testRateLimitCountsOnlyCreatedReports():void
    {
        $a=$this->user('rate.owner');$b=$this->user('rate.target');
        for($i=1;$i<=20;$i++){self::assertSame(ReportCreateResult::Created,$this->reports->create(new ReportSubmission($a,new ReportTarget(ReportTargetType::Profile,$i,$b,'rate.target','Rate',null,null),ReportReason::Spam,null)));}
        self::assertSame(ReportCreateResult::Duplicate,$this->reports->create(new ReportSubmission($a,new ReportTarget(ReportTargetType::Profile,20,$b,'rate.target','Rate',null,null),ReportReason::Spam,null)));
        self::assertSame(ReportCreateResult::RateLimited,$this->reports->create(new ReportSubmission($a,new ReportTarget(ReportTargetType::Profile,21,$b,'rate.target','Rate',null,null),ReportReason::Spam,null)));self::assertSame(20,$this->reports->paginateOwn($a,1,30)->total);
    }

    public function testAdminQueueOrderingAndNoNotificationSideEffect():void
    {
        $a=$this->user('queue.a');$b=$this->user('queue.b');
        foreach([3,1,2]as$id){$this->reports->create(new ReportSubmission($a,new ReportTarget(ReportTargetType::Profile,$id,$b,'queue.b','Queue',null,null),ReportReason::Spam,null));}
        $items=$this->reports->paginateAdmin(ReportStatus::Pending,1,50)->items;self::assertSame([3,1,2],array_map(static fn($r)=>$r->targetId,$items));self::assertSame(0,(int)$this->database->connection()->query('SELECT COUNT(*) FROM social_notifications')->fetchColumn());
    }

    private function user(string$username):int{$id=$this->users->create($username,null);$this->profiles->create($id,$username,null);return$id;}
    private function profileTarget(int$id,string$username,?string$bio):ReportTarget{return new ReportTarget(ReportTargetType::Profile,$id,$id,$username,$username,null,$bio);}
}
