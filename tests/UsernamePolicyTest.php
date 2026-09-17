<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\UsernamePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UsernamePolicyTest extends TestCase
{
    #[DataProvider('validUsernameProvider')]
    public function testAcceptsAndNormalizesValidUsernames(string $input, string $expected): void
    {
        $policy = new UsernamePolicy();
        $normalized = $policy->normalize($input);

        self::assertSame($expected, $normalized);
        self::assertNull($policy->error($normalized));
    }

    public static function validUsernameProvider(): array
    {
        return [
            'letters' => ['Cinema', 'cinema'],
            'dot and underscore' => ['flickary.user_7', 'flickary.user_7'],
            'minimum' => ['a_1', 'a_1'],
            'maximum' => [str_repeat('a', 30), str_repeat('a', 30)],
        ];
    }

    #[DataProvider('invalidUsernameProvider')]
    public function testRejectsInvalidUsernames(mixed $input): void
    {
        $policy = new UsernamePolicy();

        self::assertNotNull($policy->error($policy->normalize($input)));
    }

    public static function invalidUsernameProvider(): array
    {
        return [
            'missing' => [null],
            'too short' => ['ab'],
            'too long' => [str_repeat('a', 31)],
            'starts with dot' => ['.abc'],
            'ends with underscore' => ['abc_'],
            'space' => ['abc def'],
            'hyphen' => ['abc-def'],
            'non ascii' => ['josé'],
        ];
    }
}
