<?php

/*
 * This file is part of Polymorphine/Context package.
 *
 * (c) Shudd3r <q3.shudder@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Polymorphine\Context\Tests;

use PHPUnit\Framework\TestCase;
use Polymorphine\Context\Session\SessionData;
use Polymorphine\Context\Tests\Doubles\FakeSession;


class SessionDataTest extends TestCase
{
    public function testInstantiation()
    {
        $this->assertInstanceOf(SessionData::class, $session = $this->storage());
    }

    public function testGetData()
    {
        $storage = $this->storage(['foo' => 'bar']);
        $this->assertSame('bar', $storage->get('foo'));
    }

    public function testSetData()
    {
        $storage = $this->storage();
        $this->assertFalse($storage->has('foo'));
        $storage->set('foo', 'bar');
        $this->assertTrue($storage->has('foo'));
        $this->assertSame('bar', $storage->get('foo'));
    }

    public function testSetOverwritesData()
    {
        $storage = $this->storage(['foo' => 'bar']);
        $storage->set('foo', 'baz');
        $this->assertSame('baz', $storage->get('foo'));
    }

    public function testRemoveData()
    {
        $storage = $this->storage(['foo' => 'bar', 'baz' => true]);
        $storage->remove('foo');
        $this->assertNull($storage->get('foo'));
    }

    public function testClearData()
    {
        $storage = new SessionData($manager = new FakeSession(), ['foo' => 'bar', 'baz' => true]);
        $storage->clear();
        $storage->commit();
        $this->assertSame([], $manager->data);
    }

    public function testDefaultForMissingValues()
    {
        $storage = $this->storage();
        $this->assertSame('default', $storage->get('foo', 'default'));
    }

    public function testCommitSession()
    {
        $data = [
            'foo' => 'bar',
            'bar' => 'baz'
        ];

        $storage = new SessionData($manager = new FakeSession(), $data);

        $data['fizz'] = 'buzz';
        $storage->set('fizz', 'buzz');

        $storage->commit();
        $this->assertSame($data, $manager->data);
    }

    public function testSettingNullDoesNotRemoveData()
    {
        $storage = new SessionData($manager = new FakeSession(), ['foo' => 500]);
        $this->assertTrue($storage->has('foo'));
        $storage->set('foo', null);
        $this->assertTrue($storage->has('foo'));
        $storage->commit();
        $this->assertTrue(array_key_exists('foo', $manager->data));
    }

    private function storage(array $data = [], $manager = null): SessionData
    {
        return new SessionData($manager ?? new FakeSession(), $data);
    }
}
