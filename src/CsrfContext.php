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


interface CsrfContext
{
    /**
     * Returns existing or generates new Token.
     *
     * @return Token
     */
    public function appSignature(): Token;

    /**
     * Invalidates existing Token.
     *
     * @return void
     */
    public function resetToken(): void;
}
