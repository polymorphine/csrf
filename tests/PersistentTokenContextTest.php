<?php

/*
 * This file is part of Polymorphine/Csrf package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Csrf\Tests;

use PHPUnit\Framework\TestCase;
use Polymorphine\Csrf\CsrfContext\PersistentTokenContext;
use Polymorphine\Csrf\Token;
use Polymorphine\Csrf\Exception;
use Psr\Http\Message\ResponseInterface;


class PersistentTokenContextTest extends TestCase
{
    /**
     * @dataProvider safeMethods
     *
     * @param $method
     */
    public function testMatchingSkippedForSafeMethodRequests($method)
    {
        $this->assertResponse($this->guard(), $this->request($method));
        $this->assertResponse($this->guard($this->token('foo', 'x')), $this->request($method));
        $this->assertResponse($this->guard($this->token('foo', 'x')), $this->request($method, ['bar' => 'y']));
    }

    /**
     * @dataProvider unsafeMethods
     *
     * @param $method
     */
    public function testMissingSessionToken_ThrowsException($method)
    {
        $guard   = $this->guard();
        $request = $this->request($method);
        $this->expectException(Exception\TokenMismatchException::class);
        $guard->process($request, $this->handler());
    }

    /**
     * @dataProvider unsafeMethods
     *
     * @param $method
     */
    public function testMatchingRequestToken_ReturnsResponse($method)
    {
        $this->assertResponse($this->guard($this->token('foo', 'hash')), $this->request($method, ['foo' => 'hash']));
    }

    /**
     * @dataProvider unsafeMethods
     *
     * @param $method
     */
    public function testRequestTokenHashMismatch_ThrowsException($method)
    {
        $guard   = $this->guard($this->token('name', 'hash-0001'));
        $request = $this->request($method, ['name' => 'hash-foo']);
        $this->expectException(Exception\TokenMismatchException::class);
        $guard->process($request, $this->handler());
    }

    /**
     * @dataProvider unsafeMethods
     *
     * @param $method
     */
    public function testRequestTokenKeyMismatch_ThrowsException($method)
    {
        $guard   = $this->guard($this->token('foo', 'hash-0001'));
        $request = $this->request($method, ['bar' => 'hash-0001']);
        $this->expectException(Exception\TokenMismatchException::class);
        $guard->process($request, $this->handler());
    }

    public function testSessionTokenIsClearedOnTokenMismatch()
    {
        $token   = $this->token('foo', 'bar');
        $session = new Doubles\FakeSessionStorage($token + ['other_data' => 'baz']);
        $guard   = new PersistentTokenContext($session);
        $request = $this->request('POST', ['something' => 'name']);
        try {
            $guard->process($request, $this->handler());
            $this->fail('Exception should be thrown');
        } catch (Exception\TokenMismatchException $e) {
            $this->assertFalse($session->tokenExists($token));
            $this->assertSame('baz', $session->get('other_data'));
        }
    }

    public function testSessionTokenIsPreservedForValidRequest()
    {
        $token   = $this->token('foo', 'bar');
        $session = new Doubles\FakeSessionStorage($token);
        $guard   = new PersistentTokenContext($session);
        $request = $this->request('POST', ['foo' => 'bar']);
        $guard->process($request, $this->handler());
        $this->assertTrue($session->tokenExists($token));

        $request = $this->request('GET');
        $guard->process($request, $this->handler());
        $this->assertTrue($session->tokenExists($token));
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
        $this->assertInstanceOf(Token::class, $newToken);
        $this->assertInstanceOf(Token::class, $token);
        $this->assertNotEquals($token, $newToken);
    }

    public function unsafeMethods(): array
    {
        return [['POST'], ['PUT'], ['DELETE'], ['PATCH'], ['TRACE'], ['CONNECT']];
    }

    public function safeMethods(): array
    {
        return [['GET'], ['HEAD'], ['OPTIONS']];
    }

    private function assertResponse(PersistentTokenContext $guard, Doubles\FakeServerRequest $request)
    {
        $handler = new Doubles\FakeRequestHandler(new Doubles\DummyResponse());
        $this->assertInstanceOf(ResponseInterface::class, $guard->process($request, $handler));
    }

    private function guard(array $token = []): PersistentTokenContext
    {
        return new PersistentTokenContext(new Doubles\FakeSessionStorage($token));
    }

    private function handler(): Doubles\FakeRequestHandler
    {
        return new Doubles\FakeRequestHandler(new Doubles\DummyResponse());
    }

    private function request(string $method, array $token = []): Doubles\FakeServerRequest
    {
        return new Doubles\FakeServerRequest($method, $token);
    }

    private function token($key, $value): array
    {
        return [
            PersistentTokenContext::SESSION_CSRF_KEY   => $key,
            PersistentTokenContext::SESSION_CSRF_TOKEN => $value
        ];
    }
}
