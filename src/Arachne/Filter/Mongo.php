<?php

namespace Arachne\Filter;

use MongoDB\Client;
use Arachne\Resource;

/**
 * @todo add prefix for filterName like for frontier
 * Class Mongo
 * @package Arachne\Filter
 */
class Mongo implements FilterInterface
{
    private $storage;

    /**
     * Mongo constructor.
     * @param Client $client
     * @param string $database
     */
    public function __construct(Client $client, string $database = 'scraper')
    {
        $this->storage = $client->$database;
    }


    public function add(string $filterName, Resource $resource)
    {
        $this->storage->{$filterName}->updateOne(
            ['_id' => $resource->getHash()],
            ['$set' => ['_id' => $resource->getHash()]],
            ['upsert' => true]
        );
    }

    public function remove(string $filterName, Resource $resource)
    {
        $this->storage->{$filterName}->deleteOne(
            ['_id' => $resource->getHash()]
        );
    }

    public function exists(string $filterName, Resource $resource) : bool
    {
        $exist = $this->storage->{$filterName}->findOne(
            ['_id' => $resource->getHash()], ['_id']
        );
        return !empty($exist);
    }

    public function clear(string $filterName)
    {
        $this->storage->{$filterName}->drop();
    }

    public function count(string $filterName) : int
    {
        return $this->storage->{$filterName}->count();
    }
}
