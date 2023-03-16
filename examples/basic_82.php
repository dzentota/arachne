<?php
require 'vendor/autoload.php';

use Arachne\HttpResource;
use Arachne\Item\Item;
use Arachne\Mode;
use Arachne\Processor\Output;
use Arachne\Response;
use Arachne\ResultSet;
use Psr\Http\Message\ResponseInterface;
use Respect\Validation\Validator as v;
use Symfony\Component\DomCrawler\Crawler;

ini_set('display_errors',1);
error_reporting(E_ALL);
//require 'src/services_parallel.php';
require 'src/services_async.php';

class NewsIntro extends Item
{
    protected $title;
    protected $description;
    protected $type = 'news';

    public function getValidator()
    {
        return v::attribute('title', v::scalarVal())
            ->attribute('description', v::scalarVal());

    }
}

class NewsContent extends Item
{
    protected $content;
    protected $type = 'news';

    public function getValidator()
    {
        return v::attribute('content', v::notEmpty());
    }
}

/**
 * @var Arachne\Engine\Parallel $scraper
 */
$scraper = $container['scraper'];
$scraper
    ->prepareEnv(Mode::CLEAR)
    ->addHandlers(
        [
            'success:rss' => function (Response $response, ResultSet $resultSet) use ($container) {
                $data = [];
                $response->filter('item')
                    ->reduce(function (Crawler $node, $i) {
                        return $i < 3;
                    })
                    ->each(function (Crawler $node) use ($resultSet) {
                        $itemData = [
                            'link' => $node->filter('link')->text(),
                            'title' => $node->filter('title')->text(),
                            'description' => $node->filter('description')->html(),
                            'image' => $node->filterXPath('//*[name()=\'media:thumbnail\']')->attr('url')
                        ];
                        $link = $itemData['link'];
                        $image = $itemData['image'];
                        $item = new NewsIntro($itemData);
                        $resultSet->addItem($item);
                        $resultSet->addResource('page', $link, $item);//bind resource to Item by passing third argument
                        $resultSet->addResource('image', $image, $item);
                    });
            },
            'success:page' => function (Response $response, ResultSet $resultSet) {
                $content = $response->filter('.news-text')->html();
                $data = ['content' => $content];
                $item = new NewsContent($data);
                $resultSet->addItem($item);
            },
            //the same as build in 'blobs' handler
            'success:image' => function (ResponseInterface $response, ResultSet $resultSet) {
                $resultSet->addBlob($response->getBody());
            }
        ]
    )->addProcessor(new Output())
    ->scrape(HttpResource::fromUrl('https://www.onliner.by/feed',  'rss'))

;