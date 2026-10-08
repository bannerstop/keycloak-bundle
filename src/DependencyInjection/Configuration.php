<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\DependencyInjection;

use Bannerstop\Keycloak\Jwt\Algorithm;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    #[\Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bannerstop_keycloak');
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->scalarNode('server_url')->isRequired()->cannotBeEmpty()->info('Base URL of the Keycloak server, e.g. https://sso.example.com')->end()
                ->scalarNode('realm')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('client_id')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('client_secret')->defaultNull()->info('Empty for public clients')->end()
                ->arrayNode('scopes')->scalarPrototype()->end()->defaultValue(['openid', 'email', 'profile'])->end()
                ->arrayNode('allowed_algorithms')
                    ->scalarPrototype()
                        ->validate()
                            ->ifNotInArray(Algorithm::names())
                            ->thenInvalid('Unsupported signature algorithm %s.')
                        ->end()
                    ->end()
                    ->defaultValue([Algorithm::RS256])
                ->end()
                ->integerNode('leeway')->min(0)->max(300)->defaultValue(30)->end()
                ->scalarNode('http_client')->defaultValue('Psr\Http\Client\ClientInterface')->info('Service id of a PSR-18 client')->end()
                ->scalarNode('request_factory')->defaultNull()->info('Service id of a PSR-17 request factory; defaults to the HTTP client if it is one, else nyholm/psr7')->end()
                ->scalarNode('stream_factory')->defaultNull()->info('Service id of a PSR-17 stream factory; same default as request_factory')->end()
                ->scalarNode('cache')->defaultValue('cache.app')->info('Service id of a PSR-16 or PSR-6 cache, null to disable')->end()
                ->append($this->loginNode())
                ->append($this->rolesNode())
                ->arrayNode('bearer')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('audience')->defaultNull()->info('Audience access tokens must carry; defaults to the client id')->end()
                    ->end()
                ->end()
                ->arrayNode('directory')
                    ->info('Separate confidential client for the user directory (admin API); defaults to the login client')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('client_id')->defaultNull()->end()
                        ->scalarNode('client_secret')->defaultNull()->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $directory): bool => null !== $directory['client_id'] && (null === $directory['client_secret'] || '' === $directory['client_secret']))
                        ->thenInvalid('The directory client needs a client_secret: the user directory works through its service account.')
                    ->end()
                ->end()
                ->scalarNode('user_provisioner')->defaultNull()->info('Service id of a UserProvisioner; defaults to stateless KeycloakUser objects')->end()
            ->end();

        return $treeBuilder;
    }

    private function loginNode(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('login')->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->variableNode('allowed_email_domains')
                    ->defaultValue([])
                    ->info('A list, or a comma separated string, e.g. "%env(KEYCLOAK_ALLOWED_EMAIL_DOMAINS)%"; empty allows every domain')
                    ->validate()
                        ->ifTrue(static fn (mixed $domains): bool => !is_string($domains) && (!is_array($domains) || [] !== array_filter($domains, static fn (mixed $domain): bool => !is_string($domain))))
                        ->thenInvalid('allowed_email_domains must be a list of domains or a comma separated string, got %s.')
                    ->end()
                ->end()
                ->booleanNode('require_verified_email')->defaultTrue()->end()
                ->scalarNode('default_target_path')->defaultValue('/')->end()
                ->scalarNode('failure_path')->defaultValue('/')->info('Path or route name to send users to when a login fails')->end()
                ->scalarNode('logout_target')->defaultValue('/')->info('Path or route name Keycloak sends users back to after logout; register it as post logout redirect URI')->end()
                ->arrayNode('authorization_parameters')
                    ->info('Extra parameters for every login, e.g. kc_idp_hint or prompt')
                    ->normalizeKeys(false)
                    ->scalarPrototype()->end()
                ->end()
            ->end();

        return $node;
    }

    private function rolesNode(): ArrayNodeDefinition
    {
        $node = new TreeBuilder('roles')->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('default_roles')->scalarPrototype()->end()->defaultValue(['ROLE_USER'])->end()
                ->arrayNode('realm_roles')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()->beforeNormalization()->castToArray()->end()->scalarPrototype()->end()->end()
                ->end()
                ->arrayNode('client_roles')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('client')
                    ->arrayPrototype()
                        ->normalizeKeys(false)
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()->beforeNormalization()->castToArray()->end()->scalarPrototype()->end()->end()
                    ->end()
                ->end()
                ->arrayNode('groups')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()->beforeNormalization()->castToArray()->end()->scalarPrototype()->end()->end()
                ->end()
            ->end();

        return $node;
    }
}
