<?php declare(strict_types=1);

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
    public string $name;
    public string $hash;

    /**
     * @param string $name
     * @param string $hash
     */
    public function __construct(string $name, string $hash)
    {
        $this->name = $name;
        $this->hash = $hash;
    }
}
