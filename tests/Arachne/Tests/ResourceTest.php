<?php

class ResourceTest extends \PHPUnit_Framework_TestCase
{
    protected $resource;

    public function setUp()
    {
        $request = new \Zend\Diactoros\Request('https://example.com/foo/bar?a=b#hash', 'GET');
        $this->resource = new \Arachne\Resource($request, 'foo');
    }

    public function testGetters()
    {
        $this->assertInstanceOf(\Zend\Diactoros\Request::class, $this->resource->getHttpRequest());
        $this->assertEquals('foo', $this->resource->getType());
        $this->assertEquals('https://example.com/foo/bar?a=b#hash', $this->resource->getUrl());
    }

    public function testHash()
    {
        $data = ['type'=> 'foo', 'meta'=> [], 'url'=> 'https://example.com/foo/bar?a=b#hash'];
        $hash = sha1(json_encode($data));
        $this->assertEquals($hash, $this->resource->getHash());
    }

    public function testMeta()
    {
        $meta = [
            'foo' => 'bar',
            'baz' => 'zzz'
        ];
        $this->resource->setMeta($meta);
        $this->assertEquals($meta, $this->resource->getMeta());

        $this->assertEquals('bar', $this->resource->getMeta('foo'));
        $this->assertEquals('xyz', $this->resource->getMeta('abc', 'xyz'));

        $this->resource->addMeta(['abc' => 'dfg']);
        $this->resource->addMeta(['baz' => 'xxx']);
        $this->assertEquals('dfg', $this->resource->getMeta('abc'));
        $this->assertEquals('xxx', $this->resource->getMeta('baz'));
    }

    public function testSerialization()
    {
        $serialized = serialize($this->resource);
        $unserialized = unserialize($serialized);
        $this->assertEquals($this->resource->getUrl(), $unserialized->getUrl());
        $this->assertEquals($this->resource->getType(), $unserialized->getType());
        $this->assertEquals($this->resource->getMeta(), $unserialized->getMeta());
        $this->assertEquals($this->resource->getHeaders(), $unserialized->getHeaders());
        $this->assertEquals($this->resource->getRequestTarget(), $unserialized->getRequestTarget());
    }

    public function testProxyCall()
    {
        $request = $this->getMockBuilder(\Zend\Diactoros\Request::class)
            ->disableOriginalConstructor()->setMethods(['getHeaders'])->getMock();
        $request->expects($this->once())->method('getHeaders');
        $resource = new \Arachne\Resource($request, 'foo');
        $resource->getHeaders();
    }
}
