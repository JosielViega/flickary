<?php

declare(strict_types=1);

namespace Tests;

use App\Messaging\DirectMessageBody;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DirectMessageBodyTest extends TestCase
{
    public function testNormalizesWhitespaceAndPreservesLineBreaks(): void
    {
        [$body, $error] = DirectMessageBody::validate("  Olá\r\nsegunda linha  ");
        self::assertNull($error);
        self::assertSame("Olá\nsegunda linha", $body?->value);
    }

    #[DataProvider('invalidBodies')]
    public function testRejectsInvalidBodies(mixed $value): void
    {
        [$body, $error] = DirectMessageBody::validate($value);
        self::assertNull($body);
        self::assertNotNull($error);
    }

    public static function invalidBodies(): array
    {
        return [
            'not a string' => [null],
            'empty' => ['   '],
            'nul' => ["texto\0oculto"],
            'control' => ["texto\x07"],
            'invalid utf8' => ["\xC3\x28"],
            'too long' => [str_repeat('a', 2001)],
        ];
    }

    public function testAcceptsExactlyTwoThousandMultibyteCharacters(): void
    {
        [$body, $error] = DirectMessageBody::validate(str_repeat('á', 2000));
        self::assertNull($error);
        self::assertSame(2000, mb_strlen((string) $body?->value));
    }

    #[DataProvider('validBodies')]
    public function testAcceptsPlainTextLiterally(string $value): void
    {
        [$body,$error]=DirectMessageBody::validate($value);self::assertNull($error);self::assertSame($value,$body?->value);
    }

    public static function validBodies():array
    {
        return ['one character'=>['a'],'emoji'=>['🎬'],'multiline'=>["linha 1\nlinha 2"],'html literal'=>['<script>alert(1)</script><img src=x onerror=alert(1)>']];
    }
}
