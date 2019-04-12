<?php
require 'vendor/autoload.php';

use Arachne\Service\GenericFactory;
use Arachne\Service\Proxy;
use Psr\Http\Message\ResponseInterface;
use Arachne\Crawler\DomCrawler;
use Arachne\ResultSet;
use Respect\Validation\Validator as v;

$container = \Arachne\Service\Container::create(new Proxy(new \Arachne\Service\MongoFactory()));

class NewsIntro extends \Arachne\Item
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

class NewsContent extends \Arachne\Item
{
    protected $content;
    protected $type = 'news';

    public function getValidator()
    {
        return v::attribute('content', v::notEmpty());
    }
}

$container->get()
    ->scraper()
    ->prepareEnv(\Arachne\Mode::CLEAR)
    ->addHandlers(
        [
            'success:rss' => function (ResponseInterface $response, ResultSet $resultSet) use ($container) {
                $data = [];
                $content = (string)$response->getBody();
                $crawler = new DomCrawler($content);
                $crawler->filter('item')
//                ->reduce(function (DomCrawler $node, $i) {
//                    return $i < 3;
//                })
                    ->each(function (DomCrawler $node) use (&$data) {
                        $data[] = [
                            'link' => $node->filter('link')->text(),
                            'title' => $node->filter('title')->text(),
                            'description' => $node->filter('description')->html(),
                            'image' => $node->filterXPath('//*[name()=\'media:thumbnail\']')->attr('url')
                        ];
                    });
                foreach ($data as $itemData) {
                    $link = $itemData['link'];
                    $image = $itemData['image'];

                    $item = new NewsIntro($itemData);
                    $resultSet->addItem($item);
                    $resultSet->addResource('page', $link, $item);//bind resource to Item by passing third argument
                    $resultSet->addResource('image', $image, $item);
                }
            },
            'success:page' => function (ResponseInterface $response, ResultSet $resultSet) {
                $content = (new DomCrawler((string)$response->getBody()))->filter('.news-text')->html();
                $data = ['content' => $content];
                $item = new NewsContent($data);
                $resultSet->addItem($item);
            },
            //the same as build in 'blobs' handler
            'success:image' => function (ResponseInterface $response, ResultSet $resultSet) {
                $resultSet->markAsBlob();
            }
        ]
    )->scrape(\Arachne\HttpResource::fromUrl('https://www.onliner.by/feed',  'rss'))
    ->dumpDocuments()
;