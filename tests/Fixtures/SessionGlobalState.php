<?php

/*
 * This file is part of Polymorphine/Context package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Context\Tests\Fixtures;


class SessionGlobalState
{
    public static $name   = 'PHPSESS';
    public static $id     = '';
    public static $status = PHP_SESSION_NONE;
    public static $data   = [];

    public static function reset()
    {
        self::$name   = 'PHPSESS';
        self::$id     = '';
        self::$status = PHP_SESSION_NONE;
        self::$data   = [];
    }
}
