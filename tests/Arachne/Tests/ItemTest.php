<?php

namespace Arachne\Tests;
use Arachne\Item\Item;

class ItemTest extends \PHPUnit_Framework_TestCase
{
    public function testInitViaConstructor()
    {
        $data = ['id'=>'123abc', 'type'=>'foo123'];
        $item = new Item($data);
        $this->assertEquals('123abc', $item->id);
        $this->assertEquals('foo123', $item->type);
    }

    public function testAsArray()
    {
        $item = new Item();
        $item->id = '123abc';
        $item->type = 'foo123';
        $this->assertEquals(['id'=>'123abc', 'type'=>'foo123'], $item->asArray());
    }
    /**
     * @expectedException \DomainException
     */
    public function testGettingUnknownProperty()
    {
        $item = new Item();
        $foo = $item->foo;
    }

    /**
     * @expectedException \DomainException
     */
    public function testSettingUnknownProperty()
    {
        $item = new Item();
        $item->foo = 'bar';
    }

    /**
     * @expectedException \Respect\Validation\Exceptions\NestedValidationException
     */
    public function testDefaultIdValidationFail()
    {
        $item = new Item();
        $item->id = ['foo'=>'bar'];
        $item->validate();
    }

    /**
     * @expectedException \Respect\Validation\Exceptions\NestedValidationException
     */
    public function testDefaultTypeValidationFail()
    {
        $item = new Item();
        $item->type = ['foo'=>'bar'];
        $item->validate();
    }
}
