<?php

namespace Arachne\Tests\Document;
use Arachne\BlobsStorage\Gaufrette;
use Gaufrette\Filesystem;
use Psr\Log\NullLogger;
use Arachne\BlobsStorage\BlobsStorageInterface;
use Arachne\Document\DocumentInterface;
use Arachne\Document\Manager;
use Arachne\Document\InMemory as InMemoryStorage;
use Arachne\Resource;
use Zend\Diactoros\Request;


class ManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Manager
     */
    protected static $manager;

    public static function setUpBeforeClass()
    {
        $logger = new NullLogger();
        $documentStorage = new InMemoryStorage($logger);
        $blobsStorage = new Gaufrette($logger, new Filesystem(new \Gaufrette\Adapter\InMemory()));
        self::$manager = new Manager($documentStorage, $blobsStorage);
    }

    public function testGetStorage()
    {
        $this->assertInstanceOf(DocumentInterface::class, self::$manager->getDocStorage());
    }

    public function testGetBlobsStorage()
    {
        $this->assertInstanceOf(BlobsStorageInterface::class, self::$manager->getBlobsStorage());
    }

    public function testGetBoundDocument()
    {
        $manager = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->setMethods(['getDocument'])
            ->getMock();
        $resource = new Resource(new Request(), 'type');
        $resource->setMeta([
            'item_type' => 'test_type',
            'item_id' => 'test-id'
        ]);
        $manager->expects($this->once())->method('getDocument')->with('test_type', 'test-id');
        $manager->getBoundDocument($resource);
    }

    public function testGetDocument()
    {
        $logger = new NullLogger();
        $documentStorage = $this->createMock(InMemoryStorage::class);
        $documentStorage->expects($this->once())->method('get')->with('test_type', 'test-id');
        $blobsStorage = new Gaufrette($logger, new Filesystem(new \Gaufrette\Adapter\InMemory()));
        $manager = new Manager($documentStorage, $blobsStorage);
        $manager->getDocument('test_type', 'test-id');
    }

    public function testUpdateBoundDocument()
    {
        $manager = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->setMethods(['updateDocument'])
            ->getMock();
        $resource = new Resource(new Request(), 'type');
        $resource->setMeta([
            'item_type' => 'test_type',
            'item_id' => 'test-id'
        ]);
        $data = ['foo' => 'bar'];
        $manager->expects($this->once())->method('updateDocument')->with('test_type', 'test-id', $data);
        $manager->updateBoundDocument($resource, $data);
    }

    public function testUpdateDocument()
    {
        $logger = new NullLogger();
        $documentStorage = $this->createMock(InMemoryStorage::class);
        $data = ['foo' => 'bar'];
        $documentStorage->expects($this->once())->method('update')->with('test_type', 'test-id', $data);
        $blobsStorage = new Gaufrette($logger, new Filesystem(new \Gaufrette\Adapter\InMemory()));
        $manager = new Manager($documentStorage, $blobsStorage);
        $manager->updateDocument('test_type', 'test-id', $data);
    }

    public function testBindResourceToDoc()
    {
        $manager = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['bindResourceToDoc'])
            ->getMock();
        $meta = ['item_id' => 'test-id', 'item_type' => 'test_type'];
        $resource = $this->getMockBuilder(Resource::class)->disableOriginalConstructor()->setMethods(['setMeta'])->getMock();
        $resource->expects($this->once())->method('setMeta')->with($meta);
        $manager->bindResourceToDoc($resource, 'test_type', 'test-id');
    }

    public function testIsBoundToDoc()
    {
        $resource = new Resource(new Request(), 'type');
        $manager = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['isBoundToDoc'])
            ->getMock();

        $resource->setMeta([
            'item_type' => 'test_type',
            'item_id' => 'test-id'
        ]);

        $this->assertTrue($manager->isBoundToDoc($resource));

        $resource->setMeta([
            'item_id' => 'test-id'
        ]);

        $this->assertFalse($manager->isBoundToDoc($resource));

        $resource->setMeta([
            'item_id' => 'test-id'
        ]);

        $this->assertFalse($manager->isBoundToDoc($resource));

        $resource->setMeta([
        ]);

        $this->assertFalse($manager->isBoundToDoc($resource));

    }

    public function testCreateDocument()
    {
        $logger = new NullLogger();
        $documentStorage = $this->createMock(InMemoryStorage::class);
        $data = ['foo' => 'bar'];
        $documentStorage->expects($this->once())->method('create')->with('test_type', 'test-id', $data);
        $blobsStorage = new Gaufrette($logger, new Filesystem(new \Gaufrette\Adapter\InMemory()));
        $manager = new Manager($documentStorage, $blobsStorage);
        $manager->createDocument('test_type', 'test-id', $data);
    }

    public function testDeleteDocument()
    {
        $logger = new NullLogger();
        $documentStorage = $this->createMock(InMemoryStorage::class);
        $documentStorage->expects($this->once())->method('delete')->with('test_type', 'test-id');
        $blobsStorage = new Gaufrette($logger, new Filesystem(new \Gaufrette\Adapter\InMemory()));
        $manager = new Manager($documentStorage, $blobsStorage);
        $manager->deleteDocument('test_type', 'test-id');
    }

    public function testDocumentExists()
    {
        $logger = new NullLogger();
        $documentStorage = $this->createMock(InMemoryStorage::class);
        $documentStorage->expects($this->once())->method('exists')->with('test_type', 'test-id')->will($this->returnValue(true));
        $blobsStorage = new Gaufrette($logger, new Filesystem(new \Gaufrette\Adapter\InMemory()));
        $manager = new Manager($documentStorage, $blobsStorage);
        $this->assertTrue($manager->documentExists('test_type', 'test-id'));
    }
}
