<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\UsernamePolicy;
use App\Controllers\SocialControlController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Social\PublicFavorite;
use App\Social\PublicProfile;
use App\Social\PublicProfilePage;
use App\Social\PublicProfileReader;
use App\Social\PublicProfileStatistics;
use App\Social\UserSocialControlPage;
use App\Social\UserSocialControlStore;
use PHPUnit\Framework\TestCase;

final class SocialControlControllerTest extends TestCase
{
    private array$previous;private bool$existed;private Session$session;private Auth$auth;private Csrf$csrf;private SocialControlProfileFake$profiles;private SocialControlStoreFake$controls;private SocialControlController$controller;
    protected function setUp():void{$this->existed=isset($_SESSION);$this->previous=$_SESSION??[];$_SESSION=[];$this->session=new Session(false);$this->auth=new Auth($this->session);$this->csrf=new Csrf($this->session);$this->profiles=new SocialControlProfileFake();$this->controls=new SocialControlStoreFake();$this->controller=new SocialControlController(new View(dirname(__DIR__).'/resources/views'),$this->auth,$this->csrf,$this->session,new UsernamePolicy(),$this->profiles,$this->controls);}
    protected function tearDown():void{if($this->existed){$_SESSION=$this->previous;}else{unset($_SESSION);}}
    public function testGuestRedirectsAndInvalidCsrfIsRejected():void{self::assertSame(303,$this->controller->blocked(new Request())->status());$this->auth->login(1);self::assertSame(403,$this->controller->block(new Request(parsedBody:['_token'=>'invalid']),'target.user')->status());}
    public function testSelfControlReturns422():void{$this->auth->login(2);$this->profiles->profile=$this->profile(2,'self.user');$response=$this->controller->block(new Request(parsedBody:['_token'=>$this->csrf->token()]),'self.user');self::assertSame(422,$response->status());self::assertSame([],$this->controls->blocks);}
    public function testBlockMuteAndManagementUsePrgAndUsernameTarget():void{$this->auth->login(1);$this->profiles->profile=$this->profile(2,'target.user');$request=new Request(parsedBody:['_token'=>$this->csrf->token(),'target_user_id'=>'999']);$block=$this->controller->block($request,'target.user');self::assertSame(303,$block->status());self::assertSame('/perfil/bloqueados',$block->headers()['Location']);self::assertSame([[1,2]],$this->controls->blocks);$this->controls->muteAllowed=true;$mute=$this->controller->mute($request,'target.user');self::assertSame('/u/target.user',$mute->headers()['Location']);self::assertSame([[1,2]],$this->controls->mutes);self::assertSame(200,$this->controller->blocked(new Request())->status());self::assertSame(200,$this->controller->muted(new Request())->status());}
    public function testMuteWithoutFollowIsHidden():void{$this->auth->login(1);$this->profiles->profile=$this->profile(2,'target.user');self::assertSame(404,$this->controller->mute(new Request(parsedBody:['_token'=>$this->csrf->token()]),'target.user')->status());}
    private function profile(int$id,string$username):PublicProfile{return new PublicProfile($id,$username,'Target',null,null,null,false,'2026-11-17 10:00:00');}
}

final class SocialControlProfileFake implements PublicProfileReader
{
    public ?PublicProfile$profile=null;public function findByUsername(string$username):?PublicProfile{return$this->profile?->username===$username?$this->profile:null;}public function statisticsForUser(int$userId):PublicProfileStatistics{return new PublicProfileStatistics(0,0,0,null,0,0,0);}public function recentFavorites(int$userId,int$limit=6):array{return[];}public function search(string$query,int$page,int$perPage):PublicProfilePage{return new PublicProfilePage([],0,$page,$perPage);}
}
final class SocialControlStoreFake implements UserSocialControlStore
{
    public array$blocks=[];public array$mutes=[];public bool$muteAllowed=false;public function isBlockedEitherDirection(int$userA,int$userB):bool{return false;}public function isBlockedBy(int$blockerUserId,int$blockedUserId):bool{return false;}public function isMuted(int$userId,int$targetUserId):bool{return false;}public function block(int$blockerUserId,int$blockedUserId):void{$this->blocks[]=[$blockerUserId,$blockedUserId];}public function unblock(int$blockerUserId,int$blockedUserId):void{}public function mute(int$userId,int$targetUserId):bool{$this->mutes[]=[$userId,$targetUserId];return$this->muteAllowed;}public function unmute(int$userId,int$targetUserId):void{}public function paginateBlocked(int$userId,int$page,int$perPage):UserSocialControlPage{return new UserSocialControlPage([],0,$page,$perPage);}public function paginateMuted(int$userId,int$page,int$perPage):UserSocialControlPage{return new UserSocialControlPage([],0,$page,$perPage);}
}
