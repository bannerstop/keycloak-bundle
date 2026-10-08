<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Tests\App;

use Bannerstop\KeycloakBundle\BannerstopKeycloakBundle;
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

    #[\Override]
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new SecurityBundle(), new BannerstopKeycloakBundle()];
    }

    #[\Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[\Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/bannerstop-keycloak-bundle/' . md5((string) json_encode($this->keycloakConfig)) . '/cache';
    }

    #[\Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/bannerstop-keycloak-bundle/logs';
    }

    #[\Override]
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'session' => ['storage_factory_id' => 'session.storage.factory.mock_file', 'handler_id' => null, 'cookie_secure' => 'auto', 'cookie_samesite' => 'lax'],
                'router' => ['resource' => __DIR__ . '/routes.php', 'utf8' => true],
                'http_client' => ['enabled' => true],
            ] + self::frameworkDefaults());
            $container->loadFromExtension('bannerstop_keycloak', $this->keycloakConfig);
            $container->loadFromExtension('security', self::securityConfig());
            $container->register('logger', NullLogger::class);
            $container->register(TestController::class, TestController::class)
                ->setArguments([new Reference('security.token_storage'), new Reference('security.authentication_utils')])
                ->setPublic(true);
        });
    }

    /**
     * Options whose defaults change between Symfony versions, set so that the
     * test run shows no deprecations of the framework itself.
     *
     * @return array<string, mixed>
     */
    private static function frameworkDefaults(): array
    {
        if (self::VERSION_ID >= 70300 && self::VERSION_ID < 80000) {
            return ['property_info' => ['with_constructor_extractor' => true]];
        }
        if (self::VERSION_ID < 70000) {
            return [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
            ];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function securityConfig(): array
    {
        return [
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
        return $config;
    }
}
