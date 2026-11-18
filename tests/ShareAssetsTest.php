<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class ShareAssetsTest extends TestCase
{
    public function testShareScriptUsesOnlyProgressiveNativeApis(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__) . '/public/assets/js/share.js');

        self::assertStringContainsString("querySelectorAll('[data-share-actions]')", $script);
        self::assertStringContainsString("typeof navigator.share === 'function'", $script);
        self::assertStringContainsString("addEventListener('click'", $script);
        self::assertStringContainsString('navigator.clipboard.writeText(url)', $script);
        self::assertStringContainsString("error.name === 'AbortError'", $script);
        self::assertStringContainsString('window.FlickaryShare', $script);
        self::assertStringContainsString("'flickary:navigated'", $script);
        self::assertStringContainsString('shareInitialized', $script);
        self::assertStringNotContainsString('execCommand', $script);
        self::assertStringNotContainsString('window.location', $script);
        self::assertStringNotContainsString('facebook', strtolower($script));
        self::assertStringNotContainsString('whatsapp', strtolower($script));
    }

    public function testNavigationScriptKeepsEnhancementBoundedAndEphemeral(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__) . '/public/assets/js/app-navigation.js');

        self::assertStringContainsString("'X-Flickary-Navigation': 'partial'", $script);
        self::assertStringContainsString("method: 'GET'", $script);
        self::assertStringContainsString('new Map()', $script);
        self::assertStringContainsString('var CACHE_TTL_MS = 30000', $script);
        self::assertStringContainsString('var CACHE_LIMIT = 20', $script);
        self::assertStringContainsString('new AbortController()', $script);
        self::assertStringContainsString("'popstate'", $script);
        self::assertStringContainsString("'flickary:navigated'", $script);
        self::assertStringContainsString('window.location.assign', $script);
        self::assertStringNotContainsString('localStorage', $script);
        self::assertStringNotContainsString('sessionStorage', $script);
        self::assertStringNotContainsString('indexedDB', $script);
        self::assertStringNotContainsString('caches.', $script);
        self::assertStringNotContainsString('serviceWorker', $script);
        self::assertStringNotContainsString('prefetch', strtolower($script));
        self::assertStringNotContainsString("method: 'POST'", $script);
    }

    public function testStaticFallbackImageExists(): void
    {
        $image = dirname(__DIR__) . '/public/assets/images/flickary-share.png';
        self::assertFileExists($image);
        self::assertGreaterThan(0, filesize($image));
    }
}
