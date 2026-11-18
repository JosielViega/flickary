<?php

declare(strict_types=1);

namespace Tests;

use App\Core\LayoutContextProvider;
use App\Core\PageMeta;
use App\Core\Request;
use App\Core\View;
use App\Navigation\PartialNavigationRequest;
use PHPUnit\Framework\TestCase;

final class PartialNavigationTest extends TestCase
{
    public function testAppLayoutReturnsMetadataEnvelopeAndSkipsGlobalContext(): void
    {
        $calls = 0;
        $context = new class($calls) implements LayoutContextProvider {
            public function __construct(private int &$calls) {}
            public function context(): array { $this->calls++; return ['notificationUnreadCount' => 9]; }
        };
        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_X_FLICKARY_NAVIGATION' => 'partial',
        ]);
        $view = new View(
            dirname(__DIR__) . '/resources/views',
            $context,
            new PartialNavigationRequest($request),
        );
        $meta = new PageMeta(
            'Sobre o Flickary',
            'Descrição pública',
            'https://flickary.test/sobre',
            'https://flickary.test/card.jpg',
            'Prévia do Flickary',
        );

        $html = $view->render('pages/about', [
            'title' => 'Sobre — Flickary',
            'currentRoute' => 'about',
            'searchQuery' => 'Matrix',
            'pageMeta' => $meta,
        ]);

        self::assertSame(0, $calls);
        self::assertStringNotContainsString('<html', $html);
        self::assertStringContainsString('data-flickary-partial', $html);
        self::assertStringContainsString('data-title="Sobre — Flickary"', $html);
        self::assertStringContainsString('data-current-route="about"', $html);
        self::assertStringContainsString('data-search-query="Matrix"', $html);
        self::assertStringContainsString('data-cacheable="1"', $html);
        self::assertStringContainsString('data-flickary-page-head', $html);
        self::assertStringContainsString('data-flickary-content', $html);
    }

    public function testMessagesAndExplicitFlagDisableFragmentCache(): void
    {
        $view = $this->partialView();

        $messages = $view->render('pages/about', [
            'title' => 'Sobre', 'messages' => ['success' => 'Salvo.'],
        ]);
        $explicit = $view->render('pages/about', [
            'title' => 'Sobre', 'partialCacheable' => false,
        ]);

        self::assertStringContainsString('data-cacheable="0"', $messages);
        self::assertStringContainsString('data-cacheable="0"', $explicit);
    }

    public function testFullAndAuthenticationLayoutsRemainCompleteDocuments(): void
    {
        $calls = 0;
        $context = new class($calls) implements LayoutContextProvider {
            public function __construct(private int &$calls) {}
            public function context(): array { $this->calls++; return []; }
        };
        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_X_FLICKARY_NAVIGATION' => 'partial',
        ]);
        $view = new View(dirname(__DIR__) . '/resources/views', $context, new PartialNavigationRequest($request));

        $auth = $view->render('pages/about', ['title' => 'Sobre'], 'layouts/auth');
        self::assertStringContainsString('<html lang="pt-BR">', $auth);
        self::assertSame(1, $calls);

        $fullRequest = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $full = (new View(
            dirname(__DIR__) . '/resources/views',
            $context,
            new PartialNavigationRequest($fullRequest),
        ))->render('pages/about', ['title' => 'Sobre']);
        self::assertStringContainsString('<html lang="pt-BR">', $full);
        self::assertSame(2, $calls);
    }

    private function partialView(): View
    {
        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_X_FLICKARY_NAVIGATION' => 'partial',
        ]);

        return new View(dirname(__DIR__) . '/resources/views', partialNavigation: new PartialNavigationRequest($request));
    }
}
