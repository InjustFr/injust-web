<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation;

use App\Presentation\LocaleListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Translation\LocaleSwitcher;

final class LocaleListenerTest extends TestCase
{
    public function testWithoutALocaleInTheRouteTheBrowserLanguageIsUsed(): void
    {
        [$request, $switcher] = self::listen(['HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9']);

        self::assertSame(['fr', 'fr'], [$request->getLocale(), $switcher->getLocale()]);
    }

    public function testAnUnsupportedBrowserLanguageFallsBackToEnglish(): void
    {
        [$request, $switcher] = self::listen(['HTTP_ACCEPT_LANGUAGE' => 'de'], initial: 'fr');

        self::assertSame(['en', 'en'], [$request->getLocale(), $switcher->getLocale()]);
    }

    public function testTheRouteLocaleWinsOverTheBrowserLanguage(): void
    {
        [$request, $switcher] = self::listen(['HTTP_ACCEPT_LANGUAGE' => 'fr'], ['_locale' => 'en']);

        self::assertSame(['en', 'en'], [$request->getLocale(), $switcher->getLocale()]);
    }

    public function testASubRequestKeepsItsLocale(): void
    {
        [$request, $switcher] = self::listen(['HTTP_ACCEPT_LANGUAGE' => 'fr'], type: HttpKernelInterface::SUB_REQUEST);

        self::assertSame(['en', 'en'], [$request->getLocale(), $switcher->getLocale()]);
    }

    /**
     * @param array<string, string> $server
     * @param array<string, string> $attributes
     *
     * @return array{Request, LocaleSwitcher}
     */
    private static function listen(array $server, array $attributes = [], int $type = HttpKernelInterface::MAIN_REQUEST, string $initial = 'en'): array
    {
        $request = Request::create('/', server: $server);
        $request->attributes->add($attributes);
        $request->setLocale($attributes['_locale'] ?? $initial);
        $switcher = new LocaleSwitcher($attributes['_locale'] ?? $initial, []);
        new LocaleListener($switcher)(new RequestEvent(self::createStub(HttpKernelInterface::class), $request, $type));

        return [$request, $switcher];
    }
}
