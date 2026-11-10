<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminShellTest extends WebTestCase
{
    use SignsInClient;

    public function testEveryAdminPathServesTheShellWithTheSession(): void
    {
        $client = self::signedInClient('louis@injust.test');

        $crawler = $client->request('GET', '/admin/anything/deeper');

        self::assertResponseIsSuccessful();
        self::assertSame('louis@injust.test', Json::string(Json::decode($crawler->filter('#app-session')->text()), 'email'));
        self::assertSame('{}', $crawler->filter('#app-preload')->text());
    }
}
