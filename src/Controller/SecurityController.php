<?php

namespace App\Controller;

use App\Security\OidcAuthenticator;
use App\Security\OidcBackChannelLogout;
use App\Security\RecentAuthentication;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils, RecentAuthentication $recentAuthentication): Response
    {
        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            're_authentication' => $recentAuthentication->isReAuthenticating($request),
        ]);
    }

    #[Route('/oidc/start', name: 'oidc_login_start')]
    public function oidcLogin(Request $request, OidcAuthenticator $authenticator): Response
    {
        return $authenticator->start($request);
    }

    #[Route('/oidc/callback', name: 'oidc_login_callback')]
    public function oidcCallback(): never
    {
        throw new \LogicException('This method is never called: the OidcAuthenticator of the firewall handles this route.');
    }

    // the OpenID Connect provider posts the logout token of the sessions it ends (OpenID Connect Back-Channel Logout 1.0)
    #[Route('/oidc/backchannel-logout', name: 'oidc_backchannel_logout', methods: ['POST'])]
    public function oidcBackChannelLogout(Request $request, OidcBackChannelLogout $backChannelLogout): Response
    {
        $headers = ['Cache-Control' => 'no-store'];

        try {
            $backChannelLogout->logout($request->request->getString('logout_token'));
        } catch (AuthenticationException $e) {
            return $this->json(['error' => 'invalid_request', 'error_description' => $e->getMessage()], Response::HTTP_BAD_REQUEST, $headers);
        }

        return new Response('', Response::HTTP_OK, $headers);
    }
}
