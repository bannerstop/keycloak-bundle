<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Tests;

use Bannerstop\Keycloak\KeycloakClient;
use Bannerstop\Keycloak\Login\LoginFlow;
use Bannerstop\Keycloak\Role\RoleMapper;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final class ContainerTest extends KeycloakTestCase
{
    public function testServicesAreWired(): void
    {
        $this->browser(self::config());
        $container = $this->kernel->getContainer();

        $client = $container->get(KeycloakClient::class);
        self::assertSame(self::config()['server_url'] . '/realms/example', $client->getConfig()->getIssuer());
        self::assertInstanceOf(LoginFlow::class, $container->get(LoginFlow::class));
        self::assertInstanceOf(RoleMapper::class, $container->get(RoleMapper::class));
        self::assertTrue($container->get('test.service_container')->has('bannerstop_keycloak.cache'), 'cache.app is used to cache discovery, keys and revocations.');
    }

    public function testAnonymousUsersAreSentToTheLogin(): void
    {
        $browser = $this->browser(self::config());

        $browser->request('GET', '/me?tab=1');

        self::assertSame(302, $browser->getResponse()->getStatusCode());
        $location = (string) $browser->getResponse()->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        self::assertSame('/login/keycloak', parse_url($location, PHP_URL_PATH));
        self::assertSame(['_target_path' => '/me?tab=1'], $query);
    }

    public function testApiRequestsWithoutTokenGetAChallenge(): void
    {
        $browser = $this->browser(self::config());

        $browser->request('GET', '/api/me');

        self::assertSame(401, $browser->getResponse()->getStatusCode());
        self::assertSame('Bearer', $browser->getResponse()->headers->get('WWW-Authenticate'));
    }

    public function testRejectsUnsupportedAlgorithms(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->browser(self::config(['allowed_algorithms' => ['HS256']]));
    }

    public function testDirectoryClientNeedsASecret(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The directory client needs a client_secret');

        $this->browser(self::config(['directory' => ['client_id' => 'app-directory']]));
    }

    public function testPagesWithoutKeycloakLoginWorkWithoutKeycloakSettings(): void
    {
        $_SERVER['BANNERSTOP_KEYCLOAK_TEST_EMPTY'] = '';
        $browser = $this->browser(self::config(['server_url' => '%env(BANNERSTOP_KEYCLOAK_TEST_EMPTY)%']));

        $browser->request('GET', '/public');
        self::assertSame(200, $browser->getResponse()->getStatusCode());

        $browser->request('GET', '/me');
        self::assertSame('/login/keycloak', parse_url((string) $browser->getResponse()->headers->get('Location'), PHP_URL_PATH));

        $browser->request('GET', '/logout');
        self::assertSame(302, $browser->getResponse()->getStatusCode());
    }

    public function testRejectsInvalidDomainLists(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->browser(self::config(['login' => ['allowed_email_domains' => [42]]]));
    }
}
