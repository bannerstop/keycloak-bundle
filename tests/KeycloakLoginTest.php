<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Tests;

use Bannerstop\Keycloak\Admin\UserDirectory;
use Bannerstop\Keycloak\Exception\HttpException;

/**
 * Runs against the Keycloak of the core package's tests-e2e (KEYCLOAK_URL).
 *
 * @group keycloak
 */
final class KeycloakLoginTest extends KeycloakTestCase
{
    protected function setUp(): void
    {
        if (false === getenv('KEYCLOAK_URL')) {
            self::markTestSkipped('Set KEYCLOAK_URL to run the tests against a real Keycloak.');
        }
    }

    public function testBrowserLoginAndLogout(): void
    {
        $browser = $this->browser(self::config());

        $browser->request('GET', '/login/keycloak?_target_path=/me');
        $authorizationUrl = (string) $browser->getResponse()->headers->get('Location');
        self::assertStringStartsWith(getenv('KEYCLOAK_URL') . '/realms/example/protocol/openid-connect/auth?', $authorizationUrl);

        $browser->request('GET', '/login/keycloak/callback?' . http_build_query(self::keycloakLogin($authorizationUrl)));
        self::assertSame('/me', $browser->getResponse()->headers->get('Location'));
        self::assertContains('REMEMBERME', array_map(static fn ($cookie): string => $cookie->getName(), $browser->getResponse()->headers->getCookies()), 'The firewall\'s remember_me applies to Keycloak logins.');

        $browser->request('GET', '/me');
        $me = json_decode((string) $browser->getResponse()->getContent(), true);
        self::assertSame('jane.doe@example.com', $me['email']);
        self::assertSame('Jane Doe', $me['name']);
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_EDITOR', 'ROLE_IT'], $me['roles']);

        $browser->request('GET', '/admin');
        self::assertSame(200, $browser->getResponse()->getStatusCode());

        $browser->request('GET', '/logout');
        $logout = (string) $browser->getResponse()->headers->get('Location');
        self::assertStringStartsWith(getenv('KEYCLOAK_URL') . '/realms/example/protocol/openid-connect/logout?', $logout);
        parse_str((string) parse_url($logout, PHP_URL_QUERY), $query);
        self::assertSame('http://localhost/', $query['post_logout_redirect_uri']);
        self::assertArrayHasKey('id_token_hint', $query);

        $browser->request('GET', '/me');
        self::assertSame(302, $browser->getResponse()->getStatusCode(), 'The local session is gone.');
    }

    public function testRolesAreOnlyGrantedWhenMapped(): void
    {
        $browser = $this->browser(self::config(['roles' => ['realm_roles' => ['admin' => 'ROLE_SOMETHING']]]));

        $browser->request('GET', '/login/keycloak');
        $browser->request('GET', '/login/keycloak/callback?' . http_build_query(self::keycloakLogin((string) $browser->getResponse()->headers->get('Location'))));
        $browser->request('GET', '/admin');

        self::assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testReturnsToThePageTheFirewallRemembered(): void
    {
        $browser = $this->browser(self::config());
        $browser->request('GET', '/login/keycloak');
        $authorizationUrl = (string) $browser->getResponse()->headers->get('Location');
        // what a form_login entry point leaves behind when it sends a user to the login page
        $session = $browser->getRequest()->getSession();
        $session->set('_security.main.target_path', 'http://localhost/admin?tab=2');
        $session->save();

        $browser->request('GET', '/login/keycloak/callback?' . http_build_query(self::keycloakLogin($authorizationUrl)));

        self::assertSame('http://localhost/admin?tab=2', $browser->getResponse()->headers->get('Location'));
    }

    public function testRememberedPagesOfOtherHostsAreIgnored(): void
    {
        $browser = $this->browser(self::config());
        $browser->request('GET', '/login/keycloak');
        $authorizationUrl = (string) $browser->getResponse()->headers->get('Location');
        $session = $browser->getRequest()->getSession();
        $session->set('_security.main.target_path', 'http://localhost.evil.example/');
        $session->save();

        $browser->request('GET', '/login/keycloak/callback?' . http_build_query(self::keycloakLogin($authorizationUrl)));

        self::assertSame('/', $browser->getResponse()->headers->get('Location'));
    }

    public function testExternalReturnPathsAreIgnored(): void
    {
        $browser = $this->browser(self::config());

        $browser->request('GET', '/login/keycloak?_target_path=' . rawurlencode('//evil.example/'));
        $browser->request('GET', '/login/keycloak/callback?' . http_build_query(self::keycloakLogin((string) $browser->getResponse()->headers->get('Location'))));

        self::assertSame('/', $browser->getResponse()->headers->get('Location'));
    }

    public function testFailedLoginsEndOnTheFailurePath(): void
    {
        $browser = $this->browser(self::config(['login' => ['allowed_email_domains' => ['example.org']]]));

        $browser->request('GET', '/login/keycloak');
        $browser->request('GET', '/login/keycloak/callback?' . http_build_query(self::keycloakLogin((string) $browser->getResponse()->headers->get('Location'))));
        self::assertSame('/public', $browser->getResponse()->headers->get('Location'));

        $browser->request('GET', '/public');
        self::assertSame(['error' => 'keycloak.login.not_allowed'], json_decode((string) $browser->getResponse()->getContent(), true));
    }

    public function testForgedCallbacksAreRejected(): void
    {
        $browser = $this->browser(self::config());

        $browser->request('GET', '/login/keycloak/callback?state=forged&code=forged');
        $browser->request('GET', '/public');

        self::assertSame(['error' => 'keycloak.login.state_mismatch'], json_decode((string) $browser->getResponse()->getContent(), true));
    }

    public function testBearerTokens(): void
    {
        $browser = $this->browser(self::config());

        $browser->request('GET', '/api/me', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . self::passwordGrantAccessToken()]);
        self::assertSame(200, $browser->getResponse()->getStatusCode());
        self::assertSame('jane.doe@example.com', json_decode((string) $browser->getResponse()->getContent(), true)['email']);

        $browser->request('GET', '/api/me', [], [], ['HTTP_AUTHORIZATION' => 'Bearer a.b.c']);
        self::assertSame(401, $browser->getResponse()->getStatusCode());
        self::assertSame('Bearer error="invalid_token"', $browser->getResponse()->headers->get('WWW-Authenticate'));
    }

    public function testDirectoryUsesTheLoginClientByDefault(): void
    {
        $this->browser(self::config());

        self::assertContains('jdoe', self::usernames($this->kernel->getContainer()->get(UserDirectory::class)));
    }

    public function testDirectoryCanUseItsOwnClient(): void
    {
        $this->browser(self::config(['directory' => ['client_id' => 'app-directory', 'client_secret' => 'app-directory-secret']]));

        self::assertContains('jdoe', self::usernames($this->kernel->getContainer()->get(UserDirectory::class)));
    }

    public function testDirectoryReallyUsesItsOwnClient(): void
    {
        $this->browser(self::config(['directory' => ['client_id' => 'app-directory', 'client_secret' => 'wrong-secret']]));

        $this->expectException(HttpException::class);

        self::usernames($this->kernel->getContainer()->get(UserDirectory::class));
    }

    /**
     * @return string[]
     */
    private static function usernames(UserDirectory $directory): array
    {
        $usernames = [];
        foreach ($directory->users() as $user) {
            $usernames[] = $user->getUsername();
        }

        return $usernames;
    }

    /**
     * Fills in the Keycloak login form like a browser and returns the callback query.
     *
     * @return array<string, string>
     */
    private static function keycloakLogin(string $authorizationUrl): array
    {
        $cookies = (string) tempnam(sys_get_temp_dir(), 'kc');
        $curl = curl_init($authorizationUrl);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies]);
        $html = (string) curl_exec($curl);
        self::assertSame(1, preg_match('/<form[^>]+id="kc-form-login"[^>]+action="([^"]+)"/', $html, $form), 'Keycloak shows its login form.');
        curl_setopt_array($curl, [
            CURLOPT_URL => html_entity_decode($form[1]),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['username' => 'jdoe', 'password' => 'jane-password']),
            CURLOPT_HEADER => true,
        ]);
        $response = (string) curl_exec($curl);
        curl_close($curl);
        self::assertSame(1, preg_match('/^Location: (\S+)/mi', $response, $location), 'Keycloak redirects back.');
        parse_str((string) parse_url($location[1], PHP_URL_QUERY), $query);

        return $query;
    }

    private static function passwordGrantAccessToken(): string
    {
        $curl = curl_init(getenv('KEYCLOAK_URL') . '/realms/example/protocol/openid-connect/token');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'password', 'client_id' => 'app', 'client_secret' => 'app-secret', 'username' => 'jdoe', 'password' => 'jane-password', 'scope' => 'openid']),
        ]);
        $response = json_decode((string) curl_exec($curl), true);
        curl_close($curl);

        return $response['access_token'];
    }
}
