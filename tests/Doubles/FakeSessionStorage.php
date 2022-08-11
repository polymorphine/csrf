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

use Polymorphine\Session\SessionStorage;


class FakeSessionStorage implements SessionStorage
{
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function userId(): ?string
    {
        return null;
    }

    public function newUserContext(string $userId = null): void
    {
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function get(string $key, $default = null)
    {
        return $this->has($key) ? $this->data[$key] : $default;
    }

    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function tokenExists(array $token): bool
    {
        return array_intersect_key($token, $this->data) === $token;
    }
}
