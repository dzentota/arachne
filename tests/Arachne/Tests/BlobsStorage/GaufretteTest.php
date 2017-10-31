<?php

namespace Arachne\Tests\BlobsStorage;

use Arachne\BlobsStorage\Gaufrette;
use Gaufrette\Filesystem;
use Arachne\Resource;
use Psr\Log\NullLogger;
use Zend\Diactoros\Request;

class GaufretteTest extends \PHPUnit_Framework_TestCase
{
    protected static $filesystem;

    public function testDefaults()
    {
        $logger = new NullLogger();
        $filesystem = $this->getMockBuilder(Filesystem::class)->disableOriginalConstructor()->getMock();
        $gaufrette = new Gaufrette($logger, $filesystem);

        $this->assertEquals($filesystem, $gaufrette->getFilesystem());
        $this->assertFalse($gaufrette->getUseHashFilename());
        $this->assertEquals('index.dat', $gaufrette->getIndexFileName());
    }

    public function interfaceDataProvider()
    {
        return [
            [new Resource(new Request('http://localhost/foo/bar'), 'test'), 'test contents', false, 'localhost/foo/bar'],
            [new Resource(new Request('http://sub.domain.ltd/foo/bar/'), 'test'), 'sample test contents', true, 'sub.domain.ltd/foo/bar/index.dat'],
        ];
    }

    /**
     * @dataProvider interfaceDataProvider
     */
    public function testInterface($resource, $contents, $overwrite, $expectedPath)
    {
        $logger = new NullLogger();
        $filesystem = $this->getMockBuilder(Filesystem::class)->disableOriginalConstructor()->getMock();
        $filesystem->expects($this->once())->method('write')->with($expectedPath, $contents, $overwrite);
        $filesystem->expects($this->once())->method('keys')->will($this->returnValue(['one', 'two']));
        $filesystem->expects($this->once())->method('read')->with($expectedPath);
        $filesystem->expects($this->once())->method('has')->with($expectedPath);
        $filesystem->expects($this->once())->method('delete')->with($expectedPath);

        $gaufrette = new Gaufrette($logger, $filesystem);

        $gaufrette->keys();

        $path = $gaufrette->write($resource, $contents, $overwrite);
        $this->assertEquals($expectedPath, $path);

        $gaufrette->read($resource);

        $gaufrette->exists($resource);

        $gaufrette->delete($resource);

    }

    public function testClear()
    {
        $logger = new NullLogger();
        $filesystem = $this->getMockBuilder(Filesystem::class)->disableOriginalConstructor()->getMock();
        $filesystem->expects($this->once())->method('keys')->will($this->returnValue(['one', 'two']));
        $filesystem->expects($this->exactly(2))->method('delete')->willReturnOnConsecutiveCalls('one', 'two');

        $gaufrette = new Gaufrette($logger, $filesystem);
        $gaufrette->clear();
    }
}
