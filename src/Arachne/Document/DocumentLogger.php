<?php

namespace Arachne\Document;
use Psr\Log\LoggerInterface;

/**
 * Class DocumentLogger
 * @package Arachne\Document
 */
class DocumentLogger implements DocumentInterface
{

    /**
     * @var \Arachne\Document\DocumentInterface
     */
    private $document;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * DocumentLogger constructor.
     * @param \Arachne\Document\DocumentInterface $document
     * @param LoggerInterface $logger
     */
    public function __construct(DocumentInterface $document, LoggerInterface $logger)
    {
        $this->document = $document;
        $this->logger = $logger;
    }

    /**
     * @param string $type
     * @param string $id
     * @param array $data
     * @return mixed
     */
    public function create(string $type, string $id, array $data)
    {
        $this->logger->debug(sprintf('Creating new Document [%s][%s]', $type, $id));
        $this->document->create($type, $id, $data);
    }

    /**
     * @param string $type
     * @param string $id
     * @param array $data
     * @return mixed
     */
    public function update(string $type, string $id, array $data)
    {
        $this->logger->debug(sprintf('Updating Document [%s][%s]', $type, $id));
        $this->document->update($type, $id, $data);
    }

    /**
     * @param string $type
     * @param string $id
     * @return mixed
     */
    public function get(string $type, string $id)
    {
        $this->logger->debug(sprintf('Getting Document [%s][%s]', $type, $id));
        return $this->document->get($type, $id);
    }

    /**
     * @param $type
     * @param $id
     * @return bool
     */
    public function exists(string $type, string $id)
    {
        $this->logger->debug(sprintf('Checking if Document [%s][%s] exists', $type, $id));
        return $this->document->exists($type, $id);
    }

    /**
     * @param string $type
     * @param string $id
     * @return mixed
     */
    public function delete(string $type, string $id)
    {
        $this->logger->debug(sprintf('Deleting Document [%s][%s]', $type, $id));
        $this->document->delete($type, $id);
    }

    /**
     * @param string|null $type
     * @return mixed
     */
    public function getIterator(string $type = null)
    {
        return $this->document->getIterator($type);
    }

    /**
     * @return mixed
     */
    public function clear()
    {
        $this->logger->debug('Clearing Documents storage');
        $this->document->clear();
    }

    /**
     * @param string|null $type
     * @return mixed
     */
    public function count(string $type = null): int
    {
        $this->logger->debug(sprintf('Getting count of Documents [%s]', $type));
        return $this->document->count($type);
    }

    /**
     * @return mixed
     */
    public function getTypes()
    {
        return $this->document->getTypes();
    }
}