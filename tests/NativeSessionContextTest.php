<?php

/*
 * This file is part of Polymorphine/Context package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Context\Tests;

use PHPUnit\Framework\TestCase;
use Polymorphine\Context\Session;
use Polymorphine\Context\Tests\Doubles\FakeRequestHandler;
use Polymorphine\Context\Tests\Doubles\FakeResponse;
use Polymorphine\Context\Tests\Doubles\FakeResponseHeaders;
use Polymorphine\Context\Tests\Doubles\FakeServerRequest;
use Polymorphine\Context\Tests\Fixtures\SessionGlobalState;
use Psr\Http\Server\MiddlewareInterface;
use RuntimeException;

require_once __DIR__ . '/Fixtures/session-functions.php';
require_once __DIR__ . '/Fixtures/time-functions.php';


class NativeSessionContextTest extends TestCase
{
    public function tearDown()
    {
        SessionGlobalState::reset();
    }

    public function testInstantiation()
    {
        $context = $this->context($headers);
        $this->assertInstanceOf(MiddlewareInterface::class, $context);
        $this->assertInstanceOf(Session::class, $context);
    }

    public function testSessionInitialization()
    {
        $context = $this->context($headers, ['secure' => true]);
        $handler = $this->handler(function () use ($context) {
            $context->data()->set('foo', 'bar');
        });

        $context->process($this->request(), $handler);
        $this->assertSame(['foo' => 'bar'], SessionGlobalState::$data);

        $header = [SessionGlobalState::$name . '=DEFAULT_SESSION_ID; Path=/; Secure; HttpOnly; SameSite=Lax'];
        $this->assertSame($header, $headers->data['Set-Cookie']);
    }

    public function testSessionResume()
    {
        SessionGlobalState::$data = ['foo' => 'bar'];

        $context = $this->context($headers);
        $handler = $this->handler(function () use ($context) {
            $session = $context->data();
            $session->set('foo', $session->get('foo') . '-baz');
        });

        $context->process($this->request(true), $handler);
        $this->assertSame(['foo' => 'bar-baz'], SessionGlobalState::$data);

        $this->assertSame([], $headers->data);
    }

    public function testSessionRegenerateId()
    {
        SessionGlobalState::$data = ['foo' => 'bar'];

        $context = $this->context($headers, ['httpOnly' => false, 'sameSite' => 'Strict']);
        $handler = $this->handler(function () use ($context) {
            $context->resetContext();
        });

        $context->process($this->request(true), $handler);
        $this->assertSame(['foo' => 'bar'], SessionGlobalState::$data);

        $header = [SessionGlobalState::$name . '=REGENERATED_SESSION_ID; Path=/; SameSite=Strict'];
        $this->assertSame($header, $headers->data['Set-Cookie']);
    }

    public function testSessionDestroy()
    {
        SessionGlobalState::$data = ['foo' => 'bar'];

        $context = $this->context($headers);
        $handler = $this->handler(function () use ($context) {
            $context->data()->clear();
        });

        $context->process($this->request(true), $handler);
        $this->assertSame([], SessionGlobalState::$data);

        $header = [SessionGlobalState::$name . '=; Path=/; Expires=Thursday, 02-May-2013 00:00:00 UTC; MaxAge=-157680000'];
        $this->assertSame($header, $headers->data['Set-Cookie']);
    }

    public function testProcessingWhileSessionStarted_ThrowsException()
    {
        SessionGlobalState::$status = PHP_SESSION_ACTIVE;
        $context = $this->context($headers);

        $this->expectException(RuntimeException::class);
        $context->process($this->request(true), $this->handler());
    }

    public function testCallingSessionWithoutContextProcessing_ThrowsException()
    {
        $context = $this->context($headers);

        $this->expectException(RuntimeException::class);
        $context->data();
    }

    private function request($cookie = false)
    {
        $request = new FakeServerRequest();

        if ($cookie) {
            $request->cookies[SessionGlobalState::$name] = SessionGlobalState::$id;
        }

        return $request;
    }

    private function handler(callable $process = null)
    {
        return new FakeRequestHandler(new FakeResponse(), $process);
    }

    private function context(&$headersContext, $cookieOptions = [])
    {
        $headersContext = new FakeResponseHeaders();
        return new Session\NativeSessionContext($headersContext, $cookieOptions);
    }
}
