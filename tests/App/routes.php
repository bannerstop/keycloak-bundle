<?php

declare(strict_types=1);

use Bannerstop\KeycloakBundle\Tests\App\TestController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/** @var Symfony\Component\Routing\Loader\PhpFileLoader $loader */
$routes = new RouteCollection();
$routes->addCollection($loader->import('@BannerstopKeycloakBundle/Resources/config/routes.php'));
$routes->add('me', new Route('/me', ['_controller' => TestController::class . '::me']));
$routes->add('admin', new Route('/admin', ['_controller' => TestController::class . '::me']));
$routes->add('api_me', new Route('/api/me', ['_controller' => TestController::class . '::me']));
$routes->add('public', new Route('/public', ['_controller' => TestController::class . '::error']));
$routes->add('logout', new Route('/logout'));

return $routes;
