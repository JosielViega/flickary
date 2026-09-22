<?php

declare(strict_types=1);

namespace Tests;

use App\Integrations\Tmdb\TmdbAnimeClassifier;
use App\Integrations\Tmdb\TmdbMedia;
use App\Integrations\Tmdb\TmdbMediaDetails;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TmdbAnimeClassifierTest extends TestCase
{
    /** @return iterable<string,array{array<int>,?string,bool}> */
    public static function mediaCases(): iterable
    {
        yield 'Japanese Animation' => [[16], 'ja', true];
        yield 'Japanese Animation among genres' => [[18, 16, 10759], 'ja', true];
        yield 'American Animation' => [[16], 'en', false];
        yield 'Japanese live action' => [[18], 'ja', false];
        yield 'missing genres' => [[], 'ja', false];
        yield 'missing language' => [[16], null, false];
    }

    #[DataProvider('mediaCases')]
    public function testClassifiesNormalizedMedia(array $genres, ?string $language, bool $expected): void
    {
        $media = new TmdbMedia('tmdb','series',1,'Title',null,null,null,null,null,null,null,null,null,$genres,$language,false);
        self::assertSame($expected, (new TmdbAnimeClassifier())->media($media));
    }

    public function testDetailsUseTheSamePolicyByGenreIdNotTranslatedName(): void
    {
        $anime = $this->details([['id'=>16,'name'=>'Animação']], 'ja');
        $wrongId = $this->details([['id'=>18,'name'=>'Animation']], 'ja');
        self::assertTrue((new TmdbAnimeClassifier())->details($anime));
        self::assertFalse((new TmdbAnimeClassifier())->details($wrongId));
    }

    private function details(array $genres, ?string $language): TmdbMediaDetails
    {
        return new TmdbMediaDetails('tmdb','movie',1,'Title',null,null,null,null,null,null,null,$genres,null,null,null,$language,false,null,null,null,null,null,null);
    }
}
