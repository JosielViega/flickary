<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Request;
use App\Core\Router;
use App\Core\View;
use PHPUnit\Framework\TestCase;

final class ApplicationRoutesTest extends TestCase
{
    public function testHomeRepresentsInitialFlickaryBaseline(): void
    {
        $response = $this->router()->dispatch($this->request('GET', '/'));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('<html lang="pt-BR">', $response->body());
        self::assertStringContainsString('<h1>Flickary</h1>', $response->body());
        self::assertStringContainsString('Passado · Presente · Futuro', $response->body());
        self::assertStringNotContainsString('<form', $response->body());
    }

    public function testHealthRemainsSmallAndSafe(): void
    {
        $response = $this->router()->dispatch($this->request('GET', '/health'));

        self::assertSame(200, $response->status());
        self::assertSame('{"status":"ok"}', $response->body());
        self::assertSame('application/json; charset=UTF-8', $response->headers()['Content-Type']);
    }

    public function testDemonstrationPostRouteNoLongerExists(): void
    {
        $response = $this->router()->dispatch($this->request('POST', '/example'));

        self::assertSame(404, $response->status());
    }

    private function router(): Router
    {
        $app = [
            'view' => new View(dirname(__DIR__) . '/resources/views'),
            'config' => ['name' => 'Flickary'],
            'router' => new Router(),
        ];

        return require dirname(__DIR__) . '/routes/web.php';
    }

    private function request(string $method, string $path): Request
    {
        return new Request(server: [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path,
        ]);
    }
}
