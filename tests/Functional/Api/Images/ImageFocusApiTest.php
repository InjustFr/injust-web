<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Images;

use App\Application\Shared\SetImageFocus\SetImageFocus;
use App\Application\Shared\SetImageFocus\SetImageFocusHandler;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Twig\Environment;

final class ImageFocusApiTest extends WebTestCase
{
    use SignsInClient;

    private const string UNKNOWN = '01HZZZZZZZZZZZZZZZZZZZZZZC';

    protected function tearDown(): void
    {
        new Filesystem()->remove(self::$kernel?->getShareDir() ?? []);
        parent::tearDown();
    }

    public function testAnImageStartsCenteredAndKeepsItsFocus(): void
    {
        $client = self::signedInClient();
        $image = self::uploadImage($client);

        self::assertSame(['id' => $image, 'x' => 50, 'y' => 50], self::send($client, 'GET', self::url($image)));
        self::assertSame(['id' => $image, 'x' => 35, 'y' => 60], self::send($client, 'PUT', self::url($image), ['x' => 35, 'y' => 60]));
        self::assertSame(['id' => $image, 'x' => 35, 'y' => 60], self::send($client, 'GET', self::url($image)));
        self::assertSame(['id' => $image, 'x' => 0, 'y' => 100], self::send($client, 'PUT', self::url($image), ['x' => 0, 'y' => 100]));
        self::assertSame(['id' => $image, 'x' => 50, 'y' => 50], self::send($client, 'PUT', self::url($image), ['x' => 50, 'y' => 50]));
        self::assertSame(0, self::rows());
    }

    public function testTheStoredFocusesAreListedTogether(): void
    {
        $client = self::signedInClient();
        self::assertSame([], self::send($client, 'GET', '/api/admin/images/focus'));
        $image = self::uploadImage($client);
        self::send($client, 'PUT', self::url($image), ['x' => 35, 'y' => 60]);

        self::assertSame([['id' => $image, 'x' => 35, 'y' => 60]], self::send($client, 'GET', '/api/admin/images/focus'));
    }

    public function testAFocusOffTheGridIsRefused(): void
    {
        $client = self::signedInClient();
        $image = self::uploadImage($client);

        $client->jsonRequest('PUT', self::url($image), ['x' => 33, 'y' => 50]);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('5', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));
        $client->jsonRequest('PUT', self::url($image), ['x' => 50, 'y' => 105]);
        self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('PUT', self::url($image), ['x' => 50]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testAnUnknownImageIsNotFound(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', self::url(self::UNKNOWN), ['x' => 35, 'y' => 60]);
        self::assertResponseStatusCodeSame(404);
        $client->jsonRequest('GET', '/api/admin/images/not-an-id/focus');
        self::assertResponseStatusCodeSame(404);
    }

    public function testADiscardedImageLosesItsFocus(): void
    {
        $client = self::signedInClient();
        $image = self::uploadImage($client);
        self::send($client, 'PUT', self::url($image), ['x' => 35, 'y' => 60]);

        self::send($client, 'DELETE', '/api/admin/seo/contact/image');

        self::assertSame(0, self::rows());
    }

    public function testTheTwigFunctionPrintsTheClassesAndForgetsThemBetweenRequests(): void
    {
        $client = self::signedInClient();
        $image = self::uploadImage($client);
        $template = self::getContainer()->get(Environment::class)->createTemplate('[{{ image_focus(id) }}][{{ image_focus(null) }}]');

        self::assertSame('[][]', $template->render(['id' => $image]));
        self::getContainer()->get(SetImageFocusHandler::class)(new SetImageFocus($image, 35, 60));
        self::assertSame('[][]', $template->render(['id' => $image]));
        self::getContainer()->get('services_resetter')->reset();
        self::assertSame('[fx-35 fy-60][]', $template->render(['id' => $image]));
    }

    public function testVisitorsAndOtherOriginsAreKeptOut(): void
    {
        $client = self::createClient();
        $client->jsonRequest('GET', self::url(self::UNKNOWN));
        self::assertResponseStatusCodeSame(401);
        $client->jsonRequest('GET', '/api/admin/images/focus');
        self::assertResponseStatusCodeSame(401);
        $client->jsonRequest('PUT', self::url(self::UNKNOWN), ['x' => 35, 'y' => 60]);
        self::assertResponseStatusCodeSame(401);
        self::ensureKernelShutdown();

        $client = self::signedInClient();
        $image = self::uploadImage($client);
        $client->jsonRequest('PUT', self::url($image), ['x' => 35, 'y' => 60], ['HTTP_ORIGIN' => 'https://evil.test']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, self::rows());
    }

    private static function url(string $image): string
    {
        return '/api/admin/images/'.$image.'/focus';
    }

    private static function rows(): int
    {
        $rows = self::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM image_focus');
        self::assertIsInt($rows);

        return $rows;
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

    private static function uploadImage(KernelBrowser $client): string
    {
        $copy = tempnam(sys_get_temp_dir(), 'image');
        self::assertIsString($copy);
        copy(\dirname(__DIR__, 3).'/Fixtures/large-fern.png', $copy);
        $client->request('POST', '/api/admin/seo/contact/image', files: ['file' => new UploadedFile($copy, 'image.png', 'image/png', test: true)], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();

        return Json::string(Json::decode((string) $client->getResponse()->getContent()), 'image');
    }
}
