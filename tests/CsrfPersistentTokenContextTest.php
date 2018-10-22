<?php

/*
 * This file is part of Polymorphine/Context package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\App\Tests\Context;

use PHPUnit\Framework\TestCase;
use Polymorphine\Context\CsrfProtection\CsrfPersistentTokenContext;
use Polymorphine\Context\CsrfProtection\CsrfToken;
use Polymorphine\Context\CsrfProtection\Exception;
use Polymorphine\Context\Session\SessionData;
use Polymorphine\Context\Tests\Doubles\CallbackHandler;
use Polymorphine\Context\Tests\Doubles\FakeResponse;
use Polymorphine\Context\Tests\Doubles\FakeServerRequest;
use Polymorphine\Context\Tests\Doubles\FakeSession;


class CsrfPersistentTokenContextTest extends TestCase
{
    public function testInstantiation()
    {
        $session = new SessionData(new FakeSession(), $this->token('foo', 'bar'));
        $guard   = new CsrfPersistentTokenContext($session);
        $this->assertInstanceOf(CsrfPersistentTokenContext::class, $guard);
        $this->assertEquals('foo', $session->get(CsrfPersistentTokenContext::SESSION_CSRF_KEY));
        $this->assertEquals('bar', $session->get(CsrfPersistentTokenContext::SESSION_CSRF_TOKEN));
    }

    /**
     * @dataProvider safeMethods
     *
     * @param $method
     */
    public function testMatchingSkippedForSafeMethodRequests($method)
    {
        $handler = $this->handler();
        $guard   = $this->guard();
        $request = $this->request($method);
        $this->assertSame(200, $guard->process($request, $handler)->getStatusCode());

        $guard   = $this->guard($this->token('foo', 'bar'));
        $request = $this->request($method);
        $this->assertSame(200, $guard->process($request, $handler)->getStatusCode());

        $guard   = $this->guard($this->token('foo', 'bar'));
        $request = $this->request($method, ['baz' => 'something']);
        $this->assertSame(200, $guard->process($request, $handler)->getStatusCode());
    }

    /**
     * @dataProvider unsafeMethods
     *
     * @param $method
     */
    public function testMissingSessionToken_ThrowsException($method)
    {
        $handler = $this->handler();
        $guard   = $this->guard();
        $request = $this->request($method);
        $this->expectException(Exception\CsrfTokenMismatchException::class);
        $guard->process($request, $handler);
    }

    /**
     * @dataProvider unsafeMethods
     *
     * @param $method
     */
    public function testMatchingRequestToken_ReturnsOKResponse($method)
    {
        $token   = $this->token('name', 'hash');
        $handler = $this->handler();
        $guard   = $this->guard($token);
        $request = $this->request($method, ['name' => 'hash']);
        $this->assertSame(200, $guard->process($request, $handler)->getStatusCode());
    }

    /**
     * @dataProvider unsafeMethods
     *
     * @param $method
     */
    public function testRequestTokenHashMismatch_ThrowsException($method)
    {
        $token   = $this->token('name', 'hash-0001');
        $guard   = $this->guard($token);
        $request = $this->request($method, ['name' => 'hash-foo']);
        $this->expectException(Exception\CsrfTokenMismatchException::class);
        $guard->process($request, $this->handler());
    }

    /**
     * @dataProvider unsafeMethods
     *
     * @param $method
     */
    public function testRequestTokenKeyMismatch_ThrowsException($method)
    {
        $token   = $this->token('name', 'hash-0001');
        $guard   = $this->guard($token);
        $request = $this->request($method, ['something' => 'hash-0001']);
        $this->expectException(Exception\CsrfTokenMismatchException::class);
        $guard->process($request, $this->handler());
    }

    public function testSessionIsClearedOnTokenMismatch()
    {
        $session = new SessionData($manager = new FakeSession(), $this->token('foo', 'bar'));
        $guard   = new CsrfPersistentTokenContext($session);
        $request = $this->request('POST', ['something' => 'name']);
        try {
            $guard->process($request, $this->handler());
            $this->fail('Exception should be thrown');
        } catch (Exception\CsrfTokenMismatchException $e) {
            $this->assertFalse($session->has(CsrfPersistentTokenContext::SESSION_CSRF_KEY));
            $this->assertFalse($session->has(CsrfPersistentTokenContext::SESSION_CSRF_TOKEN));
            $session->commit();
            $this->assertSame([], $manager->data);
        }
    }

    public function testSessionTokenIsPreservedForValidRequest()
    {
        $session = new SessionData($manager = new FakeSession(), $this->token('foo', 'bar'));
        $guard   = new CsrfPersistentTokenContext($session);
        $request = $this->request('POST', ['foo' => 'bar']);
        $guard->process($request, $this->handler());
        $this->assertSame('foo', $session->get(CsrfPersistentTokenContext::SESSION_CSRF_KEY));
        $this->assertSame('bar', $session->get(CsrfPersistentTokenContext::SESSION_CSRF_TOKEN));

        $request = $this->request('GET');
        $guard->process($request, $this->handler());
        $this->assertSame('foo', $session->get(CsrfPersistentTokenContext::SESSION_CSRF_KEY));
        $this->assertSame('bar', $session->get(CsrfPersistentTokenContext::SESSION_CSRF_TOKEN));
    }

    public function testGenerateTokenGeneratesTokenOnce()
    {
        $guard = $this->guard($this->token('name', 'hash'));
        $token = $guard->appSignature();

        $this->assertEquals('name', $token->name);
        $this->assertEquals('hash', $token->hash);

        $this->assertSame($token, $guard->appSignature());
    }

    public function testResetTokenRemovesToken()
    {
        $guard = $this->guard();
        $token = $guard->appSignature();
        $guard->resetToken();

        $newToken = $guard->appSignature();
        $this->assertInstanceOf(CsrfToken::class, $newToken);
        $this->assertInstanceOf(CsrfToken::class, $token);
        $this->assertNotEquals($token, $newToken);
    }

    public function unsafeMethods()
    {
        return [['POST'], ['PUT'], ['DELETE'], ['PATCH'], ['TRACE'], ['CONNECT']];
    }

    public function safeMethods()
    {
        return [['GET'], ['HEAD'], ['OPTIONS']];
    }

    private function guard(array $token = []): CsrfPersistentTokenContext
    {
        return new CsrfPersistentTokenContext(new SessionData(new FakeSession(), $token));
    }

    private function handler()
    {
        return new CallbackHandler(function () { return new FakeResponse(); });
    }

    private function request(string $method = 'GET', array $token = [])
    {
        $request = new FakeServerRequest($method);

        $request->parsed = $token;
        return $request;
    }

    private function token($key, $value): array
    {
        return [
            CsrfPersistentTokenContext::SESSION_CSRF_KEY   => $key,
            CsrfPersistentTokenContext::SESSION_CSRF_TOKEN => $value
        ];
    }
}