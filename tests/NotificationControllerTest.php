<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\NotificationController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Notifications\NotificationLayoutContextProvider;
use App\Notifications\SocialNotification;
use App\Notifications\SocialNotificationPage;
use App\Notifications\SocialNotificationReader;
use App\Notifications\SocialNotificationStore;
use App\Notifications\SocialNotificationType;
use PHPUnit\Framework\TestCase;

final class NotificationControllerTest extends TestCase
{
    private bool$sessionExisted;private array$previousSession;private Session$session;private Auth$auth;
    protected function setUp():void{$this->sessionExisted=isset($_SESSION);$this->previousSession=$_SESSION??[];$_SESSION=[];$this->session=new Session(false);$this->auth=new Auth($this->session);}
    protected function tearDown():void{if($this->sessionExisted){$_SESSION=$this->previousSession;}else{unset($_SESSION);}}

    public function testPrivateRoutesRedirectGuests():void
    {
        $fake=new NotificationFake();$controller=$this->controller($fake);self::assertSame('/login',$controller->index(new Request())->headers()['Location']);self::assertSame('/login',$controller->open(new Request(),'1')->headers()['Location']);self::assertSame('/login',$controller->markAllRead(new Request())->headers()['Location']);self::assertSame(0,$fake->countCalls);
    }

    public function testIndexRendersUnreadReadEscapedAndNoTmdbDependency():void
    {
        $this->auth->login(7);$fake=new NotificationFake();$fake->items=[new SocialNotification(10,SocialNotificationType::ActivityComment,8,'actor','<Actor>',null,31,9,null,'2026-11-17 10:00:00'),new SocialNotification(9,SocialNotificationType::FollowAccepted,8,'actor','Actor',null,null,null,'2026-11-17 09:00:00','2026-11-17 09:00:00')];$fake->unread=1;
        $response=$this->controller($fake)->index(new Request());self::assertSame(200,$response->status());self::assertStringContainsString('&lt;Actor&gt;',$response->body());self::assertStringNotContainsString('<Actor>',$response->body());self::assertStringContainsString('Não lida',$response->body());self::assertStringContainsString('Marcar todas como lidas',$response->body());self::assertStringContainsString('href="/social" aria-current="page"',$response->body());self::assertSame(5,preg_match_all('/<(?:a|span) class="bottom-nav__item/',$response->body()));
    }

    public function testOpenRequiresCsrfOwnershipAndUsesServerDestinationIdempotently():void
    {
        $this->auth->login(7);$fake=new NotificationFake();$fake->found=new SocialNotification(10,SocialNotificationType::ActivityLike,8,'actor','Actor',null,31,null,null,'2026-11-17 10:00:00');$controller=$this->controller($fake);
        self::assertSame(403,$controller->open(new Request(),'10')->status());$csrf=(new Csrf($this->session))->token();self::assertSame(404,$controller->open(new Request(parsedBody:['_token'=>$csrf]),'bad')->status());$fake->markResult=false;self::assertSame(404,$controller->open(new Request(parsedBody:['_token'=>$csrf]),'10')->status());$fake->markResult=true;$response=$controller->open(new Request(parsedBody:['_token'=>$csrf,'returnUrl'=>'https://evil.test']),'10');self::assertSame(303,$response->status());self::assertSame('/social/atividades/31',$response->headers()['Location']);self::assertSame([[7,10],[7,10]],$fake->markCalls);
    }

    public function testMarkAllRequiresCsrfAndUsesPrg():void
    {
        $this->auth->login(7);$fake=new NotificationFake();$controller=$this->controller($fake);self::assertSame(403,$controller->markAllRead(new Request())->status());$csrf=(new Csrf($this->session))->token();$response=$controller->markAllRead(new Request(parsedBody:['_token'=>$csrf]));self::assertSame('/notificacoes',$response->headers()['Location']);self::assertSame([7],$fake->markAllCalls);
    }

    public function testEveryNotificationTypeHasAServerDefinedDestination(): void
    {
        $base = [10, 8, 'aurora', 'Aurora', null];
        self::assertSame('/perfil/solicitacoes', (new SocialNotification($base[0], SocialNotificationType::FollowRequest, $base[1], $base[2], $base[3], $base[4], null, null, null, '2026-11-17 10:00:00'))->destination());
        self::assertSame('/u/aurora', (new SocialNotification($base[0], SocialNotificationType::NewFollower, $base[1], $base[2], $base[3], $base[4], null, null, null, '2026-11-17 10:00:00'))->destination());
        self::assertSame('/u/aurora', (new SocialNotification($base[0], SocialNotificationType::FollowAccepted, $base[1], $base[2], $base[3], $base[4], null, null, null, '2026-11-17 10:00:00'))->destination());
        self::assertSame('/social/atividades/31', (new SocialNotification($base[0], SocialNotificationType::ActivityLike, $base[1], $base[2], $base[3], $base[4], 31, null, null, '2026-11-17 10:00:00'))->destination());
        self::assertSame('/social/atividades/31', (new SocialNotification($base[0], SocialNotificationType::ActivityComment, $base[1], $base[2], $base[3], $base[4], 31, 9, null, '2026-11-17 10:00:00'))->destination());
    }

    public function testOptionalLayoutContextSkipsGuestQueryAndCapsBadge():void
    {
        $fake=new NotificationFake();$provider=new NotificationLayoutContextProvider($this->auth,$fake);self::assertSame(['notificationUnreadCount'=>0],$provider->context());self::assertSame(0,$fake->countCalls);
        $this->auth->login(7);foreach([0=>null,1=>'1',99=>'99',100=>'99+']as$count=>$badge){$fake->unread=$count;$view=new View(dirname(__DIR__).'/resources/views',$provider);$body=$view->render('pages/404',['title'=>'Test','path'=>'/x','currentRoute'=>null,'auth'=>$this->auth,'csrf'=>new Csrf($this->session)]);if($badge===null){self::assertStringNotContainsString('notification-badge',$body);}else{self::assertStringContainsString('class="notification-badge">'.$badge,$body);}if($count===1){self::assertStringContainsString('aria-label="Notificações, 1 não lida"',$body);}}
        self::assertSame(4,$fake->countCalls);self::assertInstanceOf(View::class,new View(dirname(__DIR__).'/resources/views'));
    }

    private function controller(NotificationFake$fake):NotificationController{return new NotificationController(new View(dirname(__DIR__).'/resources/views'),$this->auth,new Csrf($this->session),$fake,$fake);}
}

final class NotificationFake implements SocialNotificationReader,SocialNotificationStore
{
    public array$items=[];public int$unread=0;public int$countCalls=0;public ?SocialNotification$found=null;public bool$markResult=true;public array$markCalls=[];public array$markAllCalls=[];
    public function paginateForUser(int$userId,int$page,int$perPage):SocialNotificationPage{return new SocialNotificationPage($this->items,count($this->items),$page,$perPage);}
    public function countUnread(int$userId):int{$this->countCalls++;return$this->unread;}
    public function findForRecipient(int$recipientUserId,int$notificationId):?SocialNotification{return$this->found;}
    public function markRead(int$recipientUserId,int$notificationId):bool{$this->markCalls[]=[$recipientUserId,$notificationId];return$this->markResult;}
    public function markAllRead(int$recipientUserId):void{$this->markAllCalls[]=$recipientUserId;}
    public function followRequest(int$recipientUserId,int$actorUserId):void{}public function removeFollowRequest(int$recipientUserId,int$actorUserId):void{}public function followAccepted(int$recipientUserId,int$actorUserId):void{}public function followAcceptedForPendingTarget(int$targetUserId):void{}public function removeFollowRequestsForTarget(int$targetUserId):void{}public function newFollower(int$recipientUserId,int$actorUserId):void{}public function activityLike(int$historyId,int$actorUserId):void{}public function removeActivityLike(int$historyId,int$actorUserId):void{}public function activityComment(int$historyId,int$commentId,int$actorUserId):void{}
}
