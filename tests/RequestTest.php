<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Request;
use App\Navigation\PartialNavigationRequest;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testReadsCookiesWithoutAccessingGlobalsInConsumers(): void
    {
        $request = new Request(cookies: ['g_csrf_token' => 'token']);

        self::assertSame('token', $request->cookie('g_csrf_token'));
        self::assertSame('fallback', $request->cookie('missing', 'fallback'));
    }

    public function testReadsHeadersCaseInsensitivelyAndRejectsInvalidNames(): void
    {
        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_X_FLICKARY_NAVIGATION' => 'partial',
            'CONTENT_TYPE' => 'application/json',
        ]);

        self::assertSame('partial', $request->header('x-flickary-navigation'));
        self::assertSame('application/json', $request->header('Content-Type'));
        self::assertNull($request->header("Invalid\r\nHeader"));
        self::assertTrue((new PartialNavigationRequest($request))->isPartial());
    }

    public function testPartialNavigationOnlyAcceptsGetRequests(): void
    {
        $request = new Request(server: [
            'REQUEST_METHOD' => 'POST',
            'HTTP_X_FLICKARY_NAVIGATION' => 'PARTIAL',
        ]);

        self::assertFalse((new PartialNavigationRequest($request))->isPartial());
    }
}
