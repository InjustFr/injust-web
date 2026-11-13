<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Domain\Identity\UserRepository;
use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FakeAccounts;
use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthenticationTest extends WebTestCase
{
    use ActsAsUser;

    private const array EDITOR = ['sub' => 'account-1', 'email' => 'fern@example.com', 'name' => 'Fern', 'groups' => ['injust']];

    public function testTheAdminSendsToTheInjustAccount(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/');
        self::assertResponseRedirects('/login');
        $client->request('GET', '/login');

        $query = $this->authorizeQuery($client);
        self::assertSame(['code', 'injust', 'http://localhost/login/check', 'openid email profile', 'S256'], [$query['response_type'], $query['client_id'], $query['redirect_uri'], $query['scope'], $query['code_challenge_method']]);
    }

    public function testAnEditorSignsInAndLandsWhereTheyWereGoing(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/');
        $client->request('GET', '/login');

        $client->request('GET', '/login/check', ['state' => $this->authorizeQuery($client)['state'], 'code' => FakeAccounts::code(self::EDITOR)]);

        self::assertResponseRedirects('/admin/');
        $client->request('GET', '/admin/');
        self::assertResponseIsSuccessful();
        self::assertSame('Fern', self::getContainer()->get(UserRepository::class)->findByAccountId('account-1')?->displayName());
    }

    public function testAnAccountOutsideTheGroupIsTurnedAway(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');

        $client->request('GET', '/login/check', ['state' => $this->authorizeQuery($client)['state'], 'code' => FakeAccounts::code([...self::EDITOR, 'groups' => ['mossydew']])]);
        self::assertResponseRedirects('/login');
        $client->followRedirect();

        self::assertSame('Your Injust account does not have access to the Injust admin.', Json::string(self::props($client), 'error'));
        self::assertNull(self::getContainer()->get(UserRepository::class)->findByAccountId('account-1'));
    }

    public function testAnUnknownStateIsRefused(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');

        $client->request('GET', '/login/check', ['state' => 'forged', 'code' => FakeAccounts::code(self::EDITOR)]);
        $client->followRedirect();

        self::assertSame('This sign-in took too long or was opened in another tab, please try again.', Json::string(self::props($client), 'error'));
    }

    public function testSigningOutAlsoSignsOutOfTheInjustAccount(): void
    {
        $client = self::createClient();
        $client->loginUser(SecurityUser::fromUser(self::createUser()));
        $client->request('GET', '/admin/');
        $token = Json::string(Json::decode((string) $client->getCrawler()->filter('#app-session')->text()), 'logoutToken');

        $client->request('POST', '/logout', ['_csrf_token' => $token]);

        self::assertResponseRedirects('https://accounts.test/end-session?client_id=injust&post_logout_redirect_uri=http%3A%2F%2Flocalhost%2F');
        $client->request('GET', '/admin/');
        self::assertResponseRedirects('/login');
    }

    public function testAFrenchBrowserReturnsToTheRegisteredLogoutUri(): void
    {
        $client = self::createClient([], ['HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9']);
        $client->loginUser(SecurityUser::fromUser(self::createUser()));
        $client->request('GET', '/admin/');
        $token = Json::string(Json::decode((string) $client->getCrawler()->filter('#app-session')->text()), 'logoutToken');

        $client->request('POST', '/logout', ['_csrf_token' => $token]);

        self::assertResponseRedirects('https://accounts.test/end-session?client_id=injust&post_logout_redirect_uri=http%3A%2F%2Flocalhost%2F');
    }

    /**
     * @return array<string, string>
     */
    private function authorizeQuery(KernelBrowser $client): array
    {
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('https://accounts.test/authorize?', $location);
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);

        $strings = [];
        foreach ($query as $name => $value) {
            $strings[(string) $name] = \is_string($value) ? $value : '';
        }

        return $strings;
    }

    /**
     * @return array<mixed>
     */
    private static function props(KernelBrowser $client): array
    {
        return Json::decode((string) $client->getCrawler()->filter('#auth')->attr('data-props'));
    }
}
