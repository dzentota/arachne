<?php
require 'vendor/autoload.php';

use Arachne\HttpResource;
use Arachne\Item\RawItem;
use Arachne\Mode;
use Arachne\PostProcessor\Output;
use Arachne\Response;
use Arachne\ResultSet;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

require 'src/services_async.php';

/**
 * @var \Pimple\Container $container
 * @var Arachne\Engine\Parallel $scraper
 */
$scraper = $container['scraper'];
$scraper->prepareEnv(Mode::CLEAR)
    ->addHandlers(
        [
            'success:rss' => function (Response $response, ResultSet $resultSet) {
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
                        $item = new RawItem($itemData);
                        $resultSet->addItem($item);
                        $resultSet->addResource('page', $link, $item);//bind resource to Item by passing third argument
                        $resultSet->addResource('image', $image, $item);
                    });
            },
            'success:page' => function (Response $response, ResultSet $resultSet) {
                $content = $response->filter('.news-text')->html();
                $data = ['content' => $content];
                $item = new RawItem($data);
                $resultSet->addItem($item);
            },
            'success:image' => function (ResponseInterface $response, ResultSet $resultSet) {
                $resultSet->addBlob($response->getBody());
            }
        ]
    )->addPostProcessor(new Output())
    ->scrape(HttpResource::fromUrl('https://www.onliner.by/feed',  'rss'))
;