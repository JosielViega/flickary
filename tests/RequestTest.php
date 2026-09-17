<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testReadsCookiesWithoutAccessingGlobalsInConsumers(): void
    {
        $request = new Request(cookies: ['g_csrf_token' => 'token']);

        self::assertSame('token', $request->cookie('g_csrf_token'));
        self::assertSame('fallback', $request->cookie('missing', 'fallback'));
    }
}
