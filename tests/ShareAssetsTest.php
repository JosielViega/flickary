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
        self::assertStringNotContainsString('execCommand', $script);
        self::assertStringNotContainsString('window.location', $script);
        self::assertStringNotContainsString('facebook', strtolower($script));
        self::assertStringNotContainsString('whatsapp', strtolower($script));
    }

    public function testStaticFallbackImageExists(): void
    {
        $image = dirname(__DIR__) . '/public/assets/images/flickary-share.png';
        self::assertFileExists($image);
        self::assertGreaterThan(0, filesize($image));
    }
}
