<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Music;

use App\Application\Music\MusicQueries;
use App\Tests\Support\Json;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MusicTextApiTest extends WebTestCase
{
    use CallsMusicApi;

    private const array TEXT = [
        'tagline' => ['fr' => 'Punk de protestation', 'en' => 'Protest punk'],
        'manifesto' => ['fr' => '**Debout**', 'en' => '**Stand up**'],
        'bio' => ['fr' => '', 'en' => 'From the suburbs.'],
        'hooks' => ['Burn it down', ' ', 'Never kneel'],
    ];

    public function testTheTextsAndHooksAreSaved(): void
    {
        $client = self::signedInClient();

        $text = self::send($client, 'PUT', '/api/admin/music/text', self::TEXT);

        self::assertSame([
            'tagline' => ['fr' => 'Punk de protestation', 'en' => 'Protest punk'],
            'manifesto' => ['fr' => '**Debout**', 'en' => '**Stand up**'],
            'bio' => ['fr' => '', 'en' => 'From the suburbs.'],
            'hooks' => ['Burn it down', 'Never kneel'],
            'portrait' => null,
        ], $text);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(MusicQueries::class)->text();
        self::assertSame(['From the suburbs.', ['Burn it down', 'Never kneel']], [$stored->bio->in('fr'), $stored->hooks]);
    }

    public function testFieldsLeftOutAreEmptied(): void
    {
        $client = self::signedInClient();
        self::send($client, 'PUT', '/api/admin/music/text', self::TEXT);

        $text = self::send($client, 'PUT', '/api/admin/music/text', []);

        self::assertSame([['fr' => '', 'en' => ''], []], [Json::array($text, 'tagline'), Json::array($text, 'hooks')]);
    }

    public function testTooManyHooksAreRefused(): void
    {
        $client = self::signedInClient();

        self::assertStringContainsString('12', self::refused($client, 'PUT', '/api/admin/music/text', ['hooks' => array_fill(0, 13, 'Hook')] + self::TEXT));
        self::refused($client, 'PUT', '/api/admin/music/text', ['hooks' => [str_repeat('a', 121)]] + self::TEXT);
        self::refused($client, 'PUT', '/api/admin/music/text', ['hooks' => [['nested']]] + self::TEXT);
    }

    public function testThePortraitIsUploadedReplacedAndRemoved(): void
    {
        $client = self::signedInClient();
        self::send($client, 'PUT', '/api/admin/music/text', self::TEXT);

        $first = Json::string(self::upload($client, '/api/admin/music/portrait'), 'portrait');
        $text = self::upload($client, '/api/admin/music/portrait');
        $second = Json::string($text, 'portrait');

        self::assertSame('Protest punk', Json::string($text, 'tagline', 'en'));
        self::assertSame([false, true], [self::imageExists($client, $first), self::imageExists($client, $second)]);
        self::assertNull(Json::at(self::send($client, 'DELETE', '/api/admin/music/portrait'), 'portrait'));
        self::assertFalse(self::imageExists($client, $second));
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertNull(self::getContainer()->get(MusicQueries::class)->text()->portrait);
        self::assertNull(Json::at(self::send($client, 'DELETE', '/api/admin/music/portrait'), 'portrait'));
    }

    public function testAPortraitIsKeptWhenTheTextIsSavedFirst(): void
    {
        $client = self::signedInClient();
        $portrait = Json::string(self::upload($client, '/api/admin/music/portrait'), 'portrait');

        self::send($client, 'PUT', '/api/admin/music/text', self::TEXT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame($portrait, self::getContainer()->get(MusicQueries::class)->text()->portrait);
    }

    public function testAPortraitThatIsNotAnImageIsRefused(): void
    {
        $client = self::signedInClient();

        self::assertSame(422, Json::int(self::upload($client, '/api/admin/music/portrait', 'not-an-image.png'), 'status'));
        $client->request('POST', '/api/admin/music/portrait', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
    }
}
