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
require 'src/services_mongo.php';

$container['MONGO_DB_NAME'] = 'selver';

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
            'success:map' => function (ResponseInterface $response, ResultSet $resultSet) use ($container) {
                $content = $response->getBody()->getContents();
                $crawler = new DomCrawler($content);
                $crawler->filter('loc')
                    ->each(function (DomCrawler $node) use ($resultSet) {
                        $resultSet->addResource('item', $node->text());
                    });
            },
            'success:item' => function(ResponseInterface $response, ResultSet $resultSet) {
                $content = $response->getBody()->getContents();
                if (false === strpos($content, 'http://schema.org/Offer')) {
                    return;
                }
                $crawler = new DomCrawler($content);
                $ean = '';
                $crawler->filter('#product-attribute-specs-table tr')->each(function (DomCrawler $crawler) use (&$ean) {
                   $label = $crawler->filter('.label')->text();
                   if ($label === 'Ribakood') {
                       $ean = $crawler->filter('.data')->text();
                   }
                });
                $data['ean'] = $ean;
                $data['title'] = $crawler->filter('h1')->text();
                $data['category'] = $crawler->filter('#breadcrumbs a')->last()->text();
                $data['url'] = $resultSet->getResource()->getUrl();
                $data['image'] = 'https:' . $crawler->filter('#main-image-default img')->attr('src');
                $data['price'] = floatval($crawler->filter('[itemprop=price]')->attr('content'));
                $data['description'] = (string) $crawler->filter('.product-info-box [itemprop=description]')->text();
                $resultSet->addItem(new Product($data));
            }
        ]
    )->scrape(HttpResource::fromUrl('https://www.selver.ee/sitemap.xml',  'map'))
    //->dumpDocuments()
;