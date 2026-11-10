<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Beats;

use App\Application\Beats\BeatsQueries;
use App\Tests\Functional\Api\Music\CallsMusicApi;
use App\Tests\Support\Json;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class LicenceTiersApiTest extends WebTestCase
{
    use CallsMusicApi;

    private const array TIER = [
        'name' => ['fr' => 'Basique', 'en' => 'Basic'],
        'price' => ['cents' => 2900],
        'summary' => ['fr' => 'MP3, 10 000 écoutes', 'en' => 'MP3, 10,000 streams'],
        'deliverables' => ['mp3'],
        'streamCap' => 10000,
    ];

    public function testATierIsAddedAtTheEnd(): void
    {
        $client = self::signedInClient();
        self::add($client);

        $client->jsonRequest('POST', '/api/admin/beats/tiers', ['name' => ['fr' => ' Exclusive ', 'en' => ''], 'price' => ['cents' => 49900], 'deliverables' => ['stems', 'wav', 'mp3'], 'exclusive' => true]);

        self::assertResponseStatusCodeSame(201);
        $tier = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['id', 'name', 'price', 'summary', 'deliverables', 'exclusive', 'streamCap', 'copyCap', 'videoCap', 'position'], array_keys($tier));
        self::assertSame([
            'name' => ['fr' => 'Exclusive', 'en' => ''],
            'price' => ['cents' => 49900],
            'summary' => ['fr' => '', 'en' => ''],
            'deliverables' => ['mp3', 'wav', 'stems'],
            'exclusive' => true,
            'streamCap' => null,
            'copyCap' => null,
            'videoCap' => null,
            'position' => 1,
        ], array_diff_key($tier, ['id' => true]));
        self::assertTrue(Ulid::isValid(Json::string($tier, 'id')));
        self::assertSame(Json::array(self::send($client, 'GET', '/api/admin/beats'), 'tiers', 1), $tier);
        self::assertSame([10000, false], [Json::int(self::send($client, 'GET', '/api/admin/beats'), 'tiers', 0, 'streamCap'), Json::at(self::send($client, 'GET', '/api/admin/beats'), 'tiers', 0, 'exclusive')]);
    }

    public function testAnInvalidTierIsRefused(): void
    {
        $client = self::signedInClient();

        self::assertSame('A licence needs a name in at least one language.', self::refused($client, 'POST', '/api/admin/beats/tiers', [...self::TIER, 'name' => ['fr' => '', 'en' => ' ']]));
        self::assertSame('A licence needs a price above 0 €.', self::refused($client, 'POST', '/api/admin/beats/tiers', [...self::TIER, 'price' => ['cents' => 0]]));
        self::assertSame('A licence delivers at least one file: MP3, WAV or stems.', self::refused($client, 'POST', '/api/admin/beats/tiers', [...self::TIER, 'deliverables' => []]));
        self::assertSame('A limit is at least 1: leave it empty for no limit.', self::refused($client, 'POST', '/api/admin/beats/tiers', [...self::TIER, 'copyCap' => 0]));
        self::refused($client, 'POST', '/api/admin/beats/tiers', [...self::TIER, 'price' => ['cents' => -1]]);
        self::refused($client, 'POST', '/api/admin/beats/tiers', [...self::TIER, 'deliverables' => ['flac']]);
        self::assertSame([], self::getContainer()->get(BeatsQueries::class)->admin()->tiers);
    }

    public function testOnlyOneTierIsExclusive(): void
    {
        $client = self::signedInClient();
        $exclusive = Json::string(self::send($client, 'POST', '/api/admin/beats/tiers', [...self::TIER, 'exclusive' => true]), 'id');
        $basic = self::add($client);

        self::assertSame('Only one licence can be exclusive: switch the other one off first.', self::refused($client, 'POST', '/api/admin/beats/tiers', [...self::TIER, 'exclusive' => true]));
        self::refused($client, 'PUT', '/api/admin/beats/tiers/'.$basic, [...self::TIER, 'exclusive' => true]);
        self::assertTrue(Json::at(self::send($client, 'PUT', '/api/admin/beats/tiers/'.$exclusive, [...self::TIER, 'exclusive' => true, 'videoCap' => 1]), 'exclusive'));

        self::send($client, 'PUT', '/api/admin/beats/tiers/'.$exclusive, self::TIER);

        self::assertTrue(Json::at(self::send($client, 'PUT', '/api/admin/beats/tiers/'.$basic, [...self::TIER, 'exclusive' => true]), 'exclusive'));
    }

    public function testATierIsEdited(): void
    {
        $client = self::signedInClient();
        $id = self::add($client);

        $tier = self::send($client, 'PUT', '/api/admin/beats/tiers/'.$id, ['name' => ['fr' => 'Premium', 'en' => 'Premium'], 'price' => ['cents' => 7900], 'summary' => ['fr' => '', 'en' => 'WAV'], 'deliverables' => ['wav', 'mp3'], 'copyCap' => 5000, 'videoCap' => 2]);

        self::assertSame([$id, 'Premium', 7900, ['mp3', 'wav'], null, 5000, 2], [$tier['id'], Json::string($tier, 'name', 'en'), Json::int($tier, 'price', 'cents'), $tier['deliverables'], $tier['streamCap'], $tier['copyCap'], $tier['videoCap']]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame(7900, self::getContainer()->get(BeatsQueries::class)->admin()->tiers[0]->price->cents);
    }

    public function testTiersAreReorderedAndDeleted(): void
    {
        $client = self::signedInClient();
        $a = self::add($client);
        $b = self::add($client);

        $tiers = self::send($client, 'PUT', '/api/admin/beats/tiers/order', ['ids' => [$b, $a]]);

        self::assertSame([[$b, 0], [$a, 1]], array_map(static fn (mixed $tier): array => [Json::string($tier, 'id'), Json::int($tier, 'position')], $tiers));
        self::assertSame(Json::array(self::send($client, 'GET', '/api/admin/beats'), 'tiers'), $tiers);
        self::refused($client, 'PUT', '/api/admin/beats/tiers/order', ['ids' => [$a]]);

        $client->jsonRequest('DELETE', '/api/admin/beats/tiers/'.$b);

        self::assertResponseStatusCodeSame(204);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame([$a], array_map(static fn ($tier): string => $tier->id, self::getContainer()->get(BeatsQueries::class)->admin()->tiers));
    }

    public function testAnUnknownTierIsNotFound(): void
    {
        $client = self::signedInClient();
        $unknown = '/api/admin/beats/tiers/'.new Ulid()->toBase32();

        self::refused($client, 'PUT', $unknown, self::TIER, 404);
        self::refused($client, 'DELETE', $unknown, status: 404);
    }

    private static function add(KernelBrowser $client): string
    {
        $client->jsonRequest('POST', '/api/admin/beats/tiers', self::TIER);
        self::assertResponseStatusCodeSame(201);

        return Json::string(Json::decode((string) $client->getResponse()->getContent()), 'id');
    }
}
