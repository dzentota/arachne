<?php

namespace Arachne\Frontier;

use Gebler\Doclite\Collection;
use Gebler\Doclite\Database;
use Gebler\Doclite\Document;

class DocLite implements FrontierInterface
{
    private const COLLECTION = 'frontier';
    private const SEQUENCE = '_sec';
    private const PRIORITY = '_priority';

    private Collection $frontier;

    public function __construct(Database $database)
    {
        $this->frontier = $database->collection(self::COLLECTION);
        $database->createIndex(self::COLLECTION, self::SEQUENCE, self::PRIORITY);
    }

    public function populate(\Serializable $item, int $priority = self::PRIORITY_NORMAL)
    {
        /**
         * @var Document $item
         */
        $document = $this->frontier->get();
        $document->setValue(self::SEQUENCE, hrtime(true));
        $document->setValue(self::PRIORITY, $priority);
        $document->setValue('data', serialize($item));
        $this->frontier->save($document);
    }

    public function nextItem()
    {
        $this->frontier->beginTransaction();
        $item = $this->frontier->orderBy(self::PRIORITY, 'DESC')
            ->orderBy(self::SEQUENCE, 'ASC')
            ->fetchArray();
        /**
         * @var Document[] $item
         */
        if (empty($item[0])) {
            return null;
        }
        $data = $item[0]->getValue('data');
        $this->frontier->deleteDocument($item[0]);
        $this->frontier->commit();
        return unserialize($data);
    }

    public function clear()
    {
        $this->frontier->deleteAll();
    }
}