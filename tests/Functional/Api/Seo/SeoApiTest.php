<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Seo;

use App\Application\Seo\PageSeoView;
use App\Application\Seo\SeoQueries;
use App\Domain\Seo\PageKey;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SeoApiTest extends WebTestCase
{
    use SignsInClient;

    private const array PAGE = [
        'title' => ['fr' => 'Cours de guitare', 'en' => 'Guitar lessons'],
        'description' => ['fr' => 'Apprendre le punk.', 'en' => 'Learn punk guitar.'],
    ];

    protected function tearDown(): void
    {
        new Filesystem()->remove(self::$kernel?->getShareDir() ?? []);
        parent::tearDown();
    }

    public function testEveryPageStartsBlankAndHomePointsToTheSettings(): void
    {
        $client = self::signedInClient();
        self::send($client, 'PUT', '/api/admin/settings/general', ['contactEmail' => 'a@injust.test', 'vatMentions' => ['consumer' => 'A', 'euBusiness' => 'B', 'nonEuBusiness' => 'C'], 'seoTitle' => ['fr' => 'Injust', 'en' => 'Injust — punk'], 'seoDescription' => ['fr' => '', 'en' => 'Protest punk.']]);

        $seo = self::send($client, 'GET', '/api/admin/seo');

        self::assertSame(['lessons', 'production', 'contact', 'links'], array_map(static fn (mixed $page): string => Json::string($page, 'key'), Json::array($seo, 'pages')));
        self::assertSame(['key' => 'links', 'title' => ['fr' => '', 'en' => ''], 'description' => ['fr' => '', 'en' => ''], 'image' => null], Json::array($seo, 'pages', 3));
        self::assertSame(['title' => ['fr' => 'Injust', 'en' => 'Injust — punk'], 'description' => ['fr' => '', 'en' => 'Protest punk.']], Json::array($seo, 'home'));
    }

    public function testAPageIsEdited(): void
    {
        $client = self::signedInClient();

        $page = self::send($client, 'PUT', '/api/admin/seo/lessons', self::PAGE);

        self::assertSame(['key' => 'lessons'] + self::PAGE + ['image' => null], $page);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $queries = self::getContainer()->get(SeoQueries::class);
        self::assertSame('Guitar lessons', $queries->page(PageKey::Lessons)->title->en);
        self::assertTrue($queries->page(PageKey::Contact)->title->isEmpty());
        self::assertContainsOnlyInstancesOf(PageSeoView::class, $queries->all());

        $page = self::send($client, 'PUT', '/api/admin/seo/lessons', ['title' => ['fr' => '', 'en' => 'Lessons']]);
        self::assertSame([['fr' => '', 'en' => 'Lessons'], ['fr' => '', 'en' => '']], [Json::array($page, 'title'), Json::array($page, 'description')]);
    }

    public function testATextOverItsLimitIsRefused(): void
    {
        $client = self::signedInClient();

        self::assertStringContainsString('70', self::refused($client, 'PUT', '/api/admin/seo/links', ['title' => ['fr' => str_repeat('a', 71), 'en' => '']]));
        self::refused($client, 'PUT', '/api/admin/seo/links', ['description' => ['fr' => '', 'en' => str_repeat('a', 171)]]);
        self::send($client, 'PUT', '/api/admin/seo/links', ['title' => ['fr' => str_repeat('é', 70), 'en' => ''], 'description' => ['fr' => '', 'en' => str_repeat('a', 170)]]);
    }

    public function testOnlyTheFourPagesAreEditable(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/seo/home', self::PAGE);
        self::assertResponseStatusCodeSame(404);
        $client->jsonRequest('PUT', '/api/admin/seo/beats', self::PAGE);
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnImageIsUploadedReplacedAndRemoved(): void
    {
        $client = self::signedInClient();
        self::send($client, 'PUT', '/api/admin/seo/production', self::PAGE);

        $first = Json::string(self::upload($client, 'production'), 'image');
        $second = self::upload($client, 'production');

        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', Json::string($second, 'image'));
        self::assertSame('Guitar lessons', Json::string($second, 'title', 'en'));
        self::assertFalse(self::imageExists($client, $first));
        self::assertTrue(self::imageExists($client, Json::string($second, 'image')));

        $removed = self::send($client, 'DELETE', '/api/admin/seo/production/image');
        self::assertNull(Json::at($removed, 'image'));
        self::assertFalse(self::imageExists($client, Json::string($second, 'image')));
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertNull(self::getContainer()->get(SeoQueries::class)->page(PageKey::Production)->image);
    }

    public function testAnImageIsStoredForAPageNeverEdited(): void
    {
        $client = self::signedInClient();

        $image = Json::string(self::upload($client, 'contact'), 'image');

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame($image, self::getContainer()->get(SeoQueries::class)->page(PageKey::Contact)->image);
        self::assertNull(Json::at(self::send($client, 'DELETE', '/api/admin/seo/links/image'), 'image'));
    }

    public function testAnUploadWithoutAnImageIsRefused(): void
    {
        $client = self::signedInClient();

        $client->request('POST', '/api/admin/seo/links/image', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);

        $client->request('POST', '/api/admin/seo/links/image', files: ['file' => new UploadedFile(self::copyOf('not-an-image.png'), 'x.png', 'image/png', test: true)], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testVisitorsAndOtherOriginsAreKeptOut(): void
    {
        $client = self::createClient();
        $client->jsonRequest('GET', '/api/admin/seo');
        self::assertResponseStatusCodeSame(401);
        self::ensureKernelShutdown();

        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/seo/links', self::PAGE, ['HTTP_ORIGIN' => 'https://evil.test']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAWriteReturnsTheRefreshedPages(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/seo/contact', self::PAGE, ['HTTP_X_REFRESH' => '["/api/admin/seo"]']);

        self::assertResponseIsSuccessful();
        self::assertSame('Guitar lessons', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'refreshed', '/api/admin/seo', 'pages', 2, 'title', 'en'));
    }

    /**
     * @param array<mixed> $body
     *
     * @return array<mixed>
     */
    private static function send(KernelBrowser $client, string $method, string $url, array $body = []): array
    {
        $client->jsonRequest($method, $url, $body);
        self::assertResponseIsSuccessful();

        return Json::decode((string) $client->getResponse()->getContent());
    }

    /** @param array<mixed> $body */
    private static function refused(KernelBrowser $client, string $method, string $url, array $body = []): string
    {
        $client->jsonRequest($method, $url, $body);
        self::assertResponseStatusCodeSame(422);

        return Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail');
    }

    /** @return array<mixed> */
    private static function upload(KernelBrowser $client, string $key): array
    {
        $client->request('POST', '/api/admin/seo/'.$key.'/image', files: ['file' => new UploadedFile(self::copyOf('large-fern.png'), 'image.png', 'image/png', test: true)], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();

        return Json::decode((string) $client->getResponse()->getContent());
    }

    private static function imageExists(KernelBrowser $client, string $image): bool
    {
        $client->request('GET', '/media/images/'.$image.'.webp');

        return $client->getResponse()->isSuccessful();
    }

    private static function copyOf(string $fixture): string
    {
        $copy = tempnam(sys_get_temp_dir(), 'image');
        self::assertIsString($copy);
        copy(\dirname(__DIR__, 3).'/Fixtures/'.$fixture, $copy);

        return $copy;
    }
}
