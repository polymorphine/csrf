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
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;


class ResponseHeadersContext implements MiddlewareInterface, ResponseHeaders
{
    private $headers;

    public function __construct(array $defaultHeaders = [])
    {
        $this->headers = $defaultHeaders;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        foreach ($this->headers as $name => $headerLines) {
            $response = $this->addHeaderLines($response, $name, $headerLines);
        }

        return $response;
    }

    public function cookie(string $name, array $attributes = []): CookieSetup
    {
        return new CookieSetup($name, $this, $attributes);
    }

    public function add(string $name, string $header): void
    {
        $this->headers[$name][] = $header;
    }

    private function addHeaderLines(ResponseInterface $response, string $name, array $headerLines): ResponseInterface
    {
        foreach ($headerLines as $header) {
            $response = $response->withAddedHeader($name, $header);
        }

        return $response;
    }
}
