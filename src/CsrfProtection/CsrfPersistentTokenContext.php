<?php

/*
 * This file is part of Polymorphine/Context package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Context\CsrfProtection;

use Polymorphine\Context\CsrfProtection;
use Polymorphine\Context\Session\SessionData;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;


class CsrfPersistentTokenContext implements MiddlewareInterface, CsrfProtection
{
    public const SESSION_CSRF_KEY   = 'csrf_key';
    public const SESSION_CSRF_TOKEN = 'csrf_token';

    private $session;
    private $token;

    public function __construct(SessionData $session)
    {
        $this->session = $session;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $unsafeMethods     = ['POST', 'PUT', 'DELETE', 'PATCH', 'TRACE', 'CONNECT'];
        $signatureRequired = in_array($request->getMethod(), $unsafeMethods, true);

        if ($signatureRequired) {
            $this->tokenMatch($request->getParsedBody());
        }

        return $handler->handle($request);
    }

    public function appSignature(): CsrfToken
    {
        return $this->token ?: $this->token = $this->sessionToken() ?? $this->generateToken();
    }

    public function resetToken(): void
    {
        $this->session->remove(static::SESSION_CSRF_KEY);
        $this->token = null;
    }

    private function tokenMatch(array $payload): void
    {
        $token = $this->sessionToken();
        $valid = $token && isset($payload[$token->name]) && hash_equals($token->hash, $payload[$token->name]);

        if ($valid) { return; }

        $this->session->remove(self::SESSION_CSRF_KEY);
        $this->session->remove(self::SESSION_CSRF_TOKEN);
        throw new Exception\CsrfTokenMismatchException();
    }

    private function sessionToken(): ?CsrfToken
    {
        if (!$this->session->has(self::SESSION_CSRF_KEY)) { return null; }

        return new CsrfToken(
            $this->session->get(self::SESSION_CSRF_KEY),
            $this->session->get(self::SESSION_CSRF_TOKEN)
        );
    }

    private function generateToken(): CsrfToken
    {
        $token = new CsrfToken(uniqid(), bin2hex(random_bytes(32)));
        $this->session->set(self::SESSION_CSRF_KEY, $token->name);
        $this->session->set(self::SESSION_CSRF_TOKEN, $token->hash);

        return $token;
    }
}
