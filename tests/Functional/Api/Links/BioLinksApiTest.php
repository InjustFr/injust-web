<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Links;

use App\Application\Links\BioLinkView;
use App\Application\Links\LinksQueries;
use App\Application\Links\OrderBioLinks\OrderBioLinks;
use App\Application\Links\OrderBioLinks\OrderBioLinksHandler;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class BioLinksApiTest extends WebTestCase
{
    use SignsInClient;

    private const array LINK = [
        'label' => ['fr' => 'Écouter sur Spotify', 'en' => 'Listen on Spotify'],
        'url' => 'https://open.spotify.com/artist/injust',
        'icon' => 'spotify',
        'highlighted' => true,
        'visible' => true,
    ];

    public function testALinkIsAddedAtTheEnd(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/admin/links', self::LINK);

        self::assertResponseStatusCodeSame(201);
        $link = Json::decode((string) $client->getResponse()->getContent());
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', Json::string($link, 'id'));
        self::assertSame(self::LINK + ['clicks' => 0, 'position' => 0], array_diff_key($link, ['id' => true]));
        self::assertSame(1, Json::int(self::add($client), 'position'));
    }

    public function testTheDefaultsAreAVisiblePlainLinkWithoutIcon(): void
    {
        $client = self::signedInClient();

        $link = self::send($client, 'POST', '/api/admin/links', ['label' => ['fr' => '', 'en' => 'Merch'], 'url' => 'https://shop.injust.test']);

        self::assertSame([null, false, true], [Json::at($link, 'icon'), Json::at($link, 'highlighted'), Json::at($link, 'visible')]);
    }

    public function testALinkIsEdited(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');

        $link = self::send($client, 'PUT', '/api/admin/links/'.$id, ['label' => ['fr' => 'Boutique', 'en' => 'Shop'], 'url' => 'https://shop.injust.test', 'icon' => null, 'highlighted' => false, 'visible' => false]);

        self::assertSame([$id, ['fr' => 'Boutique', 'en' => 'Shop'], 'https://shop.injust.test', null, false, false], [Json::string($link, 'id'), Json::array($link, 'label'), Json::string($link, 'url'), Json::at($link, 'icon'), Json::at($link, 'highlighted'), Json::at($link, 'visible')]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(LinksQueries::class)->links()[0];
        self::assertSame(['Shop', 'https://shop.injust.test', null, false, false], [$stored->label->en, $stored->url, $stored->icon, $stored->highlighted, $stored->visible]);
    }

    public function testAnInvalidLinkIsRefused(): void
    {
        $client = self::signedInClient();

        self::assertStringContainsString('https://', self::refused($client, 'POST', '/api/admin/links', ['url' => 'http://injust.test'] + self::LINK));
        self::refused($client, 'POST', '/api/admin/links', ['url' => 'javascript:alert(1)'] + self::LINK);
        self::refused($client, 'POST', '/api/admin/links', ['url' => 'https://injust.test/'.str_repeat('a', 236)] + self::LINK);
        self::refused($client, 'POST', '/api/admin/links', ['icon' => 'myspace'] + self::LINK);
        self::assertStringContainsString('80', self::refused($client, 'POST', '/api/admin/links', ['label' => ['fr' => str_repeat('a', 81), 'en' => 'ok']] + self::LINK));
        self::refused($client, 'POST', '/api/admin/links', ['label' => ['fr' => ' ', 'en' => '']] + self::LINK);
        self::assertSame([], self::getContainer()->get(LinksQueries::class)->links());

        self::send($client, 'POST', '/api/admin/links', ['url' => 'https://injust.test/'.str_repeat('a', 235)] + self::LINK);
    }

    public function testLinksAreReorderedAndDeleted(): void
    {
        $client = self::signedInClient();
        $a = Json::string(self::add($client), 'id');
        $b = Json::string(self::add($client), 'id');

        $links = self::send($client, 'PUT', '/api/admin/links/order', ['ids' => [$b, $a]]);
        self::assertSame([$b, $a], array_map(static fn (mixed $link): string => Json::string($link, 'id'), $links));
        self::assertSame(self::send($client, 'GET', '/api/admin/links'), $links);
        self::assertContainsOnlyInstancesOf(BioLinkView::class, self::getContainer()->get(OrderBioLinksHandler::class)(new OrderBioLinks([$a, $b])));

        $client->jsonRequest('DELETE', '/api/admin/links/'.$b);
        self::assertResponseStatusCodeSame(204);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame([$a], array_map(static fn (BioLinkView $link): string => $link->id, self::getContainer()->get(LinksQueries::class)->links()));
    }

    public function testAnOrderMustListEveryLinkExactlyOnce(): void
    {
        $client = self::signedInClient();
        $a = Json::string(self::add($client), 'id');
        $b = Json::string(self::add($client), 'id');

        self::refused($client, 'PUT', '/api/admin/links/order', ['ids' => [$a, $a]]);
        self::refused($client, 'PUT', '/api/admin/links/order', ['ids' => [$a]]);
        self::refused($client, 'PUT', '/api/admin/links/order', ['ids' => [$a, $b, new Ulid()->toBase32()]]);
        self::refused($client, 'PUT', '/api/admin/links/order', ['ids' => []]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame([$a, $b], array_map(static fn (BioLinkView $link): string => $link->id, self::getContainer()->get(LinksQueries::class)->links()));
    }

    public function testVisitorsSeeOnlyTheVisibleLinksInOrder(): void
    {
        $client = self::signedInClient();
        $a = Json::string(self::add($client), 'id');
        $hidden = Json::string(self::send($client, 'POST', '/api/admin/links', ['visible' => false] + self::LINK), 'id');
        $c = Json::string(self::add($client), 'id');
        self::send($client, 'PUT', '/api/admin/links/order', ['ids' => [$c, $hidden, $a]]);

        $queries = self::getContainer()->get(LinksQueries::class);

        self::assertSame([$c, $a], array_map(static fn (BioLinkView $link): string => $link->id, $queries->visibleLinks()));
        self::assertSame([$c, $hidden, $a], array_map(static fn (BioLinkView $link): string => $link->id, $queries->links()));
    }

    public function testAnUnknownLinkIsNotFound(): void
    {
        $client = self::signedInClient();
        $unknown = '/api/admin/links/'.new Ulid()->toBase32();

        self::refused($client, 'PUT', $unknown, self::LINK, 404);
        self::refused($client, 'DELETE', $unknown, status: 404);
    }

    public function testAWriteReturnsTheRefreshedList(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/admin/links', self::LINK, ['HTTP_X_REFRESH' => '["/api/admin/links"]']);

        self::assertResponseIsSuccessful();
        self::assertSame('https://open.spotify.com/artist/injust', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'refreshed', '/api/admin/links', 0, 'url'));
    }

    public function testVisitorsAndOtherOriginsAreKeptOut(): void
    {
        $client = self::createClient();
        $client->jsonRequest('GET', '/api/admin/links');
        self::assertResponseStatusCodeSame(401);
        self::ensureKernelShutdown();

        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/admin/links', self::LINK, ['HTTP_ORIGIN' => 'https://evil.test']);
        self::assertResponseStatusCodeSame(403);
    }

    /** @return array<mixed> */
    private static function add(KernelBrowser $client): array
    {
        return self::send($client, 'POST', '/api/admin/links', self::LINK);
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
        $content = (string) $client->getResponse()->getContent();

        return '' === $content ? [] : Json::decode($content);
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
