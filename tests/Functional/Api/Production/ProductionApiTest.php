<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Production;

use App\Application\Production\ProductionQueries;
use App\Tests\Functional\Api\Music\CallsMusicApi;
use App\Tests\Support\Json;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class ProductionApiTest extends WebTestCase
{
    use CallsMusicApi;

    private const array SERVICE = [
        'kind' => 'mix',
        'title' => ['fr' => 'Mixage', 'en' => 'Mixing'],
        'description' => ['fr' => 'Un titre mixé.', 'en' => 'One song mixed.'],
        'pricing' => 'fixed',
        'price' => ['cents' => 25000],
        'deliveryDays' => 7,
        'revisions' => 2,
    ];

    public function testTheProductionPageStartsEmpty(): void
    {
        $client = self::signedInClient();

        self::assertSame(['page' => ['intro' => ['fr' => '', 'en' => ''], 'process' => ['fr' => '', 'en' => ''], 'faq' => ['fr' => '', 'en' => '']], 'services' => [], 'portfolio' => []], self::send($client, 'GET', '/api/admin/production'));
    }

    public function testTheTextsAreEdited(): void
    {
        $client = self::signedInClient();

        $page = self::send($client, 'PUT', '/api/admin/production/page', ['intro' => ['fr' => ' **Salut** ', 'en' => 'Hi'], 'process' => ['fr' => '1. Envoi', 'en' => ''], 'faq' => ['fr' => '', 'en' => '## Formats?']]);

        self::assertSame(['intro' => ['fr' => '**Salut**', 'en' => 'Hi'], 'process' => ['fr' => '1. Envoi', 'en' => ''], 'faq' => ['fr' => '', 'en' => '## Formats?']], $page);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $view = self::getContainer()->get(ProductionQueries::class)->page();
        self::assertSame(['Hi', '1. Envoi', '## Formats?'], [$view->intro->in('en'), $view->process->in('en'), $view->faq->in('fr')]);
        self::assertSame($page, Json::array(self::send($client, 'GET', '/api/admin/production'), 'page'));
    }

    public function testAServiceIsAddedAtTheEnd(): void
    {
        $client = self::signedInClient();
        self::add($client);

        $client->jsonRequest('POST', '/api/admin/production/services', [...self::SERVICE, 'kind' => 'mix-master', 'pricing' => 'quote', 'price' => ['cents' => 0], 'deliveryDays' => null, 'revisions' => null]);

        self::assertResponseStatusCodeSame(201);
        $service = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['id', 'kind', 'title', 'description', 'pricing', 'price', 'deliveryDays', 'revisions', 'position'], array_keys($service));
        self::assertSame([...self::SERVICE, 'kind' => 'mix-master', 'pricing' => 'quote', 'price' => ['cents' => 0], 'deliveryDays' => null, 'revisions' => null, 'position' => 1], array_diff_key($service, ['id' => true]));
        self::assertTrue(Ulid::isValid(Json::string($service, 'id')));
        self::assertSame(Json::array(self::send($client, 'GET', '/api/admin/production'), 'services', 1), $service);
    }

    public function testTheOptionalFieldsMayBeLeftOut(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/admin/production/services', array_diff_key(self::SERVICE, ['deliveryDays' => true, 'revisions' => true, 'description' => true]));

        self::assertResponseStatusCodeSame(201);
        $service = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame([null, null, ['fr' => '', 'en' => '']], [$service['deliveryDays'], $service['revisions'], $service['description']]);
    }

    public function testAnInvalidServiceIsRefused(): void
    {
        $client = self::signedInClient();

        self::assertSame('A fixed-price service needs a price above 0 €: switch it to a quote to leave the price out.', self::refused($client, 'POST', '/api/admin/production/services', [...self::SERVICE, 'price' => ['cents' => 0]]));
        self::assertSame('A delivery takes between 1 and 120 days.', self::refused($client, 'POST', '/api/admin/production/services', [...self::SERVICE, 'deliveryDays' => 0]));
        self::assertSame('A service includes between 0 and 10 revisions.', self::refused($client, 'POST', '/api/admin/production/services', [...self::SERVICE, 'revisions' => 11]));
        self::assertSame('A service needs a title in at least one language.', self::refused($client, 'POST', '/api/admin/production/services', [...self::SERVICE, 'title' => ['fr' => '', 'en' => ' ']]));
        self::refused($client, 'POST', '/api/admin/production/services', array_diff_key(self::SERVICE, ['kind' => true, 'pricing' => true, 'price' => true]));
        self::refused($client, 'POST', '/api/admin/production/services', [...self::SERVICE, 'price' => ['cents' => -1]]);
        self::refused($client, 'POST', '/api/admin/production/services', [...self::SERVICE, 'kind' => 'karaoke']);
        self::refused($client, 'POST', '/api/admin/production/services', [...self::SERVICE, 'pricing' => 'free']);
        self::assertSame([], self::getContainer()->get(ProductionQueries::class)->services());
    }

    public function testAServiceIsEdited(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');

        $service = self::send($client, 'PUT', '/api/admin/production/services/'.$id, [...self::SERVICE, 'kind' => 'production', 'pricing' => 'quote', 'price' => ['cents' => 50000], 'revisions' => 0]);

        self::assertSame([$id, 'production', 'quote', 50000, 0], [Json::string($service, 'id'), Json::string($service, 'kind'), Json::string($service, 'pricing'), Json::int($service, 'price', 'cents'), Json::int($service, 'revisions')]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame('production', self::getContainer()->get(ProductionQueries::class)->services()[0]->kind);
        self::refused($client, 'PUT', '/api/admin/production/services/'.$id, [...self::SERVICE, 'revisions' => -1]);
    }

    public function testServicesAreReorderedAndDeleted(): void
    {
        $client = self::signedInClient();
        $a = Json::string(self::add($client), 'id');
        $b = Json::string(self::add($client), 'id');

        $services = self::send($client, 'PUT', '/api/admin/production/services/order', ['ids' => [$b, $a]]);

        self::assertSame([[$b, 0], [$a, 1]], array_map(static fn (mixed $service): array => [Json::string($service, 'id'), Json::int($service, 'position')], $services));
        self::assertSame(Json::array(self::send($client, 'GET', '/api/admin/production'), 'services'), $services);
        self::assertStringContainsString('every', self::refused($client, 'PUT', '/api/admin/production/services/order', ['ids' => [$a]]));

        $client->jsonRequest('DELETE', '/api/admin/production/services/'.$b);

        self::assertResponseStatusCodeSame(204);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame([$a], array_map(static fn ($service): string => $service->id, self::getContainer()->get(ProductionQueries::class)->services()));
    }

    public function testAServiceAddedAfterDeletesStillGoesLast(): void
    {
        $client = self::signedInClient();
        $a = Json::string(self::add($client), 'id');
        $b = Json::string(self::add($client), 'id');
        $c = Json::string(self::add($client), 'id');
        $client->jsonRequest('DELETE', '/api/admin/production/services/'.$a);
        $client->jsonRequest('DELETE', '/api/admin/production/services/'.$b);

        $d = Json::string(self::add($client), 'id');

        self::assertSame([[$c, 2], [$d, 3]], array_map(static fn (mixed $service): array => [Json::string($service, 'id'), Json::int($service, 'position')], Json::array(self::send($client, 'GET', '/api/admin/production'), 'services')));
    }

    public function testAServiceIsFoundByItsId(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');
        $queries = self::getContainer()->get(ProductionQueries::class);

        self::assertSame('Mixage', $queries->service($id)?->title->in('fr'));
        self::assertNull($queries->service(new Ulid()->toBase32()));
        self::assertNull($queries->service('not-an-id'));
        self::assertSame($id, $queries->page()->services[0]->id);
        self::assertSame([], $queries->page()->portfolio);
    }

    public function testAnUnknownServiceIsNotFound(): void
    {
        $client = self::signedInClient();
        $unknown = '/api/admin/production/services/'.new Ulid()->toBase32();

        self::refused($client, 'PUT', $unknown, self::SERVICE, 404);
        self::refused($client, 'DELETE', $unknown, status: 404);
    }

    public function testAWriteReturnsTheRefreshedProduction(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/admin/production/services', self::SERVICE, ['HTTP_X_REFRESH' => '["/api/admin/production"]']);

        self::assertResponseIsSuccessful();
        self::assertSame('Mixing', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'refreshed', '/api/admin/production', 'services', 0, 'title', 'en'));
    }

    public function testVisitorsAndOtherOriginsAreKeptOut(): void
    {
        $client = self::createClient();
        $client->jsonRequest('GET', '/api/admin/production');
        self::assertResponseStatusCodeSame(401);

        self::ensureKernelShutdown();
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/admin/production/services', self::SERVICE, ['HTTP_ORIGIN' => 'https://evil.test']);
        self::assertResponseStatusCodeSame(403);
    }

    /** @return array<mixed> */
    private static function add(KernelBrowser $client): array
    {
        $client->jsonRequest('POST', '/api/admin/production/services', self::SERVICE);
        self::assertResponseStatusCodeSame(201);

        return Json::decode((string) $client->getResponse()->getContent());
    }
}
