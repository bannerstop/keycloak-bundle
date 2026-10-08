<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\DependencyInjection;

use Bannerstop\Keycloak\Admin\UserDirectory;
use Bannerstop\Keycloak\KeycloakClient;
use Bannerstop\Keycloak\KeycloakConfig;
use Bannerstop\Keycloak\Login\LoginFlow;
use Bannerstop\Keycloak\Role\RoleMapper;
use Bannerstop\KeycloakBundle\Controller\LoginController;
use Bannerstop\KeycloakBundle\Security\BearerHandler;
use Bannerstop\KeycloakBundle\Security\ConfiguredEmailDomainPolicy;
use Bannerstop\KeycloakBundle\Security\KeycloakAuthenticator;
use Bannerstop\KeycloakBundle\Security\KeycloakBearerAuthenticator;
use Bannerstop\KeycloakBundle\Security\LoginHandler;
use Bannerstop\KeycloakBundle\Security\LogoutRedirect;
use Bannerstop\KeycloakBundle\Security\LogoutSubscriber;
use Bannerstop\KeycloakBundle\Security\SessionStateStore;
use Bannerstop\KeycloakBundle\Security\SessionTokenStore;
use Bannerstop\KeycloakBundle\User\KeycloakUserProvider;
use Bannerstop\KeycloakBundle\User\KeycloakUserProvisioner;
use Bannerstop\KeycloakBundle\User\UserProvisioner;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Service ids for security.yaml:
 *
 * - bannerstop_keycloak.authenticator / .bearer_authenticator
 * - bannerstop_keycloak.user_provider
 */
final class BannerstopKeycloakExtension extends Extension
{
    public const HTTP_CLIENT = 'bannerstop_keycloak.http_client';
    public const REQUEST_FACTORY = 'bannerstop_keycloak.request_factory';
    public const STREAM_FACTORY = 'bannerstop_keycloak.stream_factory';
    public const CACHE = 'bannerstop_keycloak.cache';

    /**
     * @param array<mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('bannerstop_keycloak.psr', [
            'http_client' => $config['http_client'],
            'request_factory' => $config['request_factory'],
            'stream_factory' => $config['stream_factory'],
            'cache' => $config['cache'],
        ]);

        $container->register('bannerstop_keycloak.config', KeycloakConfig::class)
            ->setFactory([KeycloakConfig::class, 'fromArray'])
            ->setArguments([[
                'server_url' => $config['server_url'],
                'realm' => $config['realm'],
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'scopes' => $config['scopes'],
                'allowed_algorithms' => $config['allowed_algorithms'],
                'leeway' => $config['leeway'],
            ]]);

        $container->register(KeycloakClient::class, KeycloakClient::class)
            ->setArguments([
                new Reference('bannerstop_keycloak.config'),
                new Reference(self::HTTP_CLIENT),
                new Reference(self::REQUEST_FACTORY),
                new Reference(self::STREAM_FACTORY),
                new Reference(self::CACHE, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);
        $directoryClient = KeycloakClient::class;
        if (null !== $config['directory']['client_id']) {
            $container->register('bannerstop_keycloak.directory_config', KeycloakConfig::class)
                ->setFactory([KeycloakConfig::class, 'fromArray'])
                ->setArguments([array_merge($container->getDefinition('bannerstop_keycloak.config')->getArgument(0), [
                    'client_id' => $config['directory']['client_id'],
                    'client_secret' => $config['directory']['client_secret'],
                ])]);
            $client = clone $container->getDefinition(KeycloakClient::class);
            $client->replaceArgument(0, new Reference('bannerstop_keycloak.directory_config'))->setPublic(false);
            $container->setDefinition('bannerstop_keycloak.directory_client', $client);
            $directoryClient = 'bannerstop_keycloak.directory_client';
        }
        $container->register(UserDirectory::class, UserDirectory::class)
            ->setArguments([new Reference($directoryClient)])
            ->setPublic(true);

        $container->register(RoleMapper::class, RoleMapper::class)
            ->setFactory([RoleMapper::class, 'fromArray'])
            ->setArguments([$config['roles']])
            ->setPublic(true);

        $policies = [];
        // A string can be an environment variable, whose value is only known at runtime.
        if (is_string($config['login']['allowed_email_domains']) || [] !== $config['login']['allowed_email_domains']) {
            $container->register('bannerstop_keycloak.email_domain_policy', ConfiguredEmailDomainPolicy::class)
                ->setArguments([$config['login']['allowed_email_domains'], $config['login']['require_verified_email']]);
            $policies[] = new Reference('bannerstop_keycloak.email_domain_policy');
        }
        $container->register('bannerstop_keycloak.state_store', SessionStateStore::class)
            ->setArguments([new Reference('request_stack')]);
        $container->register(LoginFlow::class, LoginFlow::class)
            ->setArguments([new Reference(KeycloakClient::class), new Reference('bannerstop_keycloak.state_store'), $policies])
            ->setPublic(true);

        $container->register(KeycloakUserProvisioner::class, KeycloakUserProvisioner::class);
        $container->setAlias(UserProvisioner::class, $config['user_provisioner'] ?? KeycloakUserProvisioner::class);
        $container->register('bannerstop_keycloak.user_provider', KeycloakUserProvider::class);
        $container->register('bannerstop_keycloak.token_store', SessionTokenStore::class);

        $container->register('bannerstop_keycloak.login_handler', LoginHandler::class)
            ->setArguments([
                new ServiceClosureArgument(new Reference(LoginFlow::class)),
                new Reference(RoleMapper::class),
                new Reference(UserProvisioner::class),
                new Reference('bannerstop_keycloak.token_store'),
                new Reference('router'),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config['login']['default_target_path'],
                $config['login']['failure_path'],
            ]);
        $container->register('bannerstop_keycloak.bearer_handler', BearerHandler::class)
            ->setArguments([
                new ServiceClosureArgument(new Reference(KeycloakClient::class)),
                new Reference(RoleMapper::class),
                new Reference(UserProvisioner::class),
                $config['bearer']['audience'],
            ]);

        $container->register('bannerstop_keycloak.login_controller', LoginController::class)
            ->setArguments([new Reference(LoginFlow::class), new Reference('router'), $config['login']['authorization_parameters']])
            ->setPublic(true)
            ->addTag('controller.service_arguments');

        $container->register('bannerstop_keycloak.logout_redirect', LogoutRedirect::class)
            ->setArguments([
                new ServiceClosureArgument(new Reference(KeycloakClient::class)),
                new Reference('bannerstop_keycloak.token_store'),
                new Reference('bannerstop_keycloak.login_handler'),
                $config['login']['logout_target'],
            ]);

        $container->register('bannerstop_keycloak.authenticator', KeycloakAuthenticator::class)
            ->setArguments([new Reference('bannerstop_keycloak.login_handler')]);
        $container->register('bannerstop_keycloak.bearer_authenticator', KeycloakBearerAuthenticator::class)
            ->setArguments([new Reference('bannerstop_keycloak.bearer_handler')]);
        $container->register('bannerstop_keycloak.logout_subscriber', LogoutSubscriber::class)
            ->setArguments([new Reference('bannerstop_keycloak.logout_redirect')])
            ->addTag('kernel.event_subscriber');
    }

    public function getAlias(): string
    {
        return 'bannerstop_keycloak';
    }
}
