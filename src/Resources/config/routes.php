<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('bannerstop_keycloak_login', '/login/keycloak')
        ->controller('bannerstop_keycloak.login_controller::start')
        ->methods(['GET']);
    $routes->add('bannerstop_keycloak_callback', '/login/keycloak/callback')
        ->controller('bannerstop_keycloak.login_controller::callback')
        ->methods(['GET']);
    $routes->add('bannerstop_keycloak_backchannel_logout', '/login/keycloak/backchannel-logout')
        ->controller('bannerstop_keycloak.backchannel_logout_controller')
        ->methods(['POST']);
};
