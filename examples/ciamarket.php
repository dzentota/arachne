<?php
require 'vendor/autoload.php';

use Arachne\Crawler\DomCrawler;
use Arachne\HttpResource;
use Arachne\Item;
use Arachne\Mode;
use Arachne\ResultSet;
use Psr\Http\Message\ResponseInterface;
use Respect\Validation\Validator as v;

ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'src/services.php';
require 'src/services_mongo.php';

$container['MONGO_DB_NAME'] = 'ciamarket';

class Product extends Item
{
    protected $title;
    protected $url;
    protected $image;
    protected $ean;
    protected $price;
    protected $category;
    protected $description;
    protected $type = 'products';

    public function getValidator()
    {
        return v::attribute('title', v::scalarVal()->notEmpty())
            ->attribute('url', v::scalarVal()->notEmpty())
            ->attribute('ean', v::scalarVal())
            ->attribute('image', v::scalarVal())
            ->attribute('price', v::floatType())
            ->attribute('category', v::scalarVal()->notEmpty())
            ->attribute('description', v::scalarVal());

    }
}

$container['scraper']
    ->prepareEnv(Mode::RESUME)
    ->addHandlers(
        [
            'success:home' => function (ResponseInterface $response, ResultSet $resultSet) use ($container) {
                $content = $response->getBody()->getContents();
                $crawler = new DomCrawler($content);
                $crawler->filter('.tree ul li a')
                    ->each(function (DomCrawler $node) use ($resultSet) {

                        $resultSet->addResource(
                            'category', $node->attr('href')
                        );
                    });
            },
            'success:category' => function (ResponseInterface $response, ResultSet $resultSet) {
                $content = $response->getBody()->getContents();
                $category = (new DomCrawler($content))->filter('h1')->text();

                (new DomCrawler($content))->filter('.product-flags-plist')
                    ->each(function (DomCrawler $crawler) use ($resultSet, $category) {
                        $resultSet->addHighPriorityResource(
                            'product',
                            $crawler->attr('href'),
                            null,
                            'GET',
                            null,
                            [],
                            ['category' => $category]
                        );
                    });
                (new DomCrawler($content))->filter('.page-list a')
                    ->each(function (DomCrawler $crawler) use ($resultSet) {
                        $resultSet->addHighPriorityResource('category', $crawler->attr('href'));
                    });
            },
            'success:product' => function (ResponseInterface $response, ResultSet $resultSet) {
                $content = $response->getBody()->getContents();
                $crawler = new DomCrawler($content);
                $data['ean'] = $crawler->filter('[itemprop=gtin13]')->attr('content');
                $data['title'] = $crawler->filter('h1')->text();
                $data['category'] = $resultSet->getResource()->getMeta('category', 'unknown');
                $data['url'] = $resultSet->getResource()->getUrl();
                $data['image'] = $crawler->filter('.js-qv-product-cover')->attr('src')?? '';
                $data['price'] = floatval( $crawler->filter('.price')->attr('content'));
                $data['description'] = $crawler->filter('.product-description')->text()?? '';
                $resultSet->addItem(new Product($data));
            }
        ]
    )->scrape(HttpResource::fromUrl('https://parduotuve.ciamarket.lt/', 'home'))//->dumpDocuments()
;
