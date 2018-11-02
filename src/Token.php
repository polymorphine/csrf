<?php

/*
 * This file is part of Polymorphine/Csrf package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Csrf;


class Token
{
    public $name;
    public $hash;

    public function __construct($name, $hash)
    {
        $this->name = $name;
        $this->hash = $hash;
    }
}
