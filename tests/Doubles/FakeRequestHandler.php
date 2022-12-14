<?php

/*
 * This file is part of Polymorphine/Csrf package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Csrf\Tests\Doubles;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Closure;


class FakeRequestHandler implements RequestHandlerInterface
{
    private ResponseInterface $response;
    private ?Closure          $sideEffect;

    public function __construct(ResponseInterface $response, callable $sideEffect = null)
    {
        $this->response   = $response;
        $this->sideEffect = $sideEffect;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->sideEffect) { ($this->sideEffect)(); }
        return $this->response;
    }
}
