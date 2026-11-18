<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Moderation\ModerationActionResult;
use App\Moderation\ModerationActionService;
use App\Moderation\ModerationActionType;
use App\Moderation\ModerationNote;
use App\Moderation\ReportStatus;
use App\Moderation\SuspensionDuration;
use App\Repositories\AccountAccessRepository;
use App\Repositories\ModerationActionRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ModerationActionServiceDatabaseTest extends TestCase
{
    private Database$db;private ModerationActionService$service;private int$admin;private int$target;private int$reporter;
    protected function setUp():void
    {
        $name=getenv('FLICKARY_TEST_DB_DATABASE');if(!is_string($name)||!str_ends_with($name,'_test'))self::markTestSkipped('Disposable Flickary database was not configured.');
        $this->db=new Database(['host'=>getenv('FLICKARY_TEST_DB_HOST')?:'127.0.0.1','port'=>(int)(getenv('FLICKARY_TEST_DB_PORT')?:3306),'database'=>$name,'username'=>getenv('FLICKARY_TEST_DB_USERNAME')?:'','password'=>getenv('FLICKARY_TEST_DB_PASSWORD')?:'','charset'=>'utf8mb4']);$pdo=$this->db->connection();$pdo->exec('DELETE FROM moderation_actions');$pdo->exec('DELETE FROM users');
        $this->admin=$this->user('mod.admin','Moderator');$this->target=$this->user('mod.target','Target');$this->reporter=$this->user('mod.reporter','Reporter');$pdo->prepare('UPDATE users SET is_admin=1 WHERE id=?')->execute([$this->admin]);$this->service=new ModerationActionService($this->db);
    }
    protected function tearDown():void{if(isset($this->db)){$pdo=$this->db->connection();$pdo->exec('DROP TRIGGER IF EXISTS moderation_action_test_failure');$pdo->exec('DELETE FROM moderation_actions');$pdo->exec('DELETE FROM users');}}

    public function testAllowedDurationsExtensionAuditReportResolutionAndAccessExpiry():void
    {
        $pdo=$this->db->connection();$previous=null;
        foreach(SuspensionDuration::cases()as$duration){$report=$this->report($this->target,'profile',$this->target,'Profile snapshot');self::assertSame(ModerationActionResult::Applied,$this->service->suspendFromReport($report,$this->admin,$duration,$this->note('Temporary suspension justified.')));$action=$pdo->query('SELECT * FROM moderation_actions ORDER BY id DESC LIMIT 1')->fetch();self::assertSame(ModerationActionType::SuspendUser->value,$action['action_type']);self::assertSame($duration->minutes(),(int)$action['duration_minutes']);self::assertSame($previous,$action['previous_suspended_until']);if($previous!==null)self::assertSame($duration->minutes(),(int)$pdo->query("SELECT TIMESTAMPDIFF(MINUTE,'{$previous}','{$action['effective_until']}')")->fetchColumn());$previous=$action['effective_until'];$review=$pdo->query("SELECT status,reviewed_by_user_id,dedupe_key FROM social_reports WHERE id={$report}")->fetch();self::assertSame(ReportStatus::Resolved->value,$review['status']);self::assertSame($this->admin,(int)$review['reviewed_by_user_id']);self::assertNull($review['dedupe_key']);}
        self::assertNotNull((new AccountAccessRepository($this->db))->activeSuspensionUntil($this->target));
        $pdo->prepare('UPDATE users SET suspended_until=DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 1 MINUTE) WHERE id=?')->execute([$this->target]);self::assertNull((new AccountAccessRepository($this->db))->activeSuspensionUntil($this->target));
    }

    public function testSelfAndOtherAdminSuspensionAreBlocked():void
    {
        $otherAdmin=$this->user('other.admin','Other Admin');$this->db->connection()->prepare('UPDATE users SET is_admin=1 WHERE id=?')->execute([$otherAdmin]);
        self::assertSame(ModerationActionResult::ProtectedTarget,$this->service->suspendFromReport($this->report($this->admin),$this->admin,SuspensionDuration::OneDay,$this->note('Self suspension is forbidden.')));
        self::assertSame(ModerationActionResult::ProtectedTarget,$this->service->suspendFromReport($this->report($otherAdmin),$this->admin,SuspensionDuration::OneDay,$this->note('Admin suspension is forbidden.')));
        self::assertSame(0,(int)$this->db->connection()->query('SELECT COUNT(*) FROM moderation_actions')->fetchColumn());
    }

    public function testEarlyUnsuspendIsAuditedWithoutChangingResolvedReport():void
    {
        $report=$this->report($this->target);$this->service->suspendFromReport($report,$this->admin,SuspensionDuration::SevenDays,$this->note('Suspend before early reactivation.'));
        self::assertSame(ModerationActionResult::Applied,$this->service->unsuspend('mod.target',$this->admin,$this->note('Decision corrected after review.')));$pdo=$this->db->connection();
        self::assertNull($pdo->query("SELECT suspended_until FROM users WHERE id={$this->target}")->fetchColumn());self::assertSame(ModerationActionType::UnsuspendUser->value,$pdo->query('SELECT action_type FROM moderation_actions ORDER BY id DESC LIMIT 1')->fetchColumn());self::assertSame(ReportStatus::Resolved->value,$pdo->query("SELECT status FROM social_reports WHERE id={$report}")->fetchColumn());
    }

    public function testCommentRemovalCascadesNotificationPreservesSnapshotAndAudit():void
    {
        [$comment,$notification]=$this->commentFixture();$report=$this->report($this->target,'comment',$comment,'Frozen <script>comment</script>');
        self::assertSame(ModerationActionResult::Applied,$this->service->removeReportedComment($report,$this->admin,$this->note('Reported comment violates community rules.')));$pdo=$this->db->connection();
        self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM social_activity_comments WHERE id={$comment}")->fetchColumn());self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM social_notifications WHERE id={$notification}")->fetchColumn());
        $action=$pdo->query('SELECT action_type,content_snapshot FROM moderation_actions ORDER BY id DESC LIMIT 1')->fetch();self::assertSame(ModerationActionType::RemoveComment->value,$action['action_type']);self::assertSame('Frozen <script>comment</script>',$action['content_snapshot']);self::assertSame(ReportStatus::Resolved->value,$pdo->query("SELECT status FROM social_reports WHERE id={$report}")->fetchColumn());
    }

    public function testWrongTypeMissingCommentAndTransactionalFailuresLeaveStateUntouched():void
    {
        $profile=$this->report($this->target);self::assertSame(ModerationActionResult::NotFound,$this->service->removeReportedComment($profile,$this->admin,$this->note('Wrong target must stay untouched.')));
        $missing=$this->report($this->target,'comment',999999,'Missing');self::assertSame(ModerationActionResult::NotFound,$this->service->removeReportedComment($missing,$this->admin,$this->note('Missing comment stays pending.')));
        [$comment]=$this->commentFixture();$commentReport=$this->report($this->target,'comment',$comment,'Rollback snapshot');$pdo=$this->db->connection();$pdo->exec("CREATE TRIGGER moderation_action_test_failure BEFORE INSERT ON moderation_actions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced rollback'");
        try{$this->service->removeReportedComment($commentReport,$this->admin,$this->note('Force transactional rollback now.'));self::fail('Expected forced rollback.');}catch(RuntimeException $e){self::assertStringContainsString('forced rollback',$e->getMessage());}finally{$pdo->exec('DROP TRIGGER IF EXISTS moderation_action_test_failure');}
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM social_activity_comments WHERE id={$comment}")->fetchColumn());self::assertSame(ReportStatus::Pending->value,$pdo->query("SELECT status FROM social_reports WHERE id={$commentReport}")->fetchColumn());
        $suspendReport=$this->report($this->target);$pdo->exec("CREATE TRIGGER moderation_action_test_failure BEFORE INSERT ON moderation_actions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced rollback'");
        try{$this->service->suspendFromReport($suspendReport,$this->admin,SuspensionDuration::OneDay,$this->note('Force suspension rollback now.'));self::fail('Expected forced rollback.');}catch(RuntimeException $e){self::assertStringContainsString('forced rollback',$e->getMessage());}finally{$pdo->exec('DROP TRIGGER IF EXISTS moderation_action_test_failure');}
        self::assertNull($pdo->query("SELECT suspended_until FROM users WHERE id={$this->target}")->fetchColumn());self::assertSame(ReportStatus::Pending->value,$pdo->query("SELECT status FROM social_reports WHERE id={$suspendReport}")->fetchColumn());
    }

    public function testAuditPaginationOrderingSnapshotsAndDeletedTargets():void
    {
        $report=$this->report($this->target);$this->service->suspendFromReport($report,$this->admin,SuspensionDuration::OneDay,$this->note('Audit snapshot must remain available.'));$repository=new ModerationActionRepository($this->db);$page=$repository->paginateAll(1,50);self::assertSame(1,$page->total);self::assertSame('mod.target',$page->items[0]->targetUsername);self::assertSame('mod.admin',$page->items[0]->performedByUsername);self::assertSame(1,$repository->paginateForUser($this->target,1,30)->total);
        $this->db->connection()->prepare('DELETE FROM users WHERE id=?')->execute([$this->target]);$action=$repository->paginateAll(1,50)->items[0];self::assertNull($action->targetUserId);self::assertSame('mod.target',$action->targetUsername);
    }

    private function user(string$username,string$display):int{$users=new UserRepository($this->db);$id=$users->create($username,null);(new UserProfileRepository($this->db))->create($id,$display,null);return$id;}
    private function report(int$target, string$type='profile',?int$targetId=null,?string$content='Snapshot'):int{$pdo=$this->db->connection();$user=$pdo->query("SELECT username FROM users WHERE id={$target}")->fetchColumn()?:'deleted';$statement=$pdo->prepare("INSERT INTO social_reports(reporter_user_id,reported_user_id,target_type,target_id,reason,target_username_snapshot,target_display_name_snapshot,content_snapshot,dedupe_key) VALUES (?,?,?,?,'spam',?,?,?,?)");$targetId??=$target;$statement->execute([$this->reporter,$target,$type,$targetId,$user,'Target',$content,$type.':'.$targetId.':'.bin2hex(random_bytes(3))]);return(int)$pdo->lastInsertId();}
    private function note(string$value):ModerationNote{[$note,$error]=ModerationNote::validate($value);self::assertNull($error);self::assertNotNull($note);return$note;}
    /** @return array{int,int} */private function commentFixture():array{$pdo=$this->db->connection();$pdo->prepare("INSERT INTO watch_history(user_id,source,entry_type,source_id,title,watched_on,request_key) VALUES (?,'tmdb','movie',603,'Matrix',CURRENT_DATE,?)")->execute([$this->reporter,bin2hex(random_bytes(16))]);$history=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO social_activity_comments(history_id,user_id,body) VALUES (?,?,'Live comment')")->execute([$history,$this->target]);$comment=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO social_notifications(recipient_user_id,actor_user_id,type,history_id,comment_id) VALUES (?,?,'activity_comment',?,?)")->execute([$this->reporter,$this->target,$history,$comment]);return[$comment,(int)$pdo->lastInsertId()];}
}
