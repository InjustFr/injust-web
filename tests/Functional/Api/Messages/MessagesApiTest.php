<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Messages;

use App\Application\Contact\SendContactMessage\SendContactMessage;
use App\Application\Contact\SendContactMessage\SendContactMessageHandler;
use App\Domain\Contact\ContactTopic;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Uid\Ulid;

final class MessagesApiTest extends WebTestCase
{
    use SignsInClient;

    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testOpenMessagesAreListedNewestFirst(): void
    {
        $client = self::signedInClient();
        $old = self::receive('Old', '2026-11-01 10:00:00');
        $new = self::receive('New', '2026-11-02 10:00:00');

        $messages = self::send($client, 'GET', '/api/admin/messages');

        self::assertSame([$new, $old], array_map(static fn (mixed $message): string => Json::string($message, 'id'), $messages));
        self::assertSame([
            'id' => $new,
            'name' => 'New',
            'email' => 'new@example.test',
            'topic' => 'production',
            'offerRef' => null,
            'offerLabel' => null,
            'body' => 'Mix my demo please.',
            'locale' => 'fr',
            'receivedAt' => '2026-11-02T10:00:00+00:00',
            'handled' => false,
            'handledAt' => null,
        ], $messages[0]);
    }

    public function testAHandledMessageLeavesTheOpenListAndComesBack(): void
    {
        $client = self::signedInClient();
        $id = self::receive('Joe', '2026-11-01 10:00:00');
        $kept = self::receive('Kept', '2026-11-01 09:00:00');
        Clock::set(new MockClock('2026-11-03 08:30:00'));

        $message = self::send($client, 'PUT', '/api/admin/messages/'.$id.'/handled', ['handled' => true]);

        self::assertSame([$id, true, '2026-11-03T08:30:00+00:00'], [Json::string($message, 'id'), Json::at($message, 'handled'), Json::at($message, 'handledAt')]);
        self::assertSame([$kept], array_map(static fn (mixed $message): string => Json::string($message, 'id'), self::send($client, 'GET', '/api/admin/messages?status=open')));
        self::assertSame([$id, $kept], array_map(static fn (mixed $message): string => Json::string($message, 'id'), self::send($client, 'GET', '/api/admin/messages?status=all')));

        $message = self::send($client, 'PUT', '/api/admin/messages/'.$id.'/handled', ['handled' => false]);

        self::assertSame([false, null], [Json::at($message, 'handled'), Json::at($message, 'handledAt')]);
        self::assertCount(2, self::send($client, 'GET', '/api/admin/messages'));
    }

    public function testAMessageIsDeleted(): void
    {
        $client = self::signedInClient();
        $id = self::receive('Joe', '2026-11-01 10:00:00');

        $client->jsonRequest('DELETE', '/api/admin/messages/'.$id);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([], self::send($client, 'GET', '/api/admin/messages?status=all'));
    }

    public function testAnUnknownMessageIsNotFound(): void
    {
        $client = self::signedInClient();
        $unknown = '/api/admin/messages/'.new Ulid()->toBase32();

        $client->jsonRequest('PUT', $unknown.'/handled', ['handled' => true]);
        self::assertResponseStatusCodeSame(404);
        $client->jsonRequest('DELETE', $unknown);
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheHandledFlagIsRequired(): void
    {
        $client = self::signedInClient();
        $id = self::receive('Joe', '2026-11-01 10:00:00');

        $client->jsonRequest('PUT', '/api/admin/messages/'.$id.'/handled', []);

        self::assertResponseStatusCodeSame(422);
    }

    public function testAnUnknownStatusIsRefused(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('GET', '/api/admin/messages?status=archived');

        self::assertResponseStatusCodeSame(422);
    }

    public function testVisitorsCannotReadMessages(): void
    {
        $client = self::createClient();

        $client->jsonRequest('GET', '/api/admin/messages');

        self::assertResponseStatusCodeSame(401);
    }

    private static function receive(string $name, string $at): string
    {
        Clock::set(new MockClock($at));

        return self::getContainer()->get(SendContactMessageHandler::class)(new SendContactMessage($name, strtolower($name).'@example.test', ContactTopic::Production, 'Mix my demo please.', 'fr'))->id;
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
}
