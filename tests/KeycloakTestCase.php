<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Tests;

use Bannerstop\KeycloakBundle\Tests\App\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Client;

abstract class KeycloakTestCase extends TestCase
{
    protected ?TestKernel $kernel = null;

    #[\Override]
    protected function tearDown(): void
    {
        if (null !== $this->kernel) {
            $this->kernel->shutdown();
        }
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected static function config(array $overrides = []): array
    {
        return array_replace_recursive([
            'server_url' => getenv('KEYCLOAK_URL') ?: 'http://keycloak:8080',
            'realm' => 'example',
            'client_id' => 'app',
            'client_secret' => 'app-secret',
            'login' => ['allowed_email_domains' => ['example.com'], 'failure_path' => 'public'],
            'roles' => [
                'realm_roles' => ['admin' => 'ROLE_ADMIN'],
                'client_roles' => ['app' => ['editor' => ['ROLE_EDITOR']]],
                'groups' => ['/staff/it' => 'ROLE_IT'],
            ],
            'bearer' => ['audience' => 'api'],
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return KernelBrowser|Client
     */
    protected function browser(array $config)
    {
        $this->kernel = new TestKernel($config);
        $this->kernel->boot();
        $browser = $this->kernel->getContainer()->get('test.client');
        $browser->followRedirects(false);

        return $browser;
    }
}
