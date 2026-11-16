<?php

declare(strict_types=1);

namespace Tests;

use App\Social\SocialCommentBody;
use PHPUnit\Framework\TestCase;

final class SocialCommentBodyTest extends TestCase
{
    public function testNormalizesPlainTextAndPreservesAllowedUnicodeAndLines(): void
    {
        [$body, $error] = SocialCommentBody::validate("  Olá 🎬\r\n<script>alert(1)</script>  ");
        self::assertNull($error);
        self::assertSame("Olá 🎬\n<script>alert(1)</script>", $body?->value);
    }

    public function testAcceptsExactlyFiveHundredUnicodeCharacters(): void
    {
        [$body, $error] = SocialCommentBody::validate(str_repeat('á', 500));
        self::assertNull($error);
        self::assertSame(500, mb_strlen((string) $body?->value));
    }

    public function testRejectsEmptyOversizedInvalidUtf8AndControls(): void
    {
        foreach ([null, '', " \n ", str_repeat('a', 501), "bad\0text", "bad\x07text", "\xC3\x28"] as $value) {
            [$body, $error] = SocialCommentBody::validate($value);
            self::assertNull($body);
            self::assertNotNull($error);
        }
    }
}
