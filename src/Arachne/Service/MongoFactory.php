<?php

namespace Arachne\Service;

use Arachne\Document\DocumentInterface;
use Arachne\Document\DocumentLogger;
use Arachne\Document\Mongo as MongoStorage;
use Arachne\Filter\FilterInterface;
use Arachne\Filter\FilterLogger;
use Arachne\Filter\Mongo as MongoFilter;
use Arachne\Frontier\FrontierInterface;
use Arachne\Frontier\FrontierLogger;
use Arachne\Frontier\InMemory as InMemoryFrontier;
use Arachne\Frontier\Mongo as MongoFrontier;

class MongoFactory extends GenericFactory
{
    /**
     * @return FrontierInterface
     */
    public function frontier() : FrontierInterface
    {
        $logger = $this->getContainer()->logger();
        $client = $this->getMongoDbClient();
        return new FrontierLogger( new MongoFrontier($client, $this->getDbName()), $logger);
    }

    /**
     * @return DocumentInterface
     */
    public function documentStorage() : DocumentInterface
    {
        $logger = $this->getContainer()->logger();
        $client = $this->getMongoDbClient();
        return new DocumentLogger(new MongoStorage($client, $this->getDbName()), $logger);
    }

    /**
     * @return FilterInterface
     */
    public function filter() : FilterInterface
    {
        $logger = $this->getContainer()->logger();
        $client = $this->getMongoDbClient();
        return new FilterLogger(new MongoFilter($client, $this->getDbName()), $logger);
    }

    /**
     * @return \MongoDB\Client
     */
    protected function getMongoDbClient(): \MongoDB\Client
    {
        $client = new \MongoDB\Client('mongodb://mongo', ['username' => 'root', 'password' => 'root']);
        return $client;
    }

    protected function getDbName()
    {
        return 'scraper';
    }

}
