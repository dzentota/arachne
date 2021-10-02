<?php

namespace Arachne\Document;

use Gebler\Doclite\Collection;
use Gebler\Doclite\Database;
use Gebler\Doclite\Document;

class DocLite implements DocumentInterface
{
    private Collection $documents;

    public function __construct(Database $database)
    {
        $this->documents = $database->collection('documents');
        $database->createIndex('documents', '_id', '_type');
    }

    /**
     * @param string $type
     * @param string $id
     * @param array $data
     * @return mixed|void
     */
    public function create(string $type, string $id, array $data)
    {
        /**
         * @var Document $document
         */
        $document = $this->documents->findOneBy([
            '_id' => $id,
            '_type' => $type
        ]);

        if (null !== $document) {
            throw new \DomainException('Document already exists');
        }

        $data['_id'] = $id;
        $data['_type'] = $type;
        /**
         * @var Document $document
         */
        $document = $this->documents->get();
        $this->setData($document, $data);
    }

    /**
     * @param string $type
     * @param string $id
     * @param array $data
     * @return mixed|void
     */
    public function update(string $type, string $id, array $data)
    {
        /**
         * @var Document $document
         */
        $document = $this->documents->findOneBy([
            '_id' => $id,
            '_type' => $type
        ]);

        if (null === $document) {
            throw new \DomainException('Document does not exist');
        }
        $this->setData($document, $data);
    }

    /**
     * @param string $type
     * @param string $id
     * @return array|null
     */
    public function get(string $type, string $id)
    {
        /**
         * @var Document $document
         */
        $document = $this->documents->findOneBy([
            '_id' => $id,
            '_type' => $type
        ]);

        if (null !== $document) {
            $data = $document->getData();
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
        /**
         * @var Document $document
         */
        if (!$document = $this->get($type, $id)) {
            throw new \DomainException('Document does not exist');
        }

        $this->documents->deleteDocument(
            $document
        );
    }

    /**
     * @param string|null $type
     */
    public function getIterator(string $type = null)
    {
        return is_null($type) ?
            $this->documents->findAllBy([]) :
            $this->documents->findAllBy(['_type' => $type]);
    }

    public function clear()
    {
        return $this->documents->deleteAll();
    }

    /**
     * @param string|null $type
     * @return int
     */
    public function count(string $type = null): int
    {
        return isset($type) ?
            $this->documents->where('_type', '=', $type)->count() :
            $this->documents->count();
    }

    /**
     */
    public function getTypes()
    {
        throw new \LogicException('Not implemented');
    }

    /**
     * @param $type
     * @param $id
     * @return bool
     */
    public function exists(string $type, string $id)
    {
        return !empty($this->documents->findOneBy([
            '_id' => $id,
            '_type' => $type
        ]));
    }

    /**
     * @param string $id
     * @param array $data
     * @throws \Gebler\Doclite\Exception\DatabaseException
     */
    protected function setData(Document $document, array $data): void
    {
        foreach ($data as $key => $value) {
            $document->setValue($key, $value);
        }
        $this->documents->save($document);
    }
}