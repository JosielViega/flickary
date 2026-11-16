<?php

declare(strict_types=1);

namespace Tests;

use App\Social\PrivateProfileAccessPolicy;
use App\Social\PublicProfile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PrivateProfileAccessPolicyTest extends TestCase
{
    #[DataProvider('cases')]
    public function testAccessIsGrantedOnlyByVisibilityOwnerOrAcceptedFollow(bool $private, ?int $viewer, bool $following, bool $expected): void
    {
        $profile = new PublicProfile(10, 'private.user', 'Private User', null, null, null, $private, '2026-01-01');
        self::assertSame($expected, (new PrivateProfileAccessPolicy())->canView($profile, $viewer, $following));
    }

    public static function cases(): array
    {
        return [
            'public anonymous'=>[false,null,false,true], 'public authenticated'=>[false,20,false,true],
            'private anonymous'=>[true,null,false,false], 'private unrelated'=>[true,20,false,false],
            'private pending does not authorize'=>[true,20,false,false],
            'private follower'=>[true,20,true,true], 'private owner'=>[true,10,false,true],
        ];
    }
}
