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
        self::assertSame('http://keycloak:8080/realms/example', $client->getConfig()->getIssuer());
        self::assertInstanceOf(LoginFlow::class, $container->get(LoginFlow::class));
        self::assertInstanceOf(RoleMapper::class, $container->get(RoleMapper::class));
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
}
