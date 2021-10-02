<?php

namespace Arachne\Filter;

use Arachne\Hash\Hashable;
use Gebler\Doclite\Database;

class DocLite implements FilterInterface
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
        $this->database->collection('visited');
        $this->database->collection('scheduled');
    }

    public function add(string $filterName, Hashable $resource)
    {
        $collection = $this->database->collection($filterName);
        $filter = $collection->get($resource->getHash());
        $collection->save($filter);
    }

    public function remove(string $filterName, Hashable $resource)
    {
        $collection = $this->database->collection($filterName);
        $filter = $collection->findOneBy([Database::ID_FIELD => $resource->getHash()]);
        if (null !== $filter) {
            $collection->deleteDocument($filter);
        }
    }

    public function exists(string $filterName, Hashable $resource) : bool
    {
        $collection = $this->database->collection($filterName);

        $filter = $collection->findOneBy([Database::ID_FIELD => $resource->getHash()]);
        return !is_null($filter);
    }

    public function clear(string $filterName)
    {
        $collection = $this->database->collection($filterName);
        $collection->deleteAll();
    }

    public function count(string $filterName) : int
    {
        $collection = $this->database->collection($filterName);
        return $collection->count();
    }
}