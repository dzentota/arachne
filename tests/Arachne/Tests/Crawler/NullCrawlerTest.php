<?php

namespace Arachne\Tests\Crawler;

use Arachne\Crawler\NullCrawler;

class NullCrawlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var NullCrawler
     */
    protected static $crawler;

    public static function setUpBeforeClass()
    {
        self::$crawler = new NullCrawler();
    }

    public function testNullMethods()
    {
        $crawler = self::$crawler;

        $this->assertNull($crawler->attr('any'));
        $this->assertNull($crawler->text());
        $this->assertNull($crawler->html());
        $this->assertNull($crawler->nodeName());

    }

    public function testEmptyArrayMethods()
    {
        $crawler = self::$crawler;

        $this->assertInternalType('array', $crawler->links());
        $this->assertEmpty($crawler->links());

        $this->assertInternalType('array', $crawler->extract('_text'));
        $this->assertEmpty($crawler->extract('_text'));
    }

    public function testNoneExistent()
    {
        $this->assertInstanceOf(NullCrawler::class, self::$crawler->noneExistent());
    }

    public function testToString()
    {
        $this->assertEmpty((string) self::$crawler);
    }

    public function testCount()
    {

    }
}
