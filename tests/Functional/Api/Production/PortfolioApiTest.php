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

final class PortfolioApiTest extends WebTestCase
{
    use CallsMusicApi;

    private const array ITEM = [
        'title' => 'Know Your Rights',
        'artist' => 'The Clash',
        'roles' => ['mix', 'master'],
        'year' => 2024,
        'url' => 'https://listen.test/rights',
    ];

    public function testAnItemIsAddedAtTheEnd(): void
    {
        $client = self::signedInClient();
        self::add($client);

        $client->jsonRequest('POST', '/api/admin/production/portfolio', ['title' => ' Riot ', 'artist' => ' Joe ', 'roles' => ['recording']]);

        self::assertResponseStatusCodeSame(201);
        $item = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['id', 'title', 'artist', 'roles', 'year', 'cover', 'url', 'position'], array_keys($item));
        self::assertSame(['title' => 'Riot', 'artist' => 'Joe', 'roles' => ['recording'], 'year' => null, 'cover' => null, 'url' => null, 'position' => 1], array_diff_key($item, ['id' => true]));
        self::assertTrue(Ulid::isValid(Json::string($item, 'id')));
        self::assertSame(Json::array(self::send($client, 'GET', '/api/admin/production'), 'portfolio', 1), $item);
        self::assertSame(['title' => 'Know Your Rights', 'artist' => 'The Clash', 'roles' => ['mix', 'master'], 'year' => 2024, 'cover' => null, 'url' => 'https://listen.test/rights', 'position' => 0], array_diff_key(Json::array(self::send($client, 'GET', '/api/admin/production'), 'portfolio', 0), ['id' => true]));
    }

    public function testAnItemAddedAfterDeletesStillGoesLast(): void
    {
        $client = self::signedInClient();
        $a = Json::string(self::add($client), 'id');
        $b = Json::string(self::add($client), 'id');
        $c = Json::string(self::add($client), 'id');
        $client->jsonRequest('DELETE', '/api/admin/production/portfolio/'.$a);
        $client->jsonRequest('DELETE', '/api/admin/production/portfolio/'.$b);

        $d = Json::string(self::add($client), 'id');

        self::assertSame([[$c, 2], [$d, 3]], array_map(static fn (mixed $item): array => [Json::string($item, 'id'), Json::int($item, 'position')], Json::array(self::send($client, 'GET', '/api/admin/production'), 'portfolio')));
    }

    public function testAnInvalidItemIsRefused(): void
    {
        $client = self::signedInClient();

        self::assertSame('List at least one thing you did on this track.', self::refused($client, 'POST', '/api/admin/production/portfolio', [...self::ITEM, 'roles' => []]));
        self::assertSame('The year must be between 1990 and 2100.', self::refused($client, 'POST', '/api/admin/production/portfolio', [...self::ITEM, 'year' => 1989]));
        self::assertStringContainsString('https://', self::refused($client, 'POST', '/api/admin/production/portfolio', [...self::ITEM, 'url' => 'http://listen.test']));
        self::refused($client, 'POST', '/api/admin/production/portfolio', [...self::ITEM, 'title' => ' ']);
        self::refused($client, 'POST', '/api/admin/production/portfolio', [...self::ITEM, 'artist' => '']);
        self::refused($client, 'POST', '/api/admin/production/portfolio', [...self::ITEM, 'title' => str_repeat('a', 201)]);
        self::refused($client, 'POST', '/api/admin/production/portfolio', [...self::ITEM, 'artist' => str_repeat('a', 121)]);
        self::refused($client, 'POST', '/api/admin/production/portfolio', [...self::ITEM, 'url' => 'https://listen.test/'.str_repeat('a', 240)]);
        self::refused($client, 'POST', '/api/admin/production/portfolio', [...self::ITEM, 'roles' => ['karaoke']]);
        self::assertSame([], self::getContainer()->get(ProductionQueries::class)->portfolio());
        self::send($client, 'POST', '/api/admin/production/portfolio', [...self::ITEM, 'title' => str_repeat('a', 200), 'artist' => str_repeat('a', 120)]);
    }

    public function testAnItemIsEdited(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');

        $item = self::send($client, 'PUT', '/api/admin/production/portfolio/'.$id, ['title' => 'London Calling', 'artist' => 'The Clash', 'roles' => ['production'], 'year' => null, 'url' => '']);

        self::assertSame([$id, 'London Calling', ['production'], null, null], [Json::string($item, 'id'), Json::string($item, 'title'), Json::array($item, 'roles'), $item['year'], $item['url']]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame(['production'], self::getContainer()->get(ProductionQueries::class)->portfolio()[0]->roles);
        self::refused($client, 'PUT', '/api/admin/production/portfolio/'.$id, [...self::ITEM, 'year' => 2101]);
    }

    public function testItemsAreReorderedAndDeleted(): void
    {
        $client = self::signedInClient();
        $a = Json::string(self::add($client), 'id');
        $b = Json::string(self::add($client), 'id');

        $items = self::send($client, 'PUT', '/api/admin/production/portfolio/order', ['ids' => [$b, $a]]);

        self::assertSame([[$b, 0], [$a, 1]], array_map(static fn (mixed $item): array => [Json::string($item, 'id'), Json::int($item, 'position')], $items));
        self::assertSame(Json::array(self::send($client, 'GET', '/api/admin/production'), 'portfolio'), $items);
        self::refused($client, 'PUT', '/api/admin/production/portfolio/order', ['ids' => [$a, $a]]);

        $client->jsonRequest('DELETE', '/api/admin/production/portfolio/'.$b);

        self::assertResponseStatusCodeSame(204);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame([$a], array_map(static fn ($item): string => $item->id, self::getContainer()->get(ProductionQueries::class)->portfolio()));
    }

    public function testTheCoverIsUploadedReplacedAndRemoved(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');

        $first = Json::string(self::upload($client, '/api/admin/production/portfolio/'.$id.'/cover'), 'cover');
        $second = Json::string(self::upload($client, '/api/admin/production/portfolio/'.$id.'/cover'), 'cover');

        self::assertSame([false, true], [self::imageExists($client, $first), self::imageExists($client, $second)]);
        self::assertSame($second, Json::string(self::send($client, 'GET', '/api/admin/production'), 'portfolio', 0, 'cover'));
        self::assertNull(Json::at(self::send($client, 'DELETE', '/api/admin/production/portfolio/'.$id.'/cover'), 'cover'));
        self::assertFalse(self::imageExists($client, $second));
        self::assertNull(Json::at(self::send($client, 'GET', '/api/admin/production'), 'portfolio', 0, 'cover'));
        self::assertNull(Json::at(self::send($client, 'DELETE', '/api/admin/production/portfolio/'.$id.'/cover'), 'cover'));
    }

    public function testACoverThatIsNotAnImageIsRefused(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');

        self::assertSame(422, Json::int(self::upload($client, '/api/admin/production/portfolio/'.$id.'/cover', 'not-an-image.png'), 'status'));
        $client->request('POST', '/api/admin/production/portfolio/'.$id.'/cover', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testADeletedItemTakesItsCoverWithIt(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');
        $cover = Json::string(self::upload($client, '/api/admin/production/portfolio/'.$id.'/cover'), 'cover');

        self::send($client, 'DELETE', '/api/admin/production/portfolio/'.$id);

        self::assertFalse(self::imageExists($client, $cover));
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame([], self::getContainer()->get(ProductionQueries::class)->portfolio());
    }

    public function testAnUnknownItemIsNotFound(): void
    {
        $client = self::signedInClient();
        $unknown = '/api/admin/production/portfolio/'.new Ulid()->toBase32();

        self::refused($client, 'PUT', $unknown, self::ITEM, 404);
        self::refused($client, 'DELETE', $unknown, status: 404);
        self::refused($client, 'DELETE', $unknown.'/cover', status: 404);
        self::assertSame(404, Json::int(self::upload($client, $unknown.'/cover'), 'status'));
    }

    /** @return array<mixed> */
    private static function add(KernelBrowser $client): array
    {
        $client->jsonRequest('POST', '/api/admin/production/portfolio', self::ITEM);
        self::assertResponseStatusCodeSame(201);

        return Json::decode((string) $client->getResponse()->getContent());
    }
}
