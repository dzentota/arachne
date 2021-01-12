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

$container['MONGO_DB_NAME'] = 'egulbele';

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
    ->prepareEnv(Mode::CLEAR)
    ->addHandlers(
        [
            'success:home' => function (ResponseInterface $response, ResultSet $resultSet) use ($container) {
                $content = $response->getBody()->getContents();
                $crawler = new DomCrawler($content);
                $crawler->filter('#megamenu-row-3-1 .category > a')
                    ->each(function (DomCrawler $node) use ($resultSet) {
                        $resultSet->addResource(
                            'category', $node->attr('href')
                        );
                    });
            },
            'success:category' => function (ResponseInterface $response, ResultSet $resultSet) use($container){
                $content = $response->getBody()->getContents();

                (new DomCrawler($content))->filter('.products .product-title a')
                    ->each(function (DomCrawler $crawler) use ($resultSet, $container) {
                        if (empty(trim($crawler->attr('href')))) {
                            $container['logger']->critical("empty href " . $crawler->text());
                            return;
                        }
                        $resultSet->addHighPriorityResource(
                            'product',
                            trim($crawler->attr('href'))
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
                $data['ean'] = trim($crawler->filter('[itemprop=sku]')->text());
                $data['title'] = $crawler->filter('h1')->text();
                $data['category'] = trim($crawler->filter('.breadcrumb [itemprop=name]')->text());
                $data['url'] = $resultSet->getResource()->getUrl();
                $data['image'] = $crawler->filter('.js-qv-product-cover')->attr('src')?? '';
                $data['price'] = floatval( $crawler->filter('.price [itemprop=price]')->attr('content'));
                $data['description'] = trim((string)$crawler->filter('#description .product-description')->text());
                $resultSet->addItem(new Product($data));
            }
        ]
    )->scrape(HttpResource::fromUrl('https://e-gulbele.lt/', 'home'))//->dumpDocuments()
;
