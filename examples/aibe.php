<?php
require 'vendor/autoload.php';

use Arachne\Crawler\DomCrawler;
use Arachne\HttpResource;
use Arachne\Item;
use Arachne\Mode;
use Arachne\ResultSet;
use Psr\Http\Message\ResponseInterface;
use Respect\Validation\Validator as v;

ini_set('display_errors',1);
error_reporting(E_ALL);
require 'src/services_mongo.php';

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
                $crawler->filter('.categTree a')
                    ->each(function (DomCrawler $node) use ($resultSet) {
                        $resultSet->addResource('category', $node->attr('href'));
                    });
            },
            'success:category' => function (ResponseInterface $response, ResultSet $resultSet) {
                $content = $response->getBody()->getContents();
                $isParentCategory = (new DomCrawler($content))->filter('.subcategory-heading')->count();
                if ($isParentCategory) {
                    return;
                }
                (new DomCrawler($content))->filter('.product-container .quick-view')
                    ->each(function (DomCrawler $crawler) use ($resultSet){
                    $resultSet->addHighPriorityResource('product', $crawler->attr('href'));
                });
            },
            'success:product' => function(ResponseInterface $response, ResultSet $resultSet) {
                $content = $response->getBody()->getContents();
                $crawler = new DomCrawler($content);
                $data['ean'] = $crawler->filter('.product-info-line [itemprop=sku]')->text();
                $data['title'] = $crawler->filter('h1')->text();
                $data['category'] = $crawler->filter('.navigation_page a')->last()->text();
                $data['url'] = $resultSet->getResource()->getUrl();
                $data['image'] = $crawler->filter('.jqzoom img')->attr('src');
                $data['price'] = floatval(str_replace(',','.', $crawler->filter('#our_price_display')->text()));
                $data['description'] = '';
                $resultSet->addItem(new Product($data));
            }
        ]
    )->scrape(HttpResource::fromUrl('https://www.aibesmaistas.lt/sitemap',  'map'))
    //->dumpDocuments()
;