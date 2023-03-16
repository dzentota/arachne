<?php
require 'vendor/autoload.php';

use Arachne\Crawler\DomCrawler;
use Arachne\HttpResource;
use Arachne\Item\Item;
use Arachne\Mode;
use Arachne\ResultSet;
use Psr\Http\Message\ResponseInterface;
use Respect\Validation\Validator as v;

ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'src/services.php';
require 'src/services_mongo.php';

$container['MONGO_DB_NAME'] = 'zirnis';

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
                $crawler->filter('.item-vertical a')
                    ->each(function (DomCrawler $node) use ($resultSet) {
                        $resultSet->addResource('parentCategory', $node->attr('href'));
                    });
            },
            'success:parentCategory' => function (ResponseInterface $response, ResultSet $resultSet) {
                $content = $response->getBody()->getContents();
                (new DomCrawler($content))->filter('.panel-collapse a')
                    ->each(function (DomCrawler $crawler) use ($resultSet) {
                        $resultSet->addResource('category', $crawler->attr('href'));
                    });
            },
            'success:category' => function (ResponseInterface $response, ResultSet $resultSet) {
                $content = $response->getBody()->getContents();

                (new DomCrawler($content))->filter('.product-image-container a')
                    ->each(function (DomCrawler $crawler) use ($resultSet) {
                        $resultSet->addHighPriorityResource('product', $crawler->attr('href'));
                    });
                (new DomCrawler($content))->filter('.pagination a')
                    ->each(function (DomCrawler $crawler) use ($resultSet) {
                        $resultSet->addHighPriorityResource('category', $crawler->attr('href'));
                    });
            },
            'success:product' => function (ResponseInterface $response, ResultSet $resultSet) {
                $content = $response->getBody()->getContents();
                $crawler = new DomCrawler($content);
                $ean = trim(str_replace('Prekės kodas:', '', $crawler->filter('.model')->text()));
                $data['ean'] = $ean;
                $data['title'] = $crawler->filter('h1')->text();
                $category = '';
                $crawler->filter('.breadcrumb a')->each(function (DomCrawler $crawler) use (&$category){
                    if (strpos($crawler->attr('href'), '/category')) {
                        $category = $crawler->text();
                    }
                });
                $data['category'] = $category;
                $data['url'] = $resultSet->getResource()->getUrl();
                $data['image'] = $crawler->filter('.large-image img')->attr('data-src')?? '';
                $data['price'] = floatval( $crawler->filter('#price-old')->text());
                $data['description'] = '';
                $resultSet->addItem(new Product($data));
            }
        ]
    )->scrape(HttpResource::fromUrl('https://www.parduotuvezirnis.lt/', 'home'))//->dumpDocuments()
;