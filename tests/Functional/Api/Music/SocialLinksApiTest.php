<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Music;

use App\Application\Music\MusicQueries;
use App\Application\Music\OrderSocialLinks\OrderSocialLinks;
use App\Application\Music\OrderSocialLinks\OrderSocialLinksHandler;
use App\Application\Music\SocialLinkView;
use App\Tests\Support\Json;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class SocialLinksApiTest extends WebTestCase
{
    use CallsMusicApi;

    public function testALinkIsAdded(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/admin/music/links', ['network' => 'linktree', 'url' => 'https://linktr.ee/injust', 'label' => 'All links']);

        self::assertResponseStatusCodeSame(201);
        $link = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['network' => 'linktree', 'url' => 'https://linktr.ee/injust', 'label' => 'All links', 'position' => 0], array_diff_key($link, ['id' => true]));
        self::assertSame(1, Json::int(self::add($client), 'position'));
    }

    public function testALinkIsEdited(): void
    {
        $client = self::signedInClient();
        $id = Json::string(self::add($client), 'id');

        $link = self::send($client, 'PUT', '/api/admin/music/links/'.$id, ['network' => 'tiktok', 'url' => 'https://tiktok.com/@injust']);

        self::assertSame([$id, 'tiktok', 'https://tiktok.com/@injust', null], [Json::string($link, 'id'), Json::string($link, 'network'), Json::string($link, 'url'), Json::at($link, 'label')]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame('https://tiktok.com/@injust', self::getContainer()->get(MusicQueries::class)->socialLinks()[0]->url);
    }

    public function testAnInvalidLinkIsRefused(): void
    {
        $client = self::signedInClient();

        self::assertStringContainsString('https://', self::refused($client, 'POST', '/api/admin/music/links', ['network' => 'x', 'url' => 'http://x.com/injust']));
        self::refused($client, 'POST', '/api/admin/music/links', ['network' => 'myspace', 'url' => 'https://myspace.com/injust']);
        self::refused($client, 'POST', '/api/admin/music/links', ['network' => 'x', 'url' => 'https://x.com/'.str_repeat('a', 250)]);
        self::refused($client, 'POST', '/api/admin/music/links', ['network' => 'x', 'url' => 'https://x.com/injust', 'label' => str_repeat('a', 61)]);
        self::assertSame([], self::getContainer()->get(MusicQueries::class)->socialLinks());
    }

    public function testLinksAreReorderedAndDeleted(): void
    {
        $client = self::signedInClient();
        $a = Json::string(self::add($client), 'id');
        $b = Json::string(self::add($client), 'id');

        $links = self::send($client, 'PUT', '/api/admin/music/links/order', ['ids' => [$b, $a]]);
        self::assertSame([$b, $a], array_map(static fn (mixed $link): string => Json::string($link, 'id'), $links));
        self::assertSame(Json::array(self::send($client, 'GET', '/api/admin/music'), 'socialLinks'), $links);
        self::assertContainsOnlyInstancesOf(SocialLinkView::class, self::getContainer()->get(OrderSocialLinksHandler::class)(new OrderSocialLinks([$a, $b])));
        self::refused($client, 'PUT', '/api/admin/music/links/order', ['ids' => [$a, $a]]);

        $client->jsonRequest('DELETE', '/api/admin/music/links/'.$b);
        self::assertResponseStatusCodeSame(204);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame([$a], array_map(static fn ($link): string => $link->id, self::getContainer()->get(MusicQueries::class)->socialLinks()));
    }

    public function testAnUnknownLinkIsNotFound(): void
    {
        $client = self::signedInClient();
        $unknown = '/api/admin/music/links/'.new Ulid()->toBase32();

        self::refused($client, 'PUT', $unknown, ['network' => 'x', 'url' => 'https://x.com/injust'], 404);
        self::refused($client, 'DELETE', $unknown, status: 404);
    }

    public function testTheHomeListsEverythingInOrder(): void
    {
        $client = self::signedInClient();
        self::add($client);
        self::send($client, 'POST', '/api/admin/music/videos', ['url' => 'dQw4w9WgXcQ', 'title' => 'Riot']);
        self::send($client, 'PUT', '/api/admin/music/text', ['hooks' => ['Burn it down']]);

        $home = self::getContainer()->get(MusicQueries::class)->home(new \DateTimeImmutable());

        self::assertSame([[], 'Riot', ['Burn it down'], 'instagram'], [$home->releases, $home->videos[0]->title, $home->text->hooks, $home->socialLinks[0]->network]);
    }

    /** @return array<mixed> */
    private static function add(KernelBrowser $client): array
    {
        return self::send($client, 'POST', '/api/admin/music/links', ['network' => 'instagram', 'url' => 'https://instagram.com/injust']);
    }
}
