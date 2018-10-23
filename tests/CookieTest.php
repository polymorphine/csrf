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
use Polymorphine\Context\ResponseHeaders\Cookie;
use DateTime;

require_once __DIR__ . '/Fixtures/time-functions.php';


class CookieTest extends TestCase
{
    public function testInstantiation()
    {
        $this->assertInstanceOf(Cookie::class, $this->cookie('new'));
        $this->assertInstanceOf(Cookie::class, Cookie::session('new'));
        $this->assertInstanceOf(Cookie::class, Cookie::permanent('new'));
    }

    public function testPermanentConstructor()
    {
        $expectedHeader = 'name=value; Path=/; Expires=Sunday, 30-Apr-2023 00:00:00 UTC; MaxAge=157680000';
        $standardHeader = 'name=value; Path=/; Expires=Tuesday, 01-May-2018 02:00:00 UTC; MaxAge=7200';

        $directive = ['Expires' => $this->fixedDate(7200)];

        $this->assertSame($expectedHeader, Cookie::permanent('name', $directive)->valueHeader('value'));
        $this->assertSame($standardHeader, $this->cookie('name', $directive)->valueHeader('value'));
    }

    public function testSessionConstructor()
    {
        $expectedHeader = 'SessionId=1234567890; Path=/; HttpOnly; SameSite=Lax';
        $cookie = Cookie::session('SessionId');
        $this->assertSame($expectedHeader, $cookie->valueHeader('1234567890'));
    }

    /**
     * @dataProvider cookieData
     *
     * @param string $expectedHeader
     * @param array  $data
     */
    public function testConstructorDirectivesSetting(string $expectedHeader, array $data)
    {
        $name   = $data['name'];
        $cookie = $this->cookie($name, $data);
        $header = $data['value'] ? $cookie->valueHeader($data['value']) : $cookie->revokeHeader();
        $this->assertEquals($expectedHeader, $header);
    }

    public function testNamePropertyAccessor()
    {
        $this->assertSame('nameOfTheCookie', $this->cookie('nameOfTheCookie')->name());
    }

    /**
     * @dataProvider cookieData
     *
     * @param string $expectedHeader
     * @param array  $data
     */
    public function testNameChange(string $expectedHeader, array $data)
    {
        $name      = $data['name'];
        $oldCookie = $this->cookie($name, $data);
        $newCookie = $oldCookie->withName('new-' . $name);

        $this->assertNotSame($oldCookie, $newCookie);

        $header = $data['value'] ? $newCookie->valueHeader($data['value']) : $newCookie->revokeHeader();
        $this->assertEquals('new-' . $expectedHeader, $header);
    }

    public function testGivenSameName_WithNameMethod_ReturnsSameInstance()
    {
        $oldCookie = $this->cookie('name');
        $newCookie = $oldCookie->withName('name');

        $this->assertSame($oldCookie, $newCookie);
    }

    public function testGivenBothExpiryDirectives_MaxAgeTakesPrecedence()
    {
        $expectedHeader = 'name=value; Path=/; Expires=Tuesday, 01-May-2018 00:01:40 UTC; MaxAge=100';
        $directives = ['MaxAge'  => 100, 'Expires' => $this->fixedDate(3600)];
        $this->assertSame($expectedHeader, $this->cookie('name', $directives)->valueHeader('value'));
    }

    public function testSecureAndHostNamePrefixWillForceSecureDirective()
    {
        $header   = $this->cookie('__SECURE-name', ['Domain' => 'example.com', 'Path' => '/test'])->valueHeader('test');
        $expected = '__SECURE-name=test; Domain=example.com; Path=/test; Secure';
        $this->assertEquals($expected, $header);

        $header   = $this->cookie('__host-name')->valueHeader('test');
        $expected = '__host-name=test; Path=/; Secure';
        $this->assertEquals($expected, $header);
    }

    public function testHostNamePrefixWillForceRootPathAndDomain()
    {
        $header   = $this->cookie('__Host-name', ['Domain' => 'example.com', 'Path' => '/test'])->valueHeader('test');
        $expected = '__Host-name=test; Path=/; Secure';
        $this->assertEquals($expected, $header);
    }

    public function cookieData()
    {
        return [
            ['myCookie=; Path=/; Expires=Thursday, 02-May-2013 00:00:00 UTC; MaxAge=-157680000', [
                'name'  => 'myCookie',
                'value' => null
            ]],
            ['fullCookie=foo; Domain=example.com; Path=/directory/; Expires=Tuesday, 01-May-2018 01:00:00 UTC; MaxAge=3600; Secure; HttpOnly; SameSite=Lax', [
                'name'     => 'fullCookie',
                'value'    => 'foo',
                'Secure'   => true,
                'MaxAge'   => 3600,
                'HttpOnly' => true,
                'Domain'   => 'example.com',
                'Path'     => '/directory/',
                'SameSite' => true
            ]],
            ['fullCookie=foo; Domain=example.com; Path=/directory/; Expires=Tuesday, 01-May-2018 01:00:00 UTC; MaxAge=3600; Secure; HttpOnly; SameSite=Lax', [
                'name'     => 'fullCookie',
                'value'    => 'foo',
                'Secure'   => true,
                'Expires'  => $this->fixedDate(3600),
                'HttpOnly' => true,
                'Domain'   => 'example.com',
                'Path'     => '/directory/',
                'SameSite' => true
            ]]
        ];
    }

    private function fixedDate(int $secondsFromNow = 0): DateTime
    {
        return (new DateTime())->setTimestamp(\Polymorphine\Context\ResponseHeaders\time() + $secondsFromNow);
    }

    private function cookie(string $name, array $attributes = [])
    {
        return new Cookie($name, $attributes);
    }
}
