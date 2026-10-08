<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Security\Guard;

use Bannerstop\KeycloakBundle\Security\LogoutRedirect;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;

/**
 * Keycloak logout for Symfony 4.4: set it as "success_handler" of the
 * firewall's logout.
 */
final class KeycloakLogoutSuccessHandler implements LogoutSuccessHandlerInterface
{
    /** @var LogoutRedirect */
    private $redirect;

    public function __construct(LogoutRedirect $redirect)
    {
        $this->redirect = $redirect;
    }

    public function onLogoutSuccess(Request $request): Response
    {
        return $this->redirect->response($request) ?? $this->redirect->fallback();
    }
}
