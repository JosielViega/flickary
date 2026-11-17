<?php

declare(strict_types=1);

namespace App\Social;

final class NullUserSocialControlStore implements UserSocialControlStore
{
    public function isBlockedEitherDirection(int$userA,int$userB):bool{return false;}
    public function isBlockedBy(int$blockerUserId,int$blockedUserId):bool{return false;}
    public function isMuted(int$userId,int$targetUserId):bool{return false;}
    public function block(int$blockerUserId,int$blockedUserId):void{}
    public function unblock(int$blockerUserId,int$blockedUserId):void{}
    public function mute(int$userId,int$targetUserId):bool{return false;}
    public function unmute(int$userId,int$targetUserId):void{}
    public function paginateBlocked(int$userId,int$page,int$perPage):UserSocialControlPage{return new UserSocialControlPage([],0,$page,$perPage);}
    public function paginateMuted(int$userId,int$page,int$perPage):UserSocialControlPage{return new UserSocialControlPage([],0,$page,$perPage);}
}
