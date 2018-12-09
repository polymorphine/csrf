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

use Polymorphine\Session\SessionContext;
use Polymorphine\Session\SessionStorage;


class FakeSession implements SessionContext
{
    public $data;

    public function start(): void
    {
    }

    public function storage(): SessionStorage
    {
    }

    public function reset(): void
    {
    }

    public function commit(array $data): void
    {
        $this->data = $data;
    }
}
