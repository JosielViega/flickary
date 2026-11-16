<?php

declare(strict_types=1);

namespace Tests;

use App\Core\PublicUrl;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicUrlTest extends TestCase
{
    public function testBuildsInternalUrlsAndPreservesBasePath(): void
    {
        self::assertSame(
            'https://example.test/flickary/filmes/603',
            (new PublicUrl('https://example.test/flickary/'))->forPath('/filmes/603'),
        );
        self::assertSame(
            'http://localhost:8010/u/user.name',
            (new PublicUrl('http://localhost:8010'))->forPath('/u/user.name'),
        );
    }

    #[DataProvider('invalidBases')]
    public function testRejectsInvalidBases(string $base): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PublicUrl($base);
    }

    public static function invalidBases(): array
    {
        return [[''], ['javascript:alert(1)'], ['ftp://example.test'], ['https://user@example.test'], ['https://example.test/?x=1']];
    }

    #[DataProvider('invalidPaths')]
    public function testRejectsExternalAmbiguousAndInjectedPaths(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PublicUrl('https://example.test'))->forPath($path);
    }

    public static function invalidPaths(): array
    {
        return [[''], ['filmes/1'], ['//evil.example/x'], ['https://evil.example/x'], ['/javascript:alert(1)?x'], ['/a/../b'], ["/a\nb"]];
    }

    public function testRequestHostCannotInfluenceConfiguredBase(): void
    {
        $_SERVER['HTTP_HOST'] = 'attacker.invalid';
        self::assertSame('https://trusted.example/u/alice', (new PublicUrl('https://trusted.example'))->forPath('/u/alice'));
        unset($_SERVER['HTTP_HOST']);
    }
}
