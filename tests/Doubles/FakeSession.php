<?php

/*
 * This file is part of Polymorphine/Context package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Context\Tests\Doubles;

use Polymorphine\Context\Session\SessionData;
use Polymorphine\Context\Session;


class FakeSession implements Session
{
    public $data;

    public function start(): void
    {
    }

    public function data(): SessionData
    {
    }

    public function resetContext(): void
    {
    }

    public function commit(array $data): void
    {
        $this->data = $data;
    }
}
