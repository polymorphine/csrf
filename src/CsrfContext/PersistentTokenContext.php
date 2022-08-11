<?php declare(strict_types=1);

/*
 * This file is part of Polymorphine/Csrf package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Csrf\CsrfContext;

use Polymorphine\Csrf\CsrfContext;
use Polymorphine\Csrf\Token;
use Polymorphine\Csrf\Exception;
use Polymorphine\Session\SessionStorage;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;


class PersistentTokenContext implements MiddlewareInterface, CsrfContext
{
    public const SESSION_CSRF_KEY   = 'csrf_key';
    public const SESSION_CSRF_TOKEN = 'csrf_token';

    private SessionStorage $session;
    private ?Token         $token;

    /**
     * @param SessionStorage $session
     */
    public function __construct(SessionStorage $session)
    {
        $this->session = $session;
    }

    /** {@inheritDoc} */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $unsafeMethods     = ['POST', 'PUT', 'DELETE', 'PATCH', 'TRACE', 'CONNECT'];
        $signatureRequired = in_array($request->getMethod(), $unsafeMethods, true);

        if ($signatureRequired) {
            $this->tokenMatch($request->getParsedBody());
        }

        return $handler->handle($request);
    }

    /** {@inheritDoc} */
    public function appSignature(): Token
    {
        return $this->token ??= $this->sessionToken() ?? $this->generateToken();
    }

    /** {@inheritDoc} */
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
        throw new Exception\TokenMismatchException();
    }

    private function sessionToken(): ?Token
    {
        if (!$this->session->has(self::SESSION_CSRF_KEY)) { return null; }

        return new Token(
            $this->session->get(self::SESSION_CSRF_KEY),
            $this->session->get(self::SESSION_CSRF_TOKEN)
        );
    }

    private function generateToken(): Token
    {
        $token = new Token(uniqid(), bin2hex(random_bytes(32)));
        $this->session->set(self::SESSION_CSRF_KEY, $token->name);
        $this->session->set(self::SESSION_CSRF_TOKEN, $token->hash);

        return $token;
    }
}
