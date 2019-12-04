<?php

namespace Arachne\Document;

use Arachne\BlobsStorage\BlobsStorageInterface;
use Arachne\HttpResource;

class Manager
{
    private $docStorage;
    private $blobsStorage;

    public function __construct(DocumentInterface $docStorage, BlobsStorageInterface $blobsStorage)
    {
        $this->docStorage = $docStorage;
        $this->blobsStorage = $blobsStorage;
    }

    /**
     * @return DocumentInterface
     */
    public function getDocStorage() : DocumentInterface
    {
        return $this->docStorage;
    }

    /**
     * @return BlobsStorageInterface
     */
    public function getBlobsStorage(): BlobsStorageInterface
    {
        return $this->blobsStorage;
    }

    /**
     * @param \Arachne\HttpResource|HttpResource $resource
     * @return mixed
     */
    public function getBoundDocument(HttpResource $resource)
    {
        $meta = $resource->getMeta();
        return $this->getDocument($meta['item_type'], $meta['item_id']);
    }

    /**
     * @param $type
     * @param $id
     * @return mixed
     */
    public function getDocument(string $type, string $id)
    {
        return $this->getDocStorage()->get($type, $id);
    }

    /**
     * @param \Arachne\HttpResource|HttpResource  $resource
     * @param array $data
     */
    public function updateBoundDocument(HttpResource $resource, array $data)
    {
        $meta = $resource->getMeta();
        $this->updateDocument($meta['item_type'], $meta['item_id'], $data);
    }

    /**
     * @param $type
     * @param $id
     * @param array $data
     */
    public function updateDocument(string $type, string $id, array $data)
    {
        $this->getDocStorage()->update($type, $id, $data);
    }


    /**
     * @param \Arachne\HttpResource $resource
     * @param $docType
     * @param $docId
     */
    public function bindResourceToDoc(HttpResource $resource, string $docType, string $docId)
    {
        $resource->setMeta(['item_id' => $docId, 'item_type' => $docType]);
    }

    /**
     * @param \Arachne\HttpResource $resource
     * @return bool
     */
    public function isBoundToDoc(HttpResource $resource)
    {
        return $resource->getMeta('item_id', false) && $resource->getMeta('item_type', false);
    }

    /**
     * @param $type
     * @param $id
     * @param array $data
     */
    public function createDocument(string $type, string $id, array $data)
    {
        $this->getDocStorage()->create($type, $id, $data);
    }

    /**
     * @param $type
     * @param $id
     */
    public function deleteDocument(string $type, string $id)
    {
        $this->getDocStorage()->delete($type, $id);
    }

    /**
     * @param $type
     * @param $id
     * @return bool
     */
    public function documentExists(string $type, string $id) : bool
    {
        return $this->getDocStorage()->exists($type, $id);
    }

}
