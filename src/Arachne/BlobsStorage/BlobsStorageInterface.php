<?php

namespace Arachne\BlobsStorage;

use Arachne\Resource;

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
     * @param \Arachne\Resource $resource
     * @param string|string $contents
     * @param bool $overwrite
     * @return mixed
     */
    public function write(Resource $resource, string $contents = '', bool $overwrite = true);
    /**
     * @param \Arachne\Resource|Resource $resource
     * @return mixed
     */
    public function read(Resource $resource);

    /**
     * @param \Arachne\Resource|Resource $resource
     * @return mixed
     */
    public function exists(Resource $resource);

    /**
     * @param \Arachne\Resource|Resource $resource
     * @return mixed
     */
    public function delete(Resource $resource);

    public function clear();

}
