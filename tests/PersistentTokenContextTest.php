<?php declare(strict_types=1);

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
    /** @dataProvider safeMethods */
    public function test_ForSafeMethodRequests_TokenIsIgnored(string $method)
    {
        $this->assertResponse($this->guard(), $this->request($method));
        $this->assertResponse($this->guard($this->token('foo', 'x')), $this->request($method));
        $this->assertResponse($this->guard($this->token('foo', 'x')), $this->request($method, ['bar' => 'y']));
    }

    /** @dataProvider unsafeMethods */
    public function test_MissingSessionToken_ThrowsException(string $method)
    {
        $guard   = $this->guard();
        $request = $this->request($method);
        $this->expectException(Exception\TokenMismatchException::class);
        $guard->process($request, $this->handler());
    }

    /** @dataProvider unsafeMethods */
    public function test_MatchingRequestToken_ReturnsResponse(string $method)
    {
        $this->assertResponse($this->guard($this->token('foo', 'hash')), $this->request($method, ['foo' => 'hash']));
    }

    /** @dataProvider unsafeMethods */
    public function test_RequestTokenHashMismatch_ThrowsException(string $method)
    {
        $guard   = $this->guard($this->token('name', 'hash-0001'));
        $request = $this->request($method, ['name' => 'hash-foo']);
        $this->expectException(Exception\TokenMismatchException::class);
        $guard->process($request, $this->handler());
    }

    /** @dataProvider unsafeMethods */
    public function test_RequestTokenKeyMismatch_ThrowsException(string $method)
    {
        $guard   = $this->guard($this->token('foo', 'hash-0001'));
        $request = $this->request($method, ['bar' => 'hash-0001']);
        $this->expectException(Exception\TokenMismatchException::class);
        $guard->process($request, $this->handler());
    }

    public function test_OnTokenMismatch_SessionTokenIsCleared()
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

    public function test_ForValidRequest_SessionTokenIsPreserved()
    {
        $token   = $this->token('foo', 'bar');
        $session = new Doubles\FakeSessionStorage($token);
        $guard   = new PersistentTokenContext($session);
        $request = $this->request('POST', ['foo' => 'bar']);
        $guard->process($request, $this->handler());
        $this->assertTrue($session->tokenExists($token));
    }

    public function test_Token_IsGeneratedOnce()
    {
        $guard = $this->guard($this->token('name', 'hash'));
        $token = $guard->appSignature();

        $this->assertEquals('name', $token->name);
        $this->assertEquals('hash', $token->hash);

        $this->assertSame($token, $guard->appSignature());
    }

    public function test_ResetToken_RemovesToken()
    {
        $guard = $this->guard();
        $token = $guard->appSignature();
        $guard->resetToken();

        $newToken = $guard->appSignature();
        $this->assertInstanceOf(Token::class, $newToken);
        $this->assertInstanceOf(Token::class, $token);
        $this->assertNotEquals($token, $newToken);
    }

    public static function unsafeMethods(): iterable
    {
        return [['POST'], ['PUT'], ['DELETE'], ['PATCH'], ['TRACE'], ['CONNECT']];
    }

    public static function safeMethods(): iterable
    {
        return [['GET'], ['HEAD'], ['OPTIONS']];
    }

    private function assertResponse(PersistentTokenContext $guard, Doubles\FakeServerRequest $request)
    {
        $this->assertInstanceOf(ResponseInterface::class, $guard->process($request, $this->handler()));
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
