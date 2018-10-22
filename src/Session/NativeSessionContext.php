<?php

/*
 * This file is part of Polymorphine/Context package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Context\Session;

use Polymorphine\Context\Session;
use Polymorphine\Context\ResponseHeaders;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;


class NativeSessionContext implements MiddlewareInterface, Session
{
    private $headers;
    private $cookieOptions;

    /** @var SessionData */
    private $sessionData;

    private $sessionName;
    private $sessionStarted = false;

    public function __construct(ResponseHeaders $headers, array $cookieOptions = [])
    {
        $this->headers       = $headers;
        $this->cookieOptions = $cookieOptions;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookies = $request->getCookieParams();

        $this->sessionName = session_name();
        if (isset($cookies[$this->sessionName])) { $this->start(); }
        $this->createStorage($_SESSION ?? []);

        $response = $handler->handle($request);
        $this->data()->commit();
        return $response;
    }

    public function data(): SessionData
    {
        if (!$this->sessionData) {
            throw new RuntimeException('Session context not started');
        }
        return $this->sessionData;
    }

    public function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            throw new RuntimeException('Session started in another context');
        }

        session_start();
        $this->sessionStarted = true;
    }

    public function resetContext(): void
    {
        if (!$this->sessionStarted) { return; }
        session_regenerate_id(true);
        $this->setSessionCookie();
    }

    public function commit(array $data): void
    {
        if (!$data) {
            $this->destroy();
            return;
        }

        if (!$this->sessionStarted) {
            $this->start();
            $this->setSessionCookie();
        }

        $_SESSION = $data;
        session_write_close();
    }

    protected function createStorage(array $data = []): void
    {
        $this->sessionData = new SessionData($this, $data);
    }

    protected function setSessionCookie(): void
    {
        $attributes = $this->cookieOptions + ['httpOnly' => true, 'sameSite' => 'Lax'];
        $this->headers->cookie($this->sessionName, $attributes)->value(session_id());
    }

    private function destroy(): void
    {
        if (!$this->sessionStarted) { return; }

        $this->headers->cookie($this->sessionName)->remove();
        session_destroy();
    }
}
