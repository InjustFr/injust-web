<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Lessons;

use App\Application\Lessons\LessonsQueries;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class LessonsApiTest extends WebTestCase
{
    use SignsInClient;

    private const array OFFER = [
        'title' => ['fr' => 'Pack 5 cours', 'en' => 'Pack of 5 lessons'],
        'description' => ['fr' => 'Cinq cours en ligne.', 'en' => 'Five online lessons.'],
        'kind' => 'pack',
        'sessions' => 5,
        'format' => 'online',
        'minutes' => 60,
        'price' => ['cents' => 17500],
    ];

    public function testTheLessonsPageStartsEmpty(): void
    {
        $client = self::signedInClient();

        self::assertSame(['page' => ['intro' => ['fr' => '', 'en' => ''], 'faq' => ['fr' => '', 'en' => '']], 'offers' => []], self::send($client, 'GET', '/api/admin/lessons'));
    }

    public function testTheIntroAndFaqAreEdited(): void
    {
        $client = self::signedInClient();

        $page = self::send($client, 'PUT', '/api/admin/lessons/page', ['intro' => ['fr' => ' **Salut** ', 'en' => 'Hi'], 'faq' => ['fr' => '', 'en' => '## Where?']]);

        self::assertSame(['intro' => ['fr' => '**Salut**', 'en' => 'Hi'], 'faq' => ['fr' => '', 'en' => '## Where?']], $page);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $view = self::getContainer()->get(LessonsQueries::class)->page();
        self::assertSame(['Hi', '## Where?'], [$view->intro->in('en'), $view->faq->in('fr')]);
    }

    public function testAnOfferIsAddedAtTheEnd(): void
    {
        $client = self::signedInClient();
        self::add($client);

        $client->jsonRequest('POST', '/api/admin/lessons/offers', [...self::OFFER, 'kind' => 'single', 'sessions' => 1, 'format' => 'at-home']);

        self::assertResponseStatusCodeSame(201);
        $offer = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame([...self::OFFER, 'kind' => 'single', 'sessions' => 1, 'format' => 'at-home', 'position' => 1], array_diff_key($offer, ['id' => true]));
        self::assertTrue(Ulid::isValid(Json::string($offer, 'id')));
        self::assertSame(Json::array(self::send($client, 'GET', '/api/admin/lessons'), 'offers', 1), $offer);
    }

    public function testAnInvalidOfferIsRefused(): void
    {
        $client = self::signedInClient();

        self::assertStringContainsString('2', self::refused($client, 'POST', '/api/admin/lessons/offers', [...self::OFFER, 'sessions' => 1]));
        self::assertSame('A lesson lasts between 15 and 240 minutes, in steps of 15 minutes.', self::refused($client, 'POST', '/api/admin/lessons/offers', [...self::OFFER, 'minutes' => 50]));
        self::refused($client, 'POST', '/api/admin/lessons/offers', array_diff_key(self::OFFER, ['minutes' => true, 'sessions' => true, 'kind' => true, 'format' => true, 'price' => true]));
        self::refused($client, 'POST', '/api/admin/lessons/offers', [...self::OFFER, 'title' => ['fr' => '', 'en' => ' ']]);
        self::refused($client, 'POST', '/api/admin/lessons/offers', [...self::OFFER, 'price' => ['cents' => -1]]);
        self::refused($client, 'POST', '/api/admin/lessons/offers', [...self::OFFER, 'format' => 'by-mail']);
        self::assertSame([], self::getContainer()->get(LessonsQueries::class)->offers());
    }

    public function testAnOfferIsEdited(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');

        $offer = self::send($client, 'PUT', '/api/admin/lessons/offers/'.$id, [...self::OFFER, 'format' => 'at-teacher', 'minutes' => 90, 'price' => ['cents' => 20000]]);

        self::assertSame([$id, 'at-teacher', 90, 20000], [Json::string($offer, 'id'), Json::string($offer, 'format'), Json::int($offer, 'minutes'), Json::int($offer, 'price', 'cents')]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame(90, self::getContainer()->get(LessonsQueries::class)->offers()[0]->minutes);
        self::refused($client, 'PUT', '/api/admin/lessons/offers/'.$id, [...self::OFFER, 'kind' => 'single']);
    }

    public function testOffersAreReorderedAndDeleted(): void
    {
        $client = self::signedInClient();
        $a = Json::string(self::add($client), 'id');
        $b = Json::string(self::add($client), 'id');

        $offers = self::send($client, 'PUT', '/api/admin/lessons/offers/order', ['ids' => [$b, $a]]);

        self::assertSame([[$b, 0], [$a, 1]], array_map(static fn (mixed $offer): array => [Json::string($offer, 'id'), Json::int($offer, 'position')], $offers));
        self::assertSame(Json::array(self::send($client, 'GET', '/api/admin/lessons'), 'offers'), $offers);
        self::refused($client, 'PUT', '/api/admin/lessons/offers/order', ['ids' => [$a]]);

        $client->jsonRequest('DELETE', '/api/admin/lessons/offers/'.$b);

        self::assertResponseStatusCodeSame(204);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame([$a], array_map(static fn ($offer): string => $offer->id, self::getContainer()->get(LessonsQueries::class)->offers()));
    }

    public function testAnOfferIsFoundByItsId(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');
        $queries = self::getContainer()->get(LessonsQueries::class);

        self::assertSame('Pack of 5 lessons', $queries->offer($id)?->title->in('en'));
        self::assertNull($queries->offer(new Ulid()->toBase32()));
        self::assertNull($queries->offer('not-an-id'));
        self::assertSame($id, $queries->page()->offers[0]->id);
    }

    public function testAnUnknownOfferIsNotFound(): void
    {
        $client = self::signedInClient();
        $unknown = '/api/admin/lessons/offers/'.new Ulid()->toBase32();

        self::refused($client, 'PUT', $unknown, self::OFFER, 404);
        self::refused($client, 'DELETE', $unknown, status: 404);
    }

    public function testVisitorsCannotUseTheLessonsApi(): void
    {
        $client = self::createClient();

        $client->jsonRequest('GET', '/api/admin/lessons');

        self::assertResponseStatusCodeSame(401);
    }

    /** @return array<mixed> */
    private static function add(KernelBrowser $client): array
    {
        $client->jsonRequest('POST', '/api/admin/lessons/offers', self::OFFER);
        self::assertResponseStatusCodeSame(201);

        return Json::decode((string) $client->getResponse()->getContent());
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
    private static function refused(KernelBrowser $client, string $method, string $url, array $body = [], int $status = 422): string
    {
        $client->jsonRequest($method, $url, $body);
        self::assertResponseStatusCodeSame($status);
        $problem = Json::decode((string) $client->getResponse()->getContent());

        return \is_string($problem['detail'] ?? null) ? $problem['detail'] : '';
    }
}
