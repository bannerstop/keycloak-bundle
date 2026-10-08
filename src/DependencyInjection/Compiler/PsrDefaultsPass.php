<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\DependencyInjection\Compiler;

use Bannerstop\KeycloakBundle\DependencyInjection\BannerstopKeycloakExtension;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires the PSR services the client needs, with sensible defaults:
 * the PSR-17 factories fall back to the HTTP client (Symfony's Psr18Client is
 * one) or to nyholm/psr7, and a PSR-6 pool is wrapped into a PSR-16 cache.
 *
 * @internal
 */
final class PsrDefaultsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('bannerstop_keycloak.psr')) {
            return;
        }
        $psr = $container->getParameter('bannerstop_keycloak.psr');

        if (!$container->has($psr['http_client'])) {
            throw new LogicException(sprintf('bannerstop_keycloak needs a PSR-18 HTTP client, service "%s" does not exist. Install symfony/http-client and nyholm/psr7, or set "http_client".', $psr['http_client']));
        }
        $container->setAlias(BannerstopKeycloakExtension::HTTP_CLIENT, $psr['http_client']);

        $this->factory($container, BannerstopKeycloakExtension::REQUEST_FACTORY, $psr['request_factory'], RequestFactoryInterface::class, $psr['http_client']);
        $this->factory($container, BannerstopKeycloakExtension::STREAM_FACTORY, $psr['stream_factory'], StreamFactoryInterface::class, $psr['http_client']);

        $cache = $psr['cache'];
        if (null === $cache || !$container->has($cache)) {
            return;
        }
        $class = $this->serviceClass($container, $cache);
        if (null !== $class && is_subclass_of($class, CacheInterface::class)) {
            $container->setAlias(BannerstopKeycloakExtension::CACHE, $cache);
        } elseif (null !== $class && is_subclass_of($class, CacheItemPoolInterface::class) && class_exists(Psr16Cache::class)) {
            $container->register(BannerstopKeycloakExtension::CACHE, Psr16Cache::class)->setArguments([new Reference($cache)]);
        }
    }

    private function factory(ContainerBuilder $container, string $id, ?string $configured, string $interface, string $httpClient): void
    {
        if (null !== $configured) {
            $container->setAlias($id, $configured);

            return;
        }
        if ($container->has($interface)) {
            $container->setAlias($id, $interface);

            return;
        }
        $clientClass = $this->serviceClass($container, $httpClient);
        if (null !== $clientClass && is_subclass_of($clientClass, $interface)) {
            $container->setAlias($id, $httpClient);

            return;
        }
        if (!class_exists(Psr17Factory::class)) {
            throw new LogicException(sprintf('bannerstop_keycloak needs a %s. Install nyholm/psr7 or configure one.', $interface));
        }
        $container->register($id, Psr17Factory::class);
    }

    private function serviceClass(ContainerBuilder $container, string $id): ?string
    {
        $class = $container->findDefinition($id)->getClass();
        if (null === $class) {
            return null;
        }
        $class = $container->getParameterBag()->resolveValue($class);

        return class_exists($class) || interface_exists($class) ? $class : null;
    }
}
