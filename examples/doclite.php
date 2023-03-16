<?php
require 'vendor/autoload.php';

use Arachne\Crawler\DomCrawler;
use Arachne\HttpResource;
use Arachne\Item\Item;
use Arachne\Mode;
use Arachne\ResultSet;
use Psr\Http\Message\ResponseInterface;
use Respect\Validation\Validator as v;

ini_set('display_errors',1);
error_reporting(E_ALL);
require 'src/services.php';
require 'src/services_doclite.php';

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
$scraper->prepareEnv(Mode::RESUME)
    ->addHandlers(
        [
            'success:rss' => function (ResponseInterface $response, ResultSet $resultSet) use ($container) {
                $content = $response->getBody()->getContents();
                $data = [];
                $crawler = new DomCrawler($content);
                $crawler->filter('item')
                    ->reduce(function (DomCrawler $node, $i) {
                        return $i < 3;
                    })
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
                $content = $response->getBody()->getContents();
                $content = (new DomCrawler($content))->filter('.news-text')->html();
                $data = ['content' => $content];
                $item = new NewsContent($data);
                $resultSet->addItem($item);
            },
            //the same as build in 'blobs' handler
            'success:image' => function (ResponseInterface $response, ResultSet $resultSet) {
                $resultSet->markAsBlob();
            }
        ]
    )->scrape(HttpResource::fromUrl('https://www.onliner.by/feed',  'rss'))
//    ->dumpDocuments()
;