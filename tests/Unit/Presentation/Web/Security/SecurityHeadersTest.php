<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Security;

use App\Application\Settings\LegalDocumentView;
use App\Application\Settings\SettingsQueries;
use App\Application\Settings\SettingsView;
use App\Domain\Settings\LegalKind;
use App\Domain\Settings\Settings;
use App\Domain\Settings\VatMentions;
use App\Domain\Shared\LocalizedText;
use App\Presentation\Web\Security\SecurityHeaders;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersTest extends TestCase
{
    private const string POLICY = "default-src 'self'; script-src 'self'; style-src 'self' 'sha256-60LHlRjW/B3CtzIoE/Lf1/NEDvko9efWMFaGVhHu/cs='; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-src https://www.youtube-nocookie.com https://open.spotify.com; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'";

    public function testEveryPageGetsTheStrictPolicyAndTheAccountsOriginMayReceiveForms(): void
    {
        $headers = self::respond('/', accountsUrl: 'https://accounts.test/application/o/injust/');

        self::assertSame(self::POLICY.' https://accounts.test', $headers->get('Content-Security-Policy'));
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $headers->get('X-Frame-Options'));
        self::assertFalse($headers->has('Strict-Transport-Security'));
    }

    public function testTheAccountsPortIsKeptAndAnUnusableUrlAddsNoOrigin(): void
    {
        self::assertSame(self::POLICY.' http://localhost:8099', self::respond('/', accountsUrl: 'http://localhost:8099/injust')->get('Content-Security-Policy'));
        self::assertSame(self::POLICY, self::respond('/', accountsUrl: 'not a url')->get('Content-Security-Policy'));
    }

    public function testTheDevServerGetsNoPolicySoViteCanInjectItsScripts(): void
    {
        self::assertFalse(self::respond('/', environment: 'dev')->has('Content-Security-Policy'));
    }

    public function testASecureRequestAsksBrowsersToStayOnHttps(): void
    {
        self::assertSame('max-age=31536000; includeSubDomains', self::respond('https://injust.test/')->get('Strict-Transport-Security'));
    }

    public function testASubRequestIsLeftAlone(): void
    {
        self::assertFalse(self::respond('/', type: HttpKernelInterface::SUB_REQUEST)->has('X-Frame-Options'));
    }

    #[DataProvider('hiddenPaths')]
    public function testTheAdminAndTheApiAreNeverIndexed(string $path, bool $hidden): void
    {
        self::assertSame($hidden ? 'noindex, nofollow' : null, self::respond($path, searchIndexing: true)->get('X-Robots-Tag'));
    }

    /** @return iterable<array{string, bool}> */
    public static function hiddenPaths(): iterable
    {
        yield ['/admin', true];
        yield ['/admin/settings', true];
        yield ['/api/admin/settings', true];
        yield ['/', false];
        yield ['/legal/admin', false];
        yield ['/administration', false];
    }

    public function testThePublicSiteIsHiddenFromSearchUntilIndexingIsSwitchedOn(): void
    {
        self::assertSame('noindex, nofollow', self::respond('/')->get('X-Robots-Tag'));
    }

    private static function respond(
        string $uri,
        bool $searchIndexing = false,
        string $environment = 'prod',
        string $accountsUrl = 'https://accounts.test',
        int $type = HttpKernelInterface::MAIN_REQUEST,
    ): ResponseHeaderBag {
        $settings = Settings::initial();
        $settings->editGeneral('a@b.test', null, VatMentions::defaults(), new LocalizedText(), new LocalizedText(), $searchIndexing, '');
        $queries = new class(SettingsView::of($settings)) implements SettingsQueries {
            public function __construct(private readonly SettingsView $view)
            {
            }

            public function current(): SettingsView
            {
                return $this->view;
            }

            public function legalDocument(LegalKind $kind): LegalDocumentView
            {
                throw new \LogicException();
            }

            public function legalDocuments(): array
            {
                return [];
            }
        };
        $response = new Response();
        new SecurityHeaders($queries, $environment, $accountsUrl)(new ResponseEvent(self::createStub(HttpKernelInterface::class), Request::create($uri), $type, $response));

        return $response->headers;
    }
}
