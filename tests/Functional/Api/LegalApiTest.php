<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Application\Settings\SettingsQueries;
use App\Domain\Settings\LegalKind;
use App\Domain\Shared\Exception\NotFound;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LegalApiTest extends WebTestCase
{
    use SignsInClient;

    public function testEachEditOfALegalTextIsANewVersion(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/legal/cgv-lessons', ['body' => ['fr' => '# CGV', 'en' => '# Terms']]);
        self::assertResponseIsSuccessful();
        self::assertSame(1, Json::int(Json::decode((string) $client->getResponse()->getContent()), 'version'));

        $client->jsonRequest('PUT', '/api/admin/legal/cgv-lessons', ['body' => ['fr' => '# CGV v2', 'en' => '']]);
        $document = Json::decode((string) $client->getResponse()->getContent());

        self::assertSame(['cgv-lessons', 2, ['fr' => '# CGV v2', 'en' => '']], [Json::string($document, 'kind'), Json::int($document, 'version'), Json::array($document, 'body')]);
        $view = self::getContainer()->get(SettingsQueries::class)->legalDocument(LegalKind::CgvLessons);
        self::assertSame([2, '# CGV v2'], [$view->version, $view->body->in('en')]);
    }

    public function testTheLegalTextsAreListed(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/legal/privacy', ['body' => ['fr' => 'Vie privée', 'en' => 'Privacy']]);

        $client->jsonRequest('GET', '/api/admin/legal');

        self::assertResponseIsSuccessful();
        $documents = Json::decode((string) $client->getResponse()->getContent());
        self::assertTrue(array_is_list($documents));
        self::assertSame(['privacy'], array_column($documents, 'kind'));
    }

    public function testAnEmptyLegalTextIsRefused(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/legal/mentions', ['body' => ['fr' => ' ', 'en' => '']]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testAnUnknownKindIsNotFound(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/legal/cookies', ['body' => ['fr' => 'x', 'en' => 'x']]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testAMissingDocumentIsNotFound(): void
    {
        self::bootKernel();

        $this->expectException(NotFound::class);

        self::getContainer()->get(SettingsQueries::class)->legalDocument(LegalKind::Licence);
    }
}
