<?php

namespace Arachne\Document;

use MongoDB\Client;

/**
 * Class Mongo
 * @package Arachne\Document
 */
class Mongo implements DocumentInterface
{
    private $storage;


    /**
     * Mongo constructor.
     * @param Client $client
     * @param string $database
     */
    public function __construct(Client $client, string $database = 'scraper')
    {
        $this->storage = $client->$database->documents;
    }

    /**
     * @param string $type
     * @param string $id
     * @param array $data
     * @return mixed|void
     */
    public function create(string $type, string $id, array $data)
    {
        if ($this->exists($type, $id)) {
            throw new \DomainException('Document already exists');
        }
        $data['_id'] = $id;
        $data['_type'] = $type;
        $this->storage->insertOne(
            $data
        );
    }

    /**
     * @param string $type
     * @param string $id
     * @param array $data
     * @return mixed|void
     */
    public function update(string $type, string $id, array $data)
    {
        if (!$this->exists($type, $id)) {
            throw new \DomainException('Document does not exist');
        }
        unset($data['id']);
        unset($data['_id']);
        $this->storage->updateOne(
            ['_id' => $id],
            ['$set' => $data]
        );
    }

    /**
     * @param string $type
     * @param string $id
     * @return array|null
     */
    public function get(string $type, string $id)
    {
        $document = $this->storage->findOne(array(
            '_id' => $id
        ));

        if ($document) {
            $data = $document->getArrayCopy();
            unset($data['_id']);
            unset($data['_type']);
            return $data;
        }

        return null;

    }

    /**
     * @param string $type
     * @param string $id
     * @return mixed|void
     */
    public function delete(string $type, string $id)
    {
        if (!$this->exists($type, $id)) {
            throw new \DomainException('Document does not exist');
        }
        $this->storage->deleteOne(
            ['_id' => $id, '_type' => $type]
        );
    }

    /**
     * @param string|null $type
     * @return mixed
     */
    public function getIterator(string $type = null)
    {
        return is_null($type) ?
            $this->storage->find([], [
                'projection' => ['_type' => 0, '_id' => 0]
            ]) :
            $this->storage->find(['_type' => $type], [
                'projection' => ['_type' => 0, '_id' => 0]
            ]);
    }

    /**
     * @return array|object
     */
    public function clear()
    {
        return $this->storage->drop();
    }

    /**
     * @param string|null $type
     * @return int
     */
    public function count(string $type = null): int
    {
        return isset($type) ?
            $this->storage->count(['_type' => $type]) :
            $this->storage->count();
    }

    /**
     * @return mixed
     */
    public function getTypes()
    {
        return $this->storage->distinct('_type');
    }

    /**
     * @param $type
     * @param $id
     * @return bool
     */
    public function exists(string $type, string $id)
    {
        return !empty($this->storage->findOne([
            '_id' => $id
        ], ['_id']));
    }
}
