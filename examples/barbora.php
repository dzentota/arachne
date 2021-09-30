<?php
require 'vendor/autoload.php';

use Arachne\Crawler\DomCrawler;
use Arachne\HttpResource;
use Arachne\Mode;
use Arachne\ResultSet;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Request;

ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'src/services.php';
//require 'src/services_async.php';
//require 'src/services_mongo.php';

//$container['MONGO_DB_NAME'] = 'barbora';

$h = fopen('barbora.tess.csv', 'w') or die('Cannot open csv file for writing');

/**
 * @var Arachne\Engine $engine
 */
$engine = $container['scraper'];
$request = new Request('https://barbora.lt/sitemap.xml', 'GET');
$request = $request
    ->withAddedHeader('Accept-Encoding', 'gzip, deflate, br')
    ->withAddedHeader('Accept-Language', 'ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4')
    ->withAddedHeader('Upgrade-Insecure-Requests', '1')
    ->withAddedHeader('Referer', 'https://www.barbora.lt/')
    ->withAddedHeader('User-Agent',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36')
    ->withAddedHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8')
    ->withAddedHeader('Cache-Control', 'max-age=0')
    ->withAddedHeader('Cookie', 'region=barbora.lt')
;
$map = new HttpResource($request, 'map');

$engine->prepareEnv(Mode::RESUME)
    ->addHandlers(
        [
            'success:map' => function (ResponseInterface $response, ResultSet $resultSet) use ($container) {
                $content = $response->getBody()->getContents();
                $crawler = new DomCrawler($content);
                $start = false;
                $crawler->filter('loc')
                    ->each(function (DomCrawler $crawler) use ($resultSet, &$start) {
                        $href = $crawler->text();
//                        if (false === strpos($href, 'https://barbora.lt/produktai/')) {
//                            return;
//                        }
//                        if ($start === false) {
//                            if ($href === 'https://barbora.lt/produktai/spalvoti-smeigtukai-milan-1-vnt-822391') {
//                                $start = true;
//                            } else {
//                                return;
//                            }
//                        }
                        $resultSet->addResource(
                            'product', $href
                        );
                    });
            },
            'success:product' => function (ResponseInterface $response, ResultSet $resultSet) use($h) {
                $content = $response->getBody()->getContents();
                $crawler = new DomCrawler($content);
                $title = trim($crawler->filter('h1')->text());
                $data = explode(',', $title);
                $suffix =  array_pop($data);
                list($quantity, $unit) = explode(' ', trim($suffix));
                $categories = [];
                $crawler->filter('.breadcrumb li' )->each(function(DomCrawler $crawler) use (&$categories){
                    $categories[] = trim($crawler->text());
                });
                array_shift($categories);
                list($category1, $category2, $category3) = $categories;

                preg_match('~window\.reportUserActionToThirdParty\("detail",\s*(\{.*?\})~is', $content, $m);
                $data = json_decode($m[1], true);
                $result[] = $title;
                $result[] = $category1;
                $result[] = $category2;
                $result[] = $category3;
                $result[] = $quantity;
                $result[] = $unit;
                $result[] = (float) $data['price'];
                $result[] = $data['comparative_unit_price'];
                $discountPrice = $crawler->filter('.b-product-crossed-out-price')->text();
                $discountPrice = floatval(str_replace(['€', ' ', ','], ['', '', '.'], $discountPrice));
                $discountPrice = str_replace('.', ',', $discountPrice);
                $result[] = $discountPrice;
                $result = array_map(function($item) {
                    return is_string($item)? iconv("UTF-8", "CP1257//TRANSLIT", $item) : $item;
                }, $result);
                fputcsv($h, $result, ';', '"', '"');
            }
        ]
    )->scrape($map)
;
fclose($h);
//file_put_contents('barbora.сз1257.csv',iconv("UTF-8", "CP1257//TRANSLIT", file_get_contents('barbora.csv')));