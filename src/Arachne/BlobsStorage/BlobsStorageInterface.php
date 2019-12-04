<?php

namespace Arachne\BlobsStorage;

use Arachne\HttpResource;

/**
 * Interface AdapterInterface
 * @package Arachne\BlobsStorage
 */
interface BlobsStorageInterface
{

    /**
     * Returns an array of all keys (files and directories)
     *
     * @return array
     */
    public function keys();

    /**
     * @param \Arachne\HttpResource $resource
     * @param string|string $contents
     * @param bool $overwrite
     * @return mixed
     */
    public function write(HttpResource $resource, string $contents = '', bool $overwrite = true);
    /**
     * @param \Arachne\HttpResource|HttpResource $resource
     * @return mixed
     */
    public function read(HttpResource $resource);

    /**
     * @param \Arachne\HttpResource|HttpResource $resource
     * @return mixed
     */
    public function exists(HttpResource $resource);

    /**
     * @param \Arachne\HttpResource|HttpResource $resource
     * @return mixed
     */
    public function delete(HttpResource $resource);

    public function clear();

}
