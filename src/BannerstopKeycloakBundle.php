<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle;

use Bannerstop\KeycloakBundle\DependencyInjection\Compiler\PsrDefaultsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class BannerstopKeycloakBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new PsrDefaultsPass());
    }
}
