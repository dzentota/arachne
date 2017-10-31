<?php

namespace Arachne\Tests\Parser;

use Arachne\Parser\Regex;

class RegexTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Regex
     */
    protected static $parser;

    public static function setUpBeforeClass()
    {
        $content = <<<HTML
<html>
<title>Hello</title>
<body>
<h1 class="example">Sample text</h1>
<ul>
<li><a href="#foo">foo</a></li>
<li><a href="#bar">bar</a></li>
<li><a href="#baz">baz</a></li>
</ul>
</body>
</html>
HTML;
        self::$parser = new Regex($content);
    }

    public function testDefaults()
    {
        $parser = self::$parser;
        $this->assertEquals('~', $parser->getDelimiters());
        $this->assertEquals('isu', $parser->getModifiers());
    }

    public function testSetGet()
    {
        $parser = self::$parser;

        $delimiters = '!';
        $modifiers = 'i';

        $parser->setDelimeters($delimiters);
        $parser->setModifiers($modifiers);

        $this->assertEquals($delimiters, $parser->getDelimiters());
        $this->assertEquals($modifiers, $parser->getModifiers());
    }

    public function testMatch()
    {
        $this->assertEquals('Sample text', self::$parser->match('<h1.*?>(.*)</h1>'));
        $this->assertEquals('example', self::$parser->match('<h1 class="(?<class>.*?)"'));
        $this->assertEquals('example', self::$parser->match('<h1 class="(?<class>.*?)"', 'class'));
    }

    public function testMatchAll()
    {
        $expected = ['foo', 'bar', 'baz'];
        $this->assertEquals($expected, self::$parser->matchAll('<li><a .*?>(.*?)</a></li>'));

        $expected = ['#foo', '#bar', '#baz'];
        $this->assertEquals($expected, self::$parser->matchAll('<li><a href="(?<href>.*?)">(.*?)</a></li>'));
        $this->assertEquals($expected, self::$parser->matchAll('<li><a href="(?<href>.*?)">(.*?)</a></li>', 'href'));
    }
}
