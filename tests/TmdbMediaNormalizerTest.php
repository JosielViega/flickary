<?php

declare(strict_types=1);

namespace Tests;

use App\Integrations\Tmdb\TmdbMediaNormalizer;
use PHPUnit\Framework\TestCase;

final class TmdbMediaNormalizerTest extends TestCase
{
    public function testNormalizesMovieWithoutDestroyingTmdbValues(): void
    {
        $media = (new TmdbMediaNormalizer())->movie([
            'id' => 603,
            'title' => 'Matrix',
            'original_title' => 'The Matrix',
            'overview' => 'Uma escolha muda tudo.',
            'release_date' => '1999-03-30',
            'poster_path' => '/poster.jpg',
            'backdrop_path' => '/backdrop.jpg',
            'vote_average' => 8.217,
            'vote_count' => 26789,
            'popularity' => 101.456,
            'genre_ids' => [28, 878, 28, 'invalid'],
            'original_language' => 'en',
            'adult' => false,
        ]);

        self::assertNotNull($media);
        self::assertSame('tmdb', $media->source);
        self::assertSame('movie', $media->mediaType);
        self::assertSame(603, $media->sourceId);
        self::assertSame('Matrix', $media->title);
        self::assertSame('The Matrix', $media->originalTitle);
        self::assertSame('Uma escolha muda tudo.', $media->overview);
        self::assertSame('1999-03-30', $media->releaseDate);
        self::assertSame(1999, $media->year);
        self::assertSame('/poster.jpg', $media->posterPath);
        self::assertSame('/backdrop.jpg', $media->backdropPath);
        self::assertSame(8.217, $media->voteAverage);
        self::assertSame(26789, $media->voteCount);
        self::assertSame(101.456, $media->popularity);
        self::assertSame([28, 878], $media->genreIds);
        self::assertSame('en', $media->originalLanguage);
        self::assertFalse($media->adult);
    }

    public function testMovieUsesOriginalTitleFallbackAndHandlesMissingFields(): void
    {
        $normalizer = new TmdbMediaNormalizer();
        $media = $normalizer->movie([
            'id' => 1,
            'title' => '',
            'original_title' => 'Original',
            'release_date' => '',
            'poster_path' => 'invalid',
        ]);

        self::assertNotNull($media);
        self::assertSame('Original', $media->title);
        self::assertNull($media->overview);
        self::assertNull($media->releaseDate);
        self::assertNull($media->year);
        self::assertNull($media->posterPath);
        self::assertNull($media->adult);
    }

    public function testRejectsMovieWithoutPositiveIdOrAnyTitle(): void
    {
        $normalizer = new TmdbMediaNormalizer();
        self::assertNull($normalizer->movie(['title' => 'No id']));
        self::assertNull($normalizer->movie(['id' => 0, 'title' => 'Invalid id']));
        self::assertNull($normalizer->movie(['id' => '1', 'title' => 'Coerced id']));
        self::assertNull($normalizer->movie(['id' => true, 'title' => 'Boolean id']));
        self::assertNull($normalizer->movie(['id' => 1, 'title' => '', 'original_title' => '']));
    }

    public function testNormalizesTvAsSeriesAndMapsEquivalentFields(): void
    {
        $media = (new TmdbMediaNormalizer())->series([
            'id' => 1396,
            'name' => 'Breaking Bad',
            'original_name' => 'Breaking Bad',
            'first_air_date' => '2008-01-20',
            'overview' => 'A chemistry teacher changes course.',
            'poster_path' => '/tv.jpg',
            'vote_average' => 8.9,
            'vote_count' => 14000,
            'genre_ids' => [18, 80],
            'original_language' => 'en',
            'adult' => false,
        ]);

        self::assertNotNull($media);
        self::assertSame('series', $media->mediaType);
        self::assertSame('Breaking Bad', $media->title);
        self::assertSame('Breaking Bad', $media->originalTitle);
        self::assertSame('2008-01-20', $media->releaseDate);
        self::assertSame(2008, $media->year);
    }

    public function testInvalidExternalDateDoesNotCreateEpochOrYear(): void
    {
        $media = (new TmdbMediaNormalizer())->series([
            'id' => 10,
            'name' => 'Series',
            'first_air_date' => '2025-02-31',
        ]);

        self::assertNotNull($media);
        self::assertNull($media->releaseDate);
        self::assertNull($media->year);
    }

    public function testRejectsInvalidTvPayload(): void
    {
        $normalizer = new TmdbMediaNormalizer();
        self::assertNull($normalizer->series([]));
        self::assertNull($normalizer->series(['id' => -1, 'name' => 'Invalid']));
        self::assertNull($normalizer->series(['id' => 1]));
    }
}
