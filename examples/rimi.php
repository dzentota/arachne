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
require 'src/services_async.php';
//require 'src/services_doclite.php';
//require 'src/services_mongo.php';

$container['PROJECT'] = 'rimi';

$h = fopen('rimi.fast.lt.csv', 'a') or die('Cannot open csv file for writing');

/**
 * @var Arachne\Engine $engine
 */
$engine = $container['scraper'];
$resource = HttpResource::fromUrl('https://www.rimi.lt/e-parduotuve/sitemaps/products/siteMap_rimiLtSite_Product_lt_1.xml',
    'sitemap');
$resource2 = HttpResource::fromUrl('https://www.rimi.lt/e-parduotuve/sitemaps/products/siteMap_rimiLtSite_Product_lt_2.xml',
    'sitemap');
$resource3 = HttpResource::fromUrl('https://www.rimi.lt/e-parduotuve/sitemaps/products/siteMap_rimiLtSite_Product_lt_3.xml',
    'sitemap');

$engine->prepareEnv(Mode::RESUME)
    ->addHandlers(
        [
            'success:sitemap' => function (ResponseInterface $response, ResultSet $resultSet) use ($container) {
                $content = $response->getBody()->getContents();
                $crawler = new DomCrawler();
                $crawler->addXmlContent($content);
                $html = $crawler->html();
                $subcrawler = new DomCrawler($html);
                $start = false;
                $subcrawler->filter('loc')
                    ->each(function (DomCrawler $node) use ($resultSet, &$start) {
                        $url = $node->text();
//                        if ($start === false) {
//                            if ($url === 'https://www.rimi.lt/e-parduotuve/lt/produktai/bakaleja/kruopos/miezines-ir-perlines-kruopos/miezines-kruopos-melvit-400-g/p/160941') {
//                                $start = true;
//                            } else {
//                                return;
//                            }
//                        }
                        if (false !== strpos($url, 'https://www.rimi.lt/e-parduotuve/lt/produktai')) {
                            $resultSet->addResource('product', $url);
                        }
                    });
                return [];
            },

            'success:product' => function (ResponseInterface $response, ResultSet $resultSet) use($h) {
                $content = $response->getBody()->getContents();
                $crawler = new DomCrawler($content);
                $title = $crawler->filter('.product__main-info h3')->text();

                $euro = $crawler->filter('.price-wrapper .price span')->text();
                $cent = $crawler->filter('.price-wrapper .price sup')->text();
                $price = floatval(trim($euro) . (empty(trim($cent)) ? '.00' : '.' . trim($cent)));
                $price = str_replace('.', ',', $price);
                $categories = [];
                $crawler->filter('.section-header__container a')->each(function (DomCrawler $crawler) use (&$categories) {
                    $categories[] = trim($crawler->text());
                });
                $chunks = explode('/', $resultSet->getResource()->getUrl());
                if (empty($categories)) {
                    $category1 = str_replace('-', ' ', $chunks[6]);
                    $category2 = str_replace('-', ' ', $chunks[7]);
                } else {
                    list($category1, $category2) = $categories;
                }
                $category3 = str_replace('-', ' ', $chunks[8]);

                $pricePerUnit = $crawler->filter('.price-per')->text();
                if (false !== strpos((string) $pricePerUnit, '/')) {
                    list($pricePerUnit, $unit) = explode('/', $pricePerUnit);
                } else {
                    $pricePerUnit = 0;
                }
                $pricePerUnit = str_replace(['€', ' '], '', $pricePerUnit);

                $unit = '';
                $quantity = '';

                $crawler->filter('.product__details .list .item')->each(function (DomCrawler $crawler) use (&$unit, &$quantity) {
                   $attr = $crawler->filter('span')->text();
                   if (trim($attr) === 'Kiekis') {
                       $value = $crawler->filter('p')->text();
                       list($quantity, $unit) = explode(' ', trim($value));
                   }
                });

                $result[] = $title;
                $result[] = $category1;
                $result[] = $category2;
                $result[] = $category3;
                $result[] = $quantity;
                $result[] = $unit;
                $result[] = $price;
                $result[] = $pricePerUnit;
                $discountPrice = $crawler->filter('.price__old-price')->text();
                $discountPrice = floatval(str_replace(['€', ' ', ','], ['', '', '.'], $discountPrice));
                $discountPrice = str_replace('.', ',', $discountPrice);
                $result[] = $discountPrice;
                $result = array_map(function($item) {
                    return is_string($item)? iconv("UTF-8", "CP1257//TRANSLIT", $item) : $item;
                }, $result);
                fputcsv($h, $result, ';', '"', '"');
            }
        ]
    )
    ->scrape(
        $resource, $resource2, $resource3
    )
;
fclose($h);
//https://www.rimi.lt/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/virtuves-ir-stalo-serviravimo-reikmenys-/indai-/tauriu-kompl-schott-zwiesel-12vnt-aw21/p/7258439
//https://www.rimi.lt/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/trasos-ir-dirvozemio-misiniai/vazonelis-daiginimui-pro-garden-ss22/p/7258827
//https://www.rimi.lt/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/trasos-ir-dirvozemio-misiniai/daiginimo-vazoneliai-pro-garden-ss22/p/7258829
//https://www.rimi.lt/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/trasos-ir-dirvozemio-misiniai/daiginimo-vazoneliai-pro-garden-ss22/p/7258828
//https://www.rimi.lt/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/kaledines-prekes/girliandos/led-lemputes-100-led-keliu-spal-aw21/p/7257401
//https://www.rimi.lt/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/kaledines-prekes/girliandos/led-lemputes-40-led-keliu-spalvu-aw21/p/7257391
//[https://www.rimi.lt/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/kaledines-prekes/girliandos/led-lemputes-20-led-keliu-spalvu-aw21/p/7257392
//https://www.rimi.lt/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/kaledines-prekes/girliandos/led-lemputes-20-led-baltos-aw21/p/7257387
//https://www.rimi.lt/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/kaledines-prekes/girliandos/led-lemputes-40-led-geltonos-aw21/p/7257393
//https://www.rimi.lt/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/kaledines-prekes/girliandos/led-lemputes-20-led-geltonos-aw21/p/7257389
//https://www.rimi.lt/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/namu-apyvokos-reikmenys-/zvakes-ir-smilkalai/lekste-33cm-blizgios-sid-spalvos-aw21/p/7257554
//https://www.rimi.lt/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/namu-apyvokos-reikmenys-/zvakes-ir-smilkalai/lekste-33cm-blizgios-aukso-spalvos-aw21/p/7257561
//https://www.rimi.lt/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/namu-apyvokos-reikmenys-/zvakes-ir-smilkalai/lekste-33cm-blizgios-raudonos-spalvos/p/7257560
//https://www.rimi.lt/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/kaledines-prekes/girliandos/led-lemputes-100-led-baltos-aw21/p/7257378