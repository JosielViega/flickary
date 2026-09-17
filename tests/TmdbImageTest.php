<?php

declare(strict_types=1);

namespace Tests;

use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Integrations\Tmdb\TmdbImageUrlBuilder;
use PHPUnit\Framework\TestCase;

final class TmdbImageTest extends TestCase
{
    public function testNormalizesConfigurationAndBuildsAllowedImageUrls(): void
    {
        $configuration = TmdbImageConfiguration::fromPayload(['images' => [
            'secure_base_url' => 'https://image.tmdb.org/t/p/',
            'poster_sizes' => ['w342', 'w500', 'original'],
            'backdrop_sizes' => ['w780', 'w1280', 'original'],
        ]]);
        $builder = new TmdbImageUrlBuilder($configuration);

        self::assertSame(['w342', 'w500', 'original'], $configuration->posterSizes);
        self::assertSame(['w780', 'w1280', 'original'], $configuration->backdropSizes);
        self::assertSame('https://image.tmdb.org/t/p/w500/abc.jpg', $builder->posterUrl('/abc.jpg', 'w500'));
        self::assertSame('https://image.tmdb.org/t/p/w1280/xyz.jpg', $builder->backdropUrl('/xyz.jpg', 'w1280'));
    }

    public function testReturnsNullForMissingInvalidPathOrUnknownSize(): void
    {
        $builder = new TmdbImageUrlBuilder(new TmdbImageConfiguration(
            'https://image.tmdb.org/t/p/',
            ['w500'],
            ['w1280'],
        ));

        self::assertNull($builder->posterUrl(null, 'w500'));
        self::assertNull($builder->posterUrl('../secret', 'w500'));
        self::assertNull($builder->posterUrl('/abc.jpg?token=x', 'w500'));
        self::assertNull($builder->posterUrl('/abc.jpg', 'w999'));
        self::assertNull($builder->backdropUrl('/abc.jpg', 'w500'));
    }

    public function testRejectsInsecureOrMalformedConfiguration(): void
    {
        foreach ([
            ['secure_base_url' => 'http://image.test/', 'poster_sizes' => ['w500'], 'backdrop_sizes' => ['w1280']],
            ['secure_base_url' => 'https://image.test/', 'poster_sizes' => ['bad'], 'backdrop_sizes' => ['w1280']],
        ] as $images) {
            try {
                TmdbImageConfiguration::fromPayload(['images' => $images]);
                self::fail('Expected exception.');
            } catch (TmdbException $exception) {
                self::assertSame('unexpected_payload', $exception->category);
            }
        }
    }
}
