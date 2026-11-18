<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testWithHeaderIsImmutableAndReplacesCaseInsensitively(): void
    {
        $original = Response::html('ok')->withHeader('X-Test', 'one');
        $changed = $original->withHeader('x-test', 'two');

        self::assertSame('one', $original->headers()['X-Test']);
        self::assertSame('two', $changed->headers()['x-test']);
        self::assertArrayNotHasKey('X-Test', $changed->headers());
        self::assertSame('ok', $changed->body());
        self::assertSame(200, $changed->status());
    }

    public function testWithHeaderRejectsInjection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Response::html('ok')->withHeader('X-Test', "safe\r\nInjected: yes");
    }
}
