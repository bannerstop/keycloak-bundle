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
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;

final class TestKernel extends Kernel
{
    /** @var array<string, mixed> */
    private $keycloakConfig;

    /**
     * @param array<string, mixed> $keycloakConfig
     */
    public function __construct(array $keycloakConfig)
    {
        $this->keycloakConfig = $keycloakConfig;
        parent::__construct('test', true);
    }

    public static function usesAuthenticatorManager(): bool
    {
        return self::VERSION_ID >= 50300 && class_exists(AbstractAuthenticator::class);
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
            $session = self::VERSION_ID >= 50300 ? ['storage_factory_id' => 'session.storage.factory.mock_file'] : ['storage_id' => 'session.storage.mock_file'];
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'session' => $session,
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
        $modern = self::usesAuthenticatorManager();
        $main = [
            'pattern' => '^/',
            'provider' => 'keycloak',
            'logout' => ['path' => 'logout'],
        ];
        $api = [
            'pattern' => '^/api/',
            'provider' => 'keycloak',
            'stateless' => true,
        ];
        if ($modern) {
            $main['custom_authenticators'] = ['bannerstop_keycloak.authenticator'];
            $api['custom_authenticators'] = ['bannerstop_keycloak.bearer_authenticator'];
            $main['lazy'] = true;
        } else {
            $main['guard'] = ['authenticators' => ['bannerstop_keycloak.guard_authenticator']];
            $main['logout']['success_handler'] = 'bannerstop_keycloak.logout_success_handler';
            $main['anonymous'] = true;
            $api['guard'] = ['authenticators' => ['bannerstop_keycloak.bearer_guard_authenticator']];
            $api['anonymous'] = true;
        }

        $config = [
            'providers' => ['keycloak' => ['id' => 'bannerstop_keycloak.user_provider']],
            'firewalls' => ['api' => $api, 'main' => $main],
            'access_control' => [
                ['path' => '^/login', 'roles' => $modern ? 'PUBLIC_ACCESS' : 'IS_AUTHENTICATED_ANONYMOUSLY'],
                ['path' => '^/public', 'roles' => $modern ? 'PUBLIC_ACCESS' : 'IS_AUTHENTICATED_ANONYMOUSLY'],
                ['path' => '^/admin', 'roles' => 'ROLE_ADMIN'],
                ['path' => '^/', 'roles' => 'ROLE_USER'],
            ],
        ];
        if ($modern && self::VERSION_ID < 60000) {
            $config['enable_authenticator_manager'] = true;
        }

        return $config;
    }
}
