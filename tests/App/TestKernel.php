<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Tests\App;

use Bannerstop\KeycloakBundle\BannerstopKeycloakBundle;
use Composer\InstalledVersions;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    /**
     * @param array<string, mixed> $keycloakConfig
     */
    public function __construct(
        private readonly array $keycloakConfig,
    ) {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new SecurityBundle(), new BannerstopKeycloakBundle()];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/bannerstop-keycloak-bundle/' . md5((string) json_encode($this->keycloakConfig)) . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/bannerstop-keycloak-bundle/logs';
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
                'router' => ['resource' => __DIR__ . '/routes.php', 'utf8' => true],
                'http_client' => ['enabled' => true],
            ]);
            $container->loadFromExtension('bannerstop_keycloak', $this->keycloakConfig);
            $container->loadFromExtension('security', self::securityConfig());
            $container->register('logger', NullLogger::class);
            $container->register(TestController::class, TestController::class)
                ->setArguments([new Reference('security.token_storage'), new Reference('security.authentication_utils')])
                ->setPublic(true);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function securityConfig(): array
    {
        $config = [
            'providers' => ['keycloak' => ['id' => 'bannerstop_keycloak.user_provider']],
            'firewalls' => [
                'api' => [
                    'pattern' => '^/api/',
                    'provider' => 'keycloak',
                    'stateless' => true,
                    'custom_authenticators' => ['bannerstop_keycloak.bearer_authenticator'],
                ],
                'main' => [
                    'pattern' => '^/',
                    'lazy' => true,
                    'provider' => 'keycloak',
                    'custom_authenticators' => ['bannerstop_keycloak.authenticator'],
                    'logout' => ['path' => 'logout'],
                ],
            ],
            'access_control' => [
                ['path' => '^/login', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/public', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/admin', 'roles' => 'ROLE_ADMIN'],
                ['path' => '^/', 'roles' => 'ROLE_USER'],
            ],
        ];
        // required on Symfony 5.4, deprecated from 6.2 on
        if (version_compare((string) InstalledVersions::getVersion('symfony/security-bundle'), '6.0', '<')) {
            $config['enable_authenticator_manager'] = true;
        }

        return $config;
    }
}
