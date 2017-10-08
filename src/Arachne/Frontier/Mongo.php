<?php

namespace Arachne\Frontier;

/**
 * Class Mongo
 * @package Arachne\Frontier
 */
class Mongo implements FrontierInterface
{
    /**
     * @var \MongoDB\Database
     */
    private $storage;

    /**
     * Mongo constructor.
     * @param \MongoDB\Client $client
     * @param string $database
     */
    public function __construct(\MongoDB\Client $client, string $database = 'scraper')
    {
        $this->storage = $client->$database;
        $this->setUp();
    }

    /**
     * @throws \MongoCursorException
     */
    protected function setUp()
    {
        try {
            $this->storage->frontier_counters->insertOne(array(
                '_id' => 'frontier',
                'seq' => 0
            ));
        } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
            if (false === strpos($e->getMessage(), 'duplicate key')) {
                throw $e;
            }
        }

        $this->storage->frontier->createIndex(array(
            'priority' => -1,
            'sec' => 1,
        ));

    }

    /**
     * @return mixed
     */
    protected function getNextSequence()
    {
        $ret = $this->storage->frontier_counters->findOneAndUpdate(
            array('_id' => 'frontier'),
            array('$inc' => array('seq' => 1)),
            ['returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
        );

        return $ret['seq'];
    }

    /**
     * @param \Serializable $resource
     * @param int $priority
     * @return mixed|void
     */
    public function populate( \Serializable $resource, int $priority = self::PRIORITY_NORMAL)
    {
        $this->storage->frontier->insertOne(
            ['sec'=>$this->getNextSequence(),'priority'=>$priority, 'data'=>serialize($resource)]
        );
    }

    /**
     * @return mixed|null
     */
    public function nextItem()
    {
        $ret = $this->storage->frontier->findOneAndDelete(
            [],
            [
                'sort' => ['priority'=> -1, 'seq' => 1],
            ]
        );

        if (!$ret) {
            return null;
        }
        $item = unserialize($ret['data']);
        return $item;
    }

    /**
     *
     */
    public function clear()
    {
        $this->storage->frontier->drop();
    }

}
