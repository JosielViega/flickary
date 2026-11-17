<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\UsernamePolicy;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Social\PublicProfile;
use App\Social\PublicProfileReader;
use App\Social\UserSocialControlStore;

final class SocialControlController
{
    private const PER_PAGE=30;
    public function __construct(private readonly View $view,private readonly Auth $auth,private readonly Csrf $csrf,private readonly Session $session,private readonly UsernamePolicy $usernames,private readonly PublicProfileReader $profiles,private readonly UserSocialControlStore $controls){}

    public function blocked(Request$request):Response{return $this->page($request,true);}
    public function muted(Request$request):Response{return $this->page($request,false);}
    public function block(Request$request,string$username):Response{return $this->mutate($request,$username,'block');}
    public function unblock(Request$request,string$username):Response{return $this->mutate($request,$username,'unblock');}
    public function mute(Request$request,string$username):Response{return $this->mutate($request,$username,'mute');}
    public function unmute(Request$request,string$username):Response{return $this->mutate($request,$username,'unmute');}

    private function page(Request$request,bool$blocked):Response
    {
        $userId=$this->auth->id(); if($userId===null){return Response::redirect('/login',303);}
        $page=filter_var($request->query('page',1),FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]]);$page=$page===false?1:(int)$page;
        return Response::html($this->view->render('pages/social-controls',['title'=>($blocked?'Bloqueados':'Silenciados').' · Flickary','currentRoute'=>'profile','auth'=>$this->auth,'csrf'=>$this->csrf,'blocked'=>$blocked,'controls'=>$blocked?$this->controls->paginateBlocked($userId,$page,self::PER_PAGE):$this->controls->paginateMuted($userId,$page,self::PER_PAGE),'messages'=>$this->session->consumeFlash()]));
    }

    private function mutate(Request$request,string$username,string$action):Response
    {
        $userId=$this->auth->id();if($userId===null){return Response::redirect('/login',303);}if(!$this->csrf->verify($request->input('_token'))){return Response::html('A sessão de formulário expirou.',403);}
        $target=$this->resolve($username);if(!$target instanceof PublicProfile){return $this->notFound($username);}if($target->userId===$userId){return Response::html('Você não pode aplicar este controle ao próprio perfil.',422);}
        if($action==='block'){$this->controls->block($userId,$target->userId);$this->session->flash('success','@'.$target->username.' foi bloqueado.');return Response::redirect('/perfil/bloqueados',303);}
        if($action==='unblock'){$this->controls->unblock($userId,$target->userId);$this->session->flash('success','@'.$target->username.' foi desbloqueado.');return Response::redirect('/perfil/bloqueados',303);}
        if($action==='mute'&&!$this->controls->mute($userId,$target->userId)){return $this->notFound($username);}
        if($action==='mute'){$this->session->flash('success','@'.$target->username.' foi silenciado no Social.');}
        else{$this->controls->unmute($userId,$target->userId);$this->session->flash('success','@'.$target->username.' voltou ao seu Social.');}
        return Response::redirect('/u/'.rawurlencode($target->username),303);
    }

    private function resolve(string$username):?PublicProfile{$normalized=$this->usernames->normalize($username);return $normalized!==null&&$normalized===$username&&$this->usernames->error($normalized)===null?$this->profiles->findByUsername($normalized):null;}
    private function notFound(string$username):Response{return Response::html($this->view->render('pages/404',['title'=>'Perfil não encontrado','path'=>'/u/'.$username,'currentRoute'=>null,'auth'=>$this->auth,'csrf'=>$this->csrf]),404);}
}
