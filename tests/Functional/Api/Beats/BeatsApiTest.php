<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Beats;

use App\Application\Beats\BeatsQueries;
use App\Tests\Functional\Api\Music\CallsMusicApi;
use App\Tests\Support\Json;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class BeatsApiTest extends WebTestCase
{
    use CallsMusicApi;
    use CallsBeatsApi;

    public function testABeatIsAddedHiddenAtTheEnd(): void
    {
        $client = self::signedInClient();
        self::addBeat($client);

        $client->jsonRequest('POST', '/api/admin/beats', ['title' => ' Night Shift ', 'bpm' => 90, 'key' => 'c-sharp-major', 'tags' => [' Dark ', 'TRAP']]);

        self::assertResponseStatusCodeSame(201);
        $beat = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['id', 'title', 'slug', 'bpm', 'key', 'keyLabel', 'tags', 'cover', 'preview', 'files', 'visible', 'soldExclusivelyAt', 'listed', 'position'], array_keys($beat));
        self::assertSame([
            'title' => 'Night Shift',
            'slug' => 'night-shift',
            'bpm' => 90,
            'key' => 'c-sharp-major',
            'keyLabel' => 'C# major',
            'tags' => ['dark', 'trap'],
            'cover' => null,
            'preview' => null,
            'files' => ['mp3' => null, 'wav' => null, 'stems' => null],
            'visible' => false,
            'soldExclusivelyAt' => null,
            'listed' => false,
            'position' => 1,
        ], array_diff_key($beat, ['id' => true]));
        self::assertTrue(Ulid::isValid(Json::string($beat, 'id')));
        self::assertSame(Json::array(self::send($client, 'GET', '/api/admin/beats'), 'beats', 1), $beat);
        self::assertSame(['page', 'beats', 'tiers'], array_keys(self::send($client, 'GET', '/api/admin/beats')));
    }

    public function testAnInvalidBeatIsRefused(): void
    {
        $client = self::signedInClient();

        self::assertSame('The tempo must be between 40 and 300 BPM.', self::refused($client, 'POST', '/api/admin/beats', [...self::BEAT, 'bpm' => 39]));
        self::assertSame('Keep up to 8 tags, each 1 to 24 characters.', self::refused($client, 'POST', '/api/admin/beats', [...self::BEAT, 'tags' => [str_repeat('a', 25)]]));
        self::refused($client, 'POST', '/api/admin/beats', [...self::BEAT, 'bpm' => 301]);
        self::refused($client, 'POST', '/api/admin/beats', [...self::BEAT, 'title' => ' ']);
        self::refused($client, 'POST', '/api/admin/beats', [...self::BEAT, 'title' => str_repeat('a', 121)]);
        self::refused($client, 'POST', '/api/admin/beats', [...self::BEAT, 'key' => 'h-minor']);
        self::refused($client, 'POST', '/api/admin/beats', [...self::BEAT, 'tags' => array_map(strval(...), range(1, 9))]);
        self::assertSame([], self::getContainer()->get(BeatsQueries::class)->admin()->beats);
        self::send($client, 'POST', '/api/admin/beats', [...self::BEAT, 'title' => str_repeat('a', 120)]);
    }

    public function testTwoBeatsCannotShareASlug(): void
    {
        $client = self::signedInClient();
        $first = self::addBeat($client);
        $second = Json::string(self::send($client, 'POST', '/api/admin/beats', [...self::BEAT, 'title' => 'Other']), 'id');

        self::assertSame('Another beat already uses the address “riot-gear”: choose another slug.', self::refused($client, 'POST', '/api/admin/beats', self::BEAT));
        self::refused($client, 'PUT', '/api/admin/beats/'.$second, [...self::BEAT, 'slug' => 'Riot Gear']);
        self::assertSame('riot-gear', Json::string(self::send($client, 'PUT', '/api/admin/beats/'.$first, [...self::BEAT, 'bpm' => 100]), 'slug'));
    }

    public function testABeatIsEdited(): void
    {
        $client = self::signedInClient();
        $id = self::addBeat($client);

        $beat = self::send($client, 'PUT', '/api/admin/beats/'.$id, ['title' => 'London', 'slug' => 'London Burning', 'bpm' => 300, 'key' => 'b-minor', 'tags' => [], 'visible' => true]);

        self::assertSame([$id, 'London', 'london-burning', 300, 'b-minor', 'B minor', [], true], [$beat['id'], $beat['title'], $beat['slug'], $beat['bpm'], $beat['key'], $beat['keyLabel'], $beat['tags'], $beat['visible']]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame('london-burning', self::getContainer()->get(BeatsQueries::class)->admin()->beats[0]->slug);
        self::refused($client, 'PUT', '/api/admin/beats/'.$id, [...self::BEAT, 'bpm' => 0]);
    }

    public function testBeatsAreReorderedAndDeleted(): void
    {
        $client = self::signedInClient();
        $a = self::addBeat($client);
        $b = Json::string(self::send($client, 'POST', '/api/admin/beats', [...self::BEAT, 'title' => 'Second']), 'id');

        $beats = self::send($client, 'PUT', '/api/admin/beats/order', ['ids' => [$b, $a]]);

        self::assertSame([[$b, 0], [$a, 1]], array_map(static fn (mixed $beat): array => [Json::string($beat, 'id'), Json::int($beat, 'position')], $beats));
        self::assertSame(Json::array(self::send($client, 'GET', '/api/admin/beats'), 'beats'), $beats);
        self::refused($client, 'PUT', '/api/admin/beats/order', ['ids' => [$a, $a]]);

        $client->jsonRequest('DELETE', '/api/admin/beats/'.$b);

        self::assertResponseStatusCodeSame(204);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame([$a], array_map(static fn ($beat): string => $beat->id, self::getContainer()->get(BeatsQueries::class)->admin()->beats));
    }

    public function testThePageTextIsEdited(): void
    {
        $client = self::signedInClient();

        self::assertSame(['intro' => ['fr' => '', 'en' => ''], 'faq' => ['fr' => '', 'en' => '']], Json::array(self::send($client, 'GET', '/api/admin/beats'), 'page'));

        $page = self::send($client, 'PUT', '/api/admin/beats/page', ['intro' => ['fr' => ' Salut ', 'en' => 'Hi'], 'faq' => ['fr' => '', 'en' => '**Q**']]);

        self::assertSame(['intro' => ['fr' => 'Salut', 'en' => 'Hi'], 'faq' => ['fr' => '', 'en' => '**Q**']], $page);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame($page, Json::array(self::send($client, 'GET', '/api/admin/beats'), 'page'));
        self::assertSame('Hi', self::getContainer()->get(BeatsQueries::class)->page()->intro->en);
    }

    public function testTheCoverIsUploadedReplacedAndRemoved(): void
    {
        $client = self::signedInClient();
        $id = self::addBeat($client);

        $first = Json::string(self::upload($client, '/api/admin/beats/'.$id.'/cover'), 'cover');
        $second = Json::string(self::upload($client, '/api/admin/beats/'.$id.'/cover'), 'cover');

        self::assertSame([false, true], [self::imageExists($client, $first), self::imageExists($client, $second)]);
        self::assertNull(Json::at(self::send($client, 'DELETE', '/api/admin/beats/'.$id.'/cover'), 'cover'));
        self::assertFalse(self::imageExists($client, $second));
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertNull(self::getContainer()->get(BeatsQueries::class)->admin()->beats[0]->cover);
        self::assertSame(422, Json::int(self::upload($client, '/api/admin/beats/'.$id.'/cover', 'not-an-image.png'), 'status'));
        $client->request('POST', '/api/admin/beats/'.$id.'/cover', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testAnUnknownBeatIsNotFound(): void
    {
        $client = self::signedInClient();
        $unknown = '/api/admin/beats/'.new Ulid()->toBase32();

        self::refused($client, 'PUT', $unknown, self::BEAT, 404);
        self::refused($client, 'DELETE', $unknown, status: 404);
        self::refused($client, 'DELETE', $unknown.'/cover', status: 404);
        self::refused($client, 'DELETE', $unknown.'/preview', status: 404);
        self::refused($client, 'DELETE', $unknown.'/files/wav', status: 404);
        self::assertSame(404, Json::int(self::upload($client, $unknown.'/cover'), 'status'));
        self::assertSame(404, Json::int(self::uploadAudio($client, $unknown.'/preview', self::MP3), 'status'));
        self::assertSame(404, Json::int(self::uploadAudio($client, $unknown.'/files/mp3', self::MP3), 'status'));
        self::assertSame(0, self::storedFiles());
    }

    public function testTheAdminApiNeedsASignedInUser(): void
    {
        $client = self::createClient();

        $client->jsonRequest('GET', '/api/admin/beats');

        self::assertResponseStatusCodeSame(401);
    }
}
