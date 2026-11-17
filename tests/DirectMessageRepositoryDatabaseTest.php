<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Messaging\DirectMessageBody;
use App\Repositories\DirectMessageRepository;
use App\Repositories\UserFollowRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

final class DirectMessageRepositoryDatabaseTest extends TestCase
{
    private Database $database; private UserRepository $users; private UserProfileRepository $profiles; private UserFollowRepository $follows; private DirectMessageRepository $messages;

    protected function setUp(): void
    {
        $name = getenv('FLICKARY_TEST_DB_DATABASE'); if (!is_string($name) || !str_ends_with($name, '_test')) { self::markTestSkipped('Disposable Flickary database was not configured.'); }
        $this->database = new Database(['host'=>getenv('FLICKARY_TEST_DB_HOST')?:'127.0.0.1','port'=>(int)(getenv('FLICKARY_TEST_DB_PORT')?:3306),'database'=>$name,'username'=>getenv('FLICKARY_TEST_DB_USERNAME')?:'','password'=>getenv('FLICKARY_TEST_DB_PASSWORD')?:'','charset'=>'utf8mb4']);
        $this->users = new UserRepository($this->database); $this->profiles = new UserProfileRepository($this->database); $this->follows = new UserFollowRepository($this->database); $this->messages = new DirectMessageRepository($this->database); $this->clear();
    }

    protected function tearDown(): void { if (isset($this->database)) { $this->clear(); } }

    public function testMutualFollowCreatesOneCanonicalConversationAndPreventsDuplicates(): void
    {
        $high = $this->user('message.high'); $low = $this->user('message.low');
        [$body] = DirectMessageBody::validate('primeira'); self::assertFalse($this->messages->send($high, $low, $body));
        $this->database->connection()->prepare('INSERT INTO user_follow_requests(requester_user_id,target_user_id) VALUES (?,?)')->execute([$high,$low]);
        self::assertFalse($this->messages->send($high,$low,$body));
        $this->follows->follow($high, $low); self::assertFalse($this->messages->send($high, $low, $body));
        $this->follows->follow($low, $high); self::assertTrue($this->follows->areMutualFollowers($high, $low));
        self::assertTrue($this->messages->send($high, $low, $body)); [$reply] = DirectMessageBody::validate('resposta'); self::assertTrue($this->messages->send($low, $high, $reply));
        $conversation = $this->messages->conversationForUsers($high, $low); self::assertNotNull($conversation);
        self::assertSame(min($low, $high), $conversation->userLowId); self::assertSame(max($low, $high), $conversation->userHighId);
        self::assertSame(1, $this->countRows('direct_conversations')); self::assertSame(2, $this->countRows('direct_messages'));
    }

    public function testUnreadInboxPaginationReadLifecycleAndParticipantIsolation(): void
    {
        $viewer = $this->user('message.viewer'); $other = $this->user('message.other'); $third = $this->user('message.third'); $this->mutual($viewer, $other);
        for ($i=1; $i<=55; $i++) { [$body] = DirectMessageBody::validate('message ' . $i); self::assertTrue($this->messages->send($i % 2 === 0 ? $viewer : $other, $i % 2 === 0 ? $other : $viewer, $body)); }
        self::assertSame(28, $this->messages->countUnreadForUser($viewer)); self::assertSame(28, $this->messages->countUnreadForConversation($viewer, $other));
        $inbox = $this->messages->paginateConversations($viewer, 1, 30); self::assertSame(1, $inbox->total); self::assertSame('message 55', $inbox->items[0]->latestMessage); self::assertSame(28, $inbox->items[0]->unreadCount); self::assertTrue($inbox->items[0]->canSendNow);
        $newest = $this->messages->paginateMessages($viewer, $other, 1, 50); self::assertSame(55, $newest->total); self::assertCount(50, $newest->items); self::assertSame('message 6', $newest->items[0]->body); self::assertSame('message 55', $newest->items[49]->body);
        $older = $this->messages->paginateMessages($viewer, $other, 2, 50); self::assertCount(5, $older->items); self::assertSame('message 1', $older->items[0]->body); self::assertSame('message 5', $older->items[4]->body);
        self::assertFalse($this->messages->conversationExists($viewer, $third)); self::assertSame([], $this->messages->paginateMessages($viewer, $third, 1, 50)->items);
        self::assertTrue($this->messages->markRead($viewer, $other)); self::assertSame(0, $this->messages->countUnreadForUser($viewer)); self::assertSame(27, $this->messages->countUnreadForUser($other));
        $readAt = $this->database->connection()->query("SELECT MIN(read_at) FROM direct_messages WHERE sender_user_id={$other}")->fetchColumn(); self::assertNotFalse($readAt);
        self::assertTrue($this->messages->markRead($viewer, $other)); self::assertSame($readAt, $this->database->connection()->query("SELECT MIN(read_at) FROM direct_messages WHERE sender_user_id={$other}")->fetchColumn());
    }

    public function testUnfollowMakesConversationReadOnlyAndRefollowReusesIt(): void
    {
        $a=$this->user('message.a');$b=$this->user('message.b');$this->mutual($a,$b);[$body]=DirectMessageBody::validate('before');self::assertTrue($this->messages->send($a,$b,$body));$id=$this->messages->conversationForUsers($a,$b)?->id;
        $this->follows->unfollow($a,$b);self::assertTrue($this->messages->conversationExists($a,$b));self::assertFalse($this->messages->send($b,$a,$body));self::assertFalse($this->messages->paginateConversations($a,1,30)->items[0]->canSendNow);
        $this->follows->follow($a,$b);self::assertTrue($this->messages->send($b,$a,$body));self::assertSame($id,$this->messages->conversationForUsers($a,$b)?->id);self::assertSame(1,$this->countRows('direct_conversations'));
        $this->database->connection()->prepare('DELETE FROM users WHERE id=?')->execute([$a]);self::assertSame(0,$this->countRows('direct_conversations'));self::assertSame(0,$this->countRows('direct_messages'));
    }

    public function testInboxPaginatesThirtyConversationsAndReadIsConversationScoped():void
    {
        $viewer=$this->user('message.page.viewer');$others=[];
        for($i=1;$i<=31;$i++){$other=$this->user('message.page.'.str_pad((string)$i,2,'0',STR_PAD_LEFT));$others[]=$other;$this->mutual($viewer,$other);[$body]=DirectMessageBody::validate('conversation '.$i);self::assertTrue($this->messages->send($other,$viewer,$body));}
        self::assertSame(31,$this->messages->paginateConversations($viewer,1,30)->total);self::assertCount(30,$this->messages->paginateConversations($viewer,1,30)->items);self::assertCount(1,$this->messages->paginateConversations($viewer,2,30)->items);
        foreach([1,2]as$i){[$body]=DirectMessageBody::validate('extra a '.$i);$this->messages->send($others[0],$viewer,$body);}foreach([1,2,3]as$i){[$body]=DirectMessageBody::validate('extra b '.$i);$this->messages->send($others[1],$viewer,$body);}
        self::assertSame(36,$this->messages->countUnreadForUser($viewer));self::assertSame(3,$this->messages->countUnreadForConversation($viewer,$others[0]));self::assertSame(4,$this->messages->countUnreadForConversation($viewer,$others[1]));
        self::assertTrue($this->messages->markRead($viewer,$others[0]));self::assertSame(33,$this->messages->countUnreadForUser($viewer));self::assertSame(4,$this->messages->countUnreadForConversation($viewer,$others[1]));
    }

    private function user(string$username):int{$id=$this->users->create($username,null);$this->profiles->create($id,$username,null);return$id;}
    private function mutual(int$a,int$b):void{$this->follows->follow($a,$b);$this->follows->follow($b,$a);}
    private function countRows(string$table):int{if(!in_array($table,['direct_conversations','direct_messages'],true)){throw new \InvalidArgumentException();}return(int)$this->database->connection()->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();}
    private function clear():void{$pdo=$this->database->connection();$pdo->exec('DELETE FROM direct_messages');$pdo->exec('DELETE FROM direct_conversations');$pdo->exec('DELETE FROM social_notifications');$pdo->exec('DELETE FROM user_follow_requests');$pdo->exec('DELETE FROM user_follows');$pdo->exec('DELETE FROM user_external_identities');$pdo->exec('DELETE FROM user_profiles');$pdo->exec('DELETE FROM users');}
}
