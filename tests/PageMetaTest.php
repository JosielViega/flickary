<?php

declare(strict_types=1);

namespace Tests;

use App\Core\PageMeta;
use App\Core\View;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PageMetaTest extends TestCase
{
    public function testNormalizesPlainDescriptionAndAppliesDefensiveLimit(): void
    {
        $description = "  <b>Uma</b>\n\t história   com espaços " . str_repeat('x', 220);
        $normalized = PageMeta::normalizeDescription($description);

        self::assertStringStartsWith('Uma história com espaços ', $normalized);
        self::assertStringNotContainsString('<b>', $normalized);
        self::assertLessThanOrEqual(200, mb_strlen($normalized));
        self::assertStringEndsWith('…', $normalized);
    }

    public function testLayoutEscapesAllMetadataAttributes(): void
    {
        $meta = new PageMeta(
            'Title " <script>&',
            "Description ' \" <script>alert(1)</script> &",
            'https://example.test/u/test',
            'https://example.test/image.png',
            'Image " < > &',
        );
        $html = (new View(dirname(__DIR__) . '/resources/views'))->render('pages/about', [
            'title' => 'Test', 'currentRoute' => null, 'pageMeta' => $meta,
        ]);

        self::assertStringContainsString('content="Title &quot; &lt;script&gt;&amp;"', $html);
        self::assertStringContainsString('content="Description &#039; &quot; alert(1) &amp;"', $html);
        self::assertStringContainsString('content="Image &quot; &lt; &gt; &amp;"', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        foreach (['name="description"', 'rel="canonical"', 'property="og:title"', 'property="og:type"', 'property="og:url"', 'property="og:description"', 'property="og:site_name"', 'property="og:locale"', 'property="og:image"', 'property="og:image:alt"'] as $tag) {
            self::assertStringContainsString($tag, $html);
        }
    }

    public function testRejectsNonWebImageUrls(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PageMeta('Title', 'Description', 'https://example.test/page', 'javascript:alert(1)', 'Image');
    }
}
