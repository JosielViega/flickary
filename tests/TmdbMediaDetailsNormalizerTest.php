<?php

declare(strict_types=1);

namespace Tests;

use App\Integrations\Tmdb\TmdbMediaDetailsNormalizer;
use PHPUnit\Framework\TestCase;

final class TmdbMediaDetailsNormalizerTest extends TestCase
{
    public function testNormalizesMovieDetailsAndPreservesTmdbValues(): void
    {
        $details = (new TmdbMediaDetailsNormalizer())->movie([
            'id' => 603,
            'title' => 'Matrix',
            'original_title' => 'The Matrix',
            'tagline' => 'Bem-vindo ao mundo real.',
            'overview' => 'Uma escolha muda tudo.',
            'release_date' => '1999-03-31',
            'poster_path' => '/poster.jpg',
            'backdrop_path' => '/backdrop.jpg',
            'genres' => [['id' => 28, 'name' => 'Ação'], ['id' => 878, 'name' => 'Ficção científica']],
            'vote_average' => 8.217,
            'vote_count' => 26789,
            'popularity' => 101.456,
            'original_language' => 'en',
            'adult' => false,
            'status' => 'Released',
            'runtime' => 136,
        ]);

        self::assertNotNull($details);
        self::assertSame('tmdb', $details->source);
        self::assertSame('movie', $details->mediaType);
        self::assertSame(603, $details->sourceId);
        self::assertSame('Matrix', $details->title);
        self::assertSame('The Matrix', $details->originalTitle);
        self::assertSame('Bem-vindo ao mundo real.', $details->tagline);
        self::assertSame('1999-03-31', $details->releaseDate);
        self::assertSame(1999, $details->year);
        self::assertSame([['id' => 28, 'name' => 'Ação'], ['id' => 878, 'name' => 'Ficção científica']], $details->genres);
        self::assertSame(136, $details->runtime);
        self::assertSame(8.217, $details->voteAverage);
        self::assertFalse($details->adult);
        self::assertNull($details->numberOfSeasons);
    }

    public function testNormalizesSeriesMappingsAndAggregates(): void
    {
        $details = (new TmdbMediaDetailsNormalizer())->series([
            'id' => 1396,
            'name' => 'Breaking Bad',
            'original_name' => 'Breaking Bad',
            'tagline' => 'All bad things must come to an end.',
            'overview' => 'Um professor muda de vida.',
            'first_air_date' => '2008-01-20',
            'last_air_date' => '2013-09-29',
            'poster_path' => '/series.jpg',
            'backdrop_path' => '/series-backdrop.jpg',
            'genres' => [['id' => 18, 'name' => 'Drama']],
            'vote_average' => 8.9,
            'vote_count' => 14000,
            'original_language' => 'en',
            'adult' => false,
            'status' => 'Ended',
            'number_of_seasons' => 5,
            'number_of_episodes' => 62,
            'in_production' => false,
        ]);

        self::assertNotNull($details);
        self::assertSame('series', $details->mediaType);
        self::assertSame('Breaking Bad', $details->title);
        self::assertSame('2008-01-20', $details->releaseDate);
        self::assertSame(2008, $details->year);
        self::assertSame(5, $details->numberOfSeasons);
        self::assertSame(62, $details->numberOfEpisodes);
        self::assertSame('2013-09-29', $details->lastAirDate);
        self::assertFalse($details->inProduction);
        self::assertNull($details->runtime);
    }

    public function testUsesOriginalTitleFallbackAndNormalizesOptionalFields(): void
    {
        $details = (new TmdbMediaDetailsNormalizer())->movie([
            'id' => 1,
            'title' => '',
            'original_title' => 'Original',
            'release_date' => '2026-02-31',
            'poster_path' => 'invalid',
            'backdrop_path' => null,
            'genres' => [
                ['id' => 1, 'name' => ' Drama '],
                ['id' => 1, 'name' => 'Duplicado'],
                ['id' => 0, 'name' => 'Inválido'],
                ['id' => 2, 'name' => ''],
                'invalid',
            ],
            'runtime' => 0,
        ]);

        self::assertNotNull($details);
        self::assertSame('Original', $details->title);
        self::assertNull($details->releaseDate);
        self::assertNull($details->year);
        self::assertNull($details->posterPath);
        self::assertNull($details->backdropPath);
        self::assertSame([['id' => 1, 'name' => 'Drama']], $details->genres);
        self::assertNull($details->runtime);
        self::assertNull($details->overview);
    }

    public function testRejectsInvalidDetailsPayloads(): void
    {
        $normalizer = new TmdbMediaDetailsNormalizer();
        self::assertNull($normalizer->movie([]));
        self::assertNull($normalizer->movie(['id' => 0, 'title' => 'Invalid']));
        self::assertNull($normalizer->movie(['id' => 2147483648, 'title' => 'Too large']));
        self::assertNull($normalizer->movie(['id' => 1, 'title' => '', 'original_title' => '']));
        self::assertNull($normalizer->series(['id' => '1396', 'name' => 'Coerced']));
        self::assertNull($normalizer->series(['id' => 1396]));
    }

    public function testPreservesExplicitAdultFlag(): void
    {
        $details = (new TmdbMediaDetailsNormalizer())->series([
            'id' => 99,
            'name' => 'Restricted',
            'adult' => true,
        ]);

        self::assertNotNull($details);
        self::assertTrue($details->adult);
    }
}
