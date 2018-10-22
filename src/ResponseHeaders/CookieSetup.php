<?php

/*
 * This file is part of Polymorphine/Context package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Context\ResponseHeaders;

use Polymorphine\Context\ResponseHeaders;
use LogicException;
use DateTime;


class CookieSetup
{
    private const MAX_TIME = 2628000;

    private $headers;
    private $name;

    private $minutes;
    private $domain;
    private $path     = '/';
    private $hostLock = false;
    private $secure   = false;
    private $httpOnly = false;
    private $sameSite;

    /**
     * Default cookie attributes can be overridden with $attributes array with following keys:
     * - domain   => (string) override default domain (current request domain)
     * - path     => (string) override default cookie path (root path)
     * - expires  => (int) minutes (empty not null value will set permanent cookie)
     * - httpOnly => (bool) true will override default (false)
     * - secure   => (bool) true will override default (false)
     * - sameSite => (string) 'Strict'|'Lax' ('Lax' will be set for any non-empty value).
     *
     * `__Secure-` name prefix will force secure cookie
     * `__Host-` name prefix will force (and lock) secure, current domain & root path cookie
     *
     * @param string          $name
     * @param ResponseHeaders $headers
     * @param array           $attributes
     */
    public function __construct(string $name, ResponseHeaders $headers, array $attributes = [])
    {
        $this->name    = $name;
        $this->headers = $headers;
        $this->parsePrefix($name);
        $this->setAttributes($attributes);
    }

    /**
     * Sets cookie header with provided value and attribute settings.
     *
     * @param string $value
     */
    public function value(string $value): void
    {
        $this->headers->add('Set-Cookie', $this->header($value));
    }

    /**
     * Sets header removing cookie with given params.
     */
    public function remove(): void
    {
        $this->minutes = -self::MAX_TIME;
        $this->headers->add('Set-Cookie', $this->header(null));
    }

    /**
     * Sets expiry datetime relative to current time in minutes.
     *
     * @param int $minutes
     *
     * @return CookieSetup
     */
    public function expires(int $minutes): CookieSetup
    {
        $this->minutes = $minutes;
        return $this;
    }

    /**
     * Sets expiry date that makes cookie practically permanent.
     *
     * @return CookieSetup
     */
    public function permanent(): CookieSetup
    {
        $this->minutes = self::MAX_TIME;
        return $this;
    }

    /**
     * Sets cookie domain.
     *
     * If cookie name has '__Host-' prefix this value cannot be changed and
     * LogicException will be thrown.
     *
     * @param string $domain
     *
     * @throws LogicException
     *
     * @return CookieSetup
     */
    public function domain(string $domain): CookieSetup
    {
        if ($this->hostLock) {
            throw new LogicException('Cannot set domain in cookies with `__Host-` name prefix');
        }

        $this->domain = $domain;
        return $this;
    }

    /**
     * Sets cookie path.
     *
     * If cookie name has '__Host-' prefix this value cannot be changed and
     * LogicException will be thrown.
     *
     * @param string $path
     *
     * @throws LogicException
     *
     * @return CookieSetup
     */
    public function path(string $path): CookieSetup
    {
        if ($this->hostLock) {
            throw new LogicException('Cannot set path in cookies with `__Host-` name prefix');
        }

        $this->path = $path;
        return $this;
    }

    /**
     * Sets HttpOnly cookie directive that orders browsers to deny access
     * to its content from client-side executable scripts.
     *
     * @return CookieSetup
     */
    public function httpOnly(): CookieSetup
    {
        $this->httpOnly = true;
        return $this;
    }

    /**
     * Sets Secure cookie directive that prevents cookie to be sent with
     * unencrypted protocol (http), so its content cannot be intercepted.
     *
     * @return CookieSetup
     */
    public function secure(): CookieSetup
    {
        $this->secure = true;
        return $this;
    }

    /**
     * Sets 'Strict' as SameSite attribute which orders browsers to sent
     * cookie back only when website is called directly.
     *
     * @return CookieSetup
     */
    public function sameSiteStrict(): CookieSetup
    {
        $this->setSameSiteDirective('Strict');
        return $this;
    }

    /**
     * Sets 'Lax' as SameSite attribute which orders browsers to sent
     * cookie back only when website is called directly or with GET method.
     *
     * @return CookieSetup
     */
    public function sameSiteLax(): CookieSetup
    {
        $this->setSameSiteDirective('Lax');
        return $this;
    }

    private function setSameSiteDirective(string $value): void
    {
        if ($this->sameSite) {
            throw new LogicException('SameSite cookie directive already set and cannot be changed');
        }
        $this->sameSite = $value;
    }

    private function parsePrefix(string $name): void
    {
        $secure = (stripos($name, '__Secure-') === 0);
        $host   = (stripos($name, '__Host-') === 0);
        if (!$host && !$secure) { return; }

        $this->secure = true;
        if ($secure) { return; }

        $this->hostLock = true;
    }

    private function setAttributes(array $attr): void
    {
        if (isset($attr['domain'])) { $this->domain($attr['domain']); }
        if (isset($attr['path'])) { $this->path($attr['path']); }
        if (isset($attr['expires'])) {
            $attr['expires'] ? $this->expires($attr['expires']) : $this->permanent();
        }
        if (!empty($attr['httpOnly'])) {
            $this->httpOnly = true;
        }
        if (!empty($attr['secure'])) {
            $this->secure = true;
        }
        if (!empty($attr['sameSite'])) {
            $this->setSameSiteDirective($attr['sameSite'] === 'Strict' ? 'Strict' : 'Lax');
        }
    }

    private function header($value): string
    {
        $header = $this->name . '=' . $value;

        if ($this->domain) {
            $header .= '; Domain=' . (string) $this->domain;
        }

        if ($this->path) {
            $header .= '; Path=' . $this->path;
        }

        if ($this->minutes) {
            $seconds = $this->minutes * 60;
            $expire  = (new DateTime())->setTimestamp(time() + $seconds)->format(DateTime::COOKIE);

            $header .= '; Expires=' . $expire;
            $header .= '; MaxAge=' . $seconds;
        }

        if ($this->secure) {
            $header .= '; Secure';
        }

        if ($this->httpOnly) {
            $header .= '; HttpOnly';
        }

        if ($this->sameSite) {
            $header .= '; SameSite=' . $this->sameSite;
        }

        return $header;
    }
}
