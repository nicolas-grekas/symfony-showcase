<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Answers the API requests that carry no valid access token, or one lacking a scope, with the challenges of RFC 6750,
 * Section 3, which tell clients where to find the protected resource metadata (RFC 9728, Section 5.1).
 */
final class AccessTokenChallenge implements AuthenticationEntryPointInterface, AuthenticationFailureHandlerInterface, AccessDeniedHandlerInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        // no error code: the client sent no token to describe
        return $this->challenge(Response::HTTP_UNAUTHORIZED);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->challenge(Response::HTTP_UNAUTHORIZED, [
            'error' => 'invalid_token',
            'error_description' => $this->translator->trans($exception->getMessageKey(), $exception->getMessageData(), 'security'),
        ]);
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        $scopes = [];
        foreach ($accessDeniedException->getAttributes() as $attribute) {
            if (\is_string($attribute) && str_starts_with($attribute, AccessTokenScopeVoter::PREFIX)) {
                $scopes[] = substr($attribute, \strlen(AccessTokenScopeVoter::PREFIX));
            }
        }

        // the denials no scope took part in get the regular 403 response
        if (!$scopes) {
            return null;
        }

        return $this->challenge(Response::HTTP_FORBIDDEN, [
            'error' => 'insufficient_scope',
            'error_description' => 'The request requires higher privileges than provided by the access token.',
            'scope' => implode(' ', $scopes),
        ]);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function challenge(int $statusCode, array $parameters = []): Response
    {
        $parameters['resource_metadata'] = $this->urlGenerator->generate('api_protected_resource_metadata', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $values = [];
        foreach ($parameters as $name => $value) {
            $values[] = \sprintf('%s="%s"', $name, $value);
        }

        return new Response(null, $statusCode, ['WWW-Authenticate' => 'Bearer '.implode(',', $values)]);
    }
}
