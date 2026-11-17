<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\UsernamePolicy;
use App\Controllers\DirectMessageController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\CompositeLayoutContextProvider;
use App\Core\LayoutContextProvider;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Messaging\DirectConversation;
use App\Messaging\DirectConversationPage;
use App\Messaging\DirectMessage;
use App\Messaging\DirectMessageBody;
use App\Messaging\DirectMessagePage;
use App\Messaging\DirectMessageReader;
use App\Messaging\DirectMessageStore;
use App\Messaging\MessageLayoutContextProvider;
use App\Social\FollowStore;
use App\Social\PublicProfile;
use App\Social\PublicProfilePage;
use App\Social\PublicProfileReader;
use App\Social\PublicProfileStatistics;
use PHPUnit\Framework\TestCase;

final class DirectMessageControllerTest extends TestCase
{
    private bool $sessionExisted;
    private array $previousSession;
    private Session $session;
    private Auth $auth;
    private Csrf $csrf;
    private DirectMessageProfileReaderFake $profiles;
    private DirectMessageFollowStoreFake $follows;
    private DirectMessageStoreFake $messages;

    protected function setUp(): void
    {
        $this->sessionExisted = isset($_SESSION); $this->previousSession = $_SESSION ?? []; $_SESSION = [];
        $this->session = new Session(false); $this->auth = new Auth($this->session); $this->csrf = new Csrf($this->session);
        $this->profiles = new DirectMessageProfileReaderFake(); $this->follows = new DirectMessageFollowStoreFake(); $this->messages = new DirectMessageStoreFake();
    }

    protected function tearDown(): void
    {
        if ($this->sessionExisted) { $_SESSION = $this->previousSession; } else { unset($_SESSION); }
    }

    public function testInboxAndConversationRequireAuthentication(): void
    {
        self::assertSame('/login', $this->controller()->index(new Request())->headers()['Location']);
        self::assertSame('/login', $this->controller()->show(new Request(), 'other.user')->headers()['Location']);
        self::assertSame('/login', $this->controller()->send(new Request(), 'other.user')->headers()['Location']);
    }

    public function testInvalidCsrfIsRejectedBeforeTargetLookup(): void
    {
        $this->auth->login(7);
        $response = $this->controller()->send(new Request(parsedBody: ['body' => 'oi']), 'missing.user');
        self::assertSame(403, $response->status());
        self::assertSame(0, $this->profiles->findCalls);
    }

    public function testSelfMissingAndNonMutualTargetsAreHidden(): void
    {
        $this->auth->login(7);
        $this->profiles->profile = $this->profile(7, 'self.user');
        self::assertSame(404, $this->controller()->show(new Request(), 'self.user')->status());
        $this->profiles->profile = null;
        self::assertSame(404, $this->controller()->show(new Request(), 'missing.user')->status());
        $this->profiles->profile = $this->profile();
        self::assertSame(404, $this->controller()->show(new Request(), 'other.user')->status());
    }

    public function testValidSendUsesPrgAndEscapesInvalidSubmission(): void
    {
        $this->auth->login(7); $this->profiles->profile = $this->profile(); $this->follows->mutual = true; $this->messages->sendResult = true;
        $invalid = $this->controller()->send($this->post(['body' => '<script>alert(1)</script>' . str_repeat('x', 2000)]), 'other.user');
        self::assertSame(422, $invalid->status());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $invalid->body());
        self::assertStringNotContainsString('<script>alert(1)</script>', $invalid->body());
        $sent = $this->controller()->send($this->post(['body' => " Olá\r\n mundo "]), 'other.user');
        self::assertSame(303, $sent->status()); self::assertSame('/mensagens/com/other.user', $sent->headers()['Location']);
        self::assertSame("Olá\n mundo", $this->messages->sentBody?->value);
    }

    public function testExistingConversationRemainsReadableButCannotSendAfterUnfollow(): void
    {
        $this->auth->login(7); $this->profiles->profile = $this->profile(); $this->messages->exists = true;
        self::assertSame(200, $this->controller()->show(new Request(), 'other.user')->status());
        self::assertSame(404, $this->controller()->send($this->post(['body' => 'mensagem']), 'other.user')->status());
        self::assertNull($this->messages->sentBody);
    }

    public function testGetDoesNotMarkReadAndPostReadDoes(): void
    {
        $this->auth->login(7); $this->profiles->profile = $this->profile(); $this->messages->exists = true; $this->messages->unread = 1;
        $page = $this->controller()->show(new Request(), 'other.user');
        self::assertSame(200, $page->status()); self::assertSame(0, $this->messages->markCalls);
        $this->messages->markResult = true;
        $response = $this->controller()->markRead($this->post(), 'other.user');
        self::assertSame(303, $response->status()); self::assertSame(1, $this->messages->markCalls);
    }

    public function testLayoutContextSkipsGuestsCoexistsAndCapsBadge(): void
    {
        $provider = new MessageLayoutContextProvider($this->auth, $this->messages);
        self::assertSame(['messageUnreadCount' => 0], $provider->context()); self::assertSame(0, $this->messages->countCalls);
        $this->auth->login(7);
        $other = new class implements LayoutContextProvider { public function context(): array { return ['notificationUnreadCount' => 4]; } };
        foreach ([0=>null,1=>'1',99=>'99',100=>'99+'] as $count=>$badge) {
            $this->messages->unread=$count;
            $view=new View(dirname(__DIR__).'/resources/views',new CompositeLayoutContextProvider([$other,$provider]));
            $body=$view->render('pages/404',['title'=>'Test','path'=>'/x','currentRoute'=>null,'auth'=>$this->auth,'csrf'=>$this->csrf]);
            self::assertStringContainsString('class="notification-badge">4', $body);
            if ($badge===null) { self::assertMatchesRegularExpression('/class="notification-action message-action"[^>]*>(?:(?!notification-badge).)*<\/a>/s', $body); }
            else { self::assertMatchesRegularExpression('/class="notification-action message-action"[^>]*>.*?class="notification-badge">'.preg_quote($badge,'/').'<\/strong>/s', $body); }
            if ($count===1) { self::assertStringContainsString('aria-label="Mensagens, 1 não lida"', $body); }
        }
        self::assertSame(4,$this->messages->countCalls);
    }

    private function controller(): DirectMessageController
    {
        return new DirectMessageController(new View(dirname(__DIR__) . '/resources/views'), $this->auth, $this->csrf, new UsernamePolicy(), $this->profiles, $this->follows, $this->messages, $this->messages);
    }

    private function post(array $body = []): Request { return new Request(parsedBody: ['_token' => $this->csrf->token(), ...$body]); }
    private function profile(int $id = 8, string $username = 'other.user'): PublicProfile { return new PublicProfile($id, $username, 'Other <User>', null, null, null, false, '2026-11-17 10:00:00'); }
}

final class DirectMessageProfileReaderFake implements PublicProfileReader
{
    public ?PublicProfile $profile = null; public int $findCalls = 0;
    public function findByUsername(string $username): ?PublicProfile { $this->findCalls++; return $this->profile?->username === $username ? $this->profile : null; }
    public function statisticsForUser(int $userId): PublicProfileStatistics { return new PublicProfileStatistics(0,0,0,null,0,0,0); }
    public function recentFavorites(int $userId, int $limit = 6): array { return []; }
    public function search(string $query, int $page, int $perPage): PublicProfilePage { return new PublicProfilePage([],0,$page,$perPage); }
}

final class DirectMessageFollowStoreFake implements FollowStore
{
    public bool $mutual = false;
    public function follow(int $followerUserId,int $followedUserId):void{} public function unfollow(int $followerUserId,int $followedUserId):void{}
    public function isFollowing(int $followerUserId,int $followedUserId):bool{return false;} public function areMutualFollowers(int $userA,int $userB):bool{return $this->mutual;}
    public function countFollowers(int $userId):int{return 0;} public function countFollowing(int $userId):int{return 0;}
    public function paginateFollowers(int $userId,int $page,int $perPage):PublicProfilePage{return new PublicProfilePage([],0,$page,$perPage);}
    public function paginateFollowing(int $userId,int $page,int $perPage):PublicProfilePage{return new PublicProfilePage([],0,$page,$perPage);}
}

final class DirectMessageStoreFake implements DirectMessageReader, DirectMessageStore
{
    public bool $exists=false; public bool $sendResult=false; public bool $markResult=false; public int $markCalls=0; public int $unread=0; public int $countCalls=0; public ?DirectMessageBody $sentBody=null;
    public function conversationExists(int$userA,int$userB):bool{return $this->exists;}
    public function conversationForUsers(int$currentUserId,int$otherUserId):?DirectConversation{return $this->exists?new DirectConversation(1,min($currentUserId,$otherUserId),max($currentUserId,$otherUserId),'2026-11-17 10:00:00','2026-11-17 10:00:00'):null;}
    public function paginateConversations(int$userId,int$page,int$perPage):DirectConversationPage{return new DirectConversationPage([],0,$page,$perPage);}
    public function paginateMessages(int$currentUserId,int$otherUserId,int$page,int$perPage):DirectMessagePage{return new DirectMessagePage([new DirectMessage(1,1,$otherUserId,'<b>segura</b>',null,'2026-11-17 10:00:00')],1,$page,$perPage);}
    public function countUnreadForUser(int$userId):int{$this->countCalls++;return $this->unread;} public function countUnreadForConversation(int$currentUserId,int$otherUserId):int{return $this->unread;}
    public function send(int$senderUserId,int$otherUserId,DirectMessageBody$body):bool{$this->sentBody=$body;return $this->sendResult;}
    public function markRead(int$currentUserId,int$otherUserId):bool{$this->markCalls++;return $this->markResult;}
}
