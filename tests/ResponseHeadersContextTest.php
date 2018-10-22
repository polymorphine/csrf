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
use Polymorphine\Context\ResponseHeaders;
use Polymorphine\Context\Tests\Doubles\FakeRequestHandler;
use Polymorphine\Context\Tests\Doubles\FakeServerRequest;
use Polymorphine\Context\Tests\Doubles\FakeResponse;
use Psr\Http\Server\MiddlewareInterface;


class ResponseHeadersContextTest extends TestCase
{
    public function testInstantiation()
    {
        $this->assertInstanceOf(ResponseHeaders::class, $this->collection());
        $this->assertInstanceOf(MiddlewareInterface::class, $this->collection());
    }

    public function testProcessing()
    {
        $headers = [
            'Set-Cookie' => [
                'fullCookie=foo; Domain=example.com; Path=/directory/; Expires=Tuesday, 01-May-2018 01:00:00 UTC; MaxAge=3600; Secure; HttpOnly',
                'myCookie=; Expires=Thursday, 02-May-2013 00:00:00 UTC; MaxAge=-157680000'
            ],
            'X-Foo-Header' => ['foo'],
            'X-Bar-Header' => ['bar']
        ];

        $handler  = new FakeRequestHandler(new FakeResponse('test'));
        $response = $this->collection($headers)->process(new FakeServerRequest(), $handler);

        $this->assertSame('test', (string) $response->getBody());
        $this->assertSame($headers, $response->getHeaders());
    }

    public function testAddHeaderToCollection()
    {
        $expectedHeaders = [
            'Set-Cookie'   => ['fullCookie=foo; Domain=example.com; Path=/directory/; Expires=Tuesday, 01-May-2018 01:00:00 UTC; MaxAge=3600; Secure; HttpOnly'],
            'X-Foo-Header' => ['foo'],
            'X-Bar-Header' => ['bar']
        ];

        $collection = $this->collection($expectedHeaders);

        $cookieValue = 'myCookie=; Expires=Thursday, 02-May-2013 00:00:00 UTC; MaxAge=-157680000';
        $collection->add('Set-Cookie', $cookieValue);
        $expectedHeaders['Set-Cookie'][] = $cookieValue;

        $handler  = new FakeRequestHandler(new FakeResponse('test'));
        $response = $this->collection($expectedHeaders)->process(new FakeServerRequest(), $handler);
        $this->assertSame($expectedHeaders, $response->getHeaders());
    }

    public function testCookieSetupInstance()
    {
        $this->assertInstanceOf(ResponseHeaders\CookieSetup::class, $this->collection()->cookie('test'));
    }

    private function collection(array $headers = [])
    {
        return new ResponseHeaders\ResponseHeadersContext($headers);
    }
}
