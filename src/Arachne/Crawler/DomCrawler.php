<?php

declare(strict_types=1);

namespace Arachne\Crawler;
use Symfony\Component\DomCrawler\Crawler;

class DomCrawler extends GenericCrawler
{
    private Crawler $crawler;

    /**
     * @param $node
     * @param $currentUri
     * @param $baseHref
     */
    public function __construct($node = null, $currentUri = null, $baseHref = null)
    {
        $this->crawler = new Crawler($node, $currentUri, $baseHref);
    }

    public function setCrawler(Crawler $crawler)
    {
        $this->crawler = $crawler;
    }

    public function getCrawler(): Crawler
    {
        return $this->crawler;
    }

    /**
     * Returns the first node of the current selection.
     */
    public function first(): NullCrawler|static
    {
        $first = $this->crawler->eq(0);
        if (!count($first)) {
            return new NullCrawler();
        }
        $crawler = new static();
        $crawler->setCrawler($first);
        return $crawler;
    }

    /**
     * Returns the last node of the current selection.
     */
    public function last(): NullCrawler|static
    {
        $last = $this->crawler->eq(count($this->crawler) - 1);
        if (!count($last)) {
            return new NullCrawler();
        }
        $crawler = new static();
        $crawler->setCrawler($last);
        return $crawler;
    }

    /**
     * Returns the siblings nodes of the current selection.
     */
    public function siblings(): NullCrawler|static
    {
        if (!count($this->crawler)) {
            return new NullCrawler();
        }
        $crawler = new static();
        $crawler->setCrawler($this->crawler->siblings());
        return $crawler;
    }

    /**
     * Returns the next siblings nodes of the current selection.
     *
     */
    public function nextAll(): NullCrawler|static
    {
        if (!count($this->crawler)) {
            return new NullCrawler();
        }
        $crawler = new static();
        $crawler->setCrawler($this->crawler->nextAll());
        return $crawler;
    }

    /**
     * Returns the previous sibling nodes of the current selection.
     */
    public function previousAll(): NullCrawler|static
    {
        if (!count($this->crawler)) {
            return new NullCrawler();
        }
        $crawler = new static();
        $crawler->setCrawler($this->crawler->previousAll());
        return $crawler;
    }

    public function eq($position): static
    {
        foreach ($this->crawler as $i => $node) {
            if ($i == $position) {
                return $this->createSubCrawler($node);
            }
        }

        return $this->createSubCrawler(null);
    }
    /**
     * Returns the parents nodes of the current selection.
     *
     */
    public function parents(): NullCrawler|static
    {
        if (!count($this->crawler)) {
            return new NullCrawler();
        }
        $crawler = new static();
        $crawler->setCrawler($this->crawler->parents());
        return $crawler;
    }

    /**
     * Returns the children nodes of the current selection.
     */
    public function children(): NullCrawler|static
    {
        if (!count($this->crawler)) {
            return new NullCrawler();
        }
        $crawler = new static();
        $crawler->setCrawler($this->crawler->children());
        return $crawler;
    }

    /**
     * Returns the attribute value of the first node of the list.
     *
     * @param string $attribute The attribute name
     * @return null|NullCrawler|string
     */
    public function attr($attribute): NullCrawler|string|null
    {
        if (!count($this->crawler)) {
            return null;
        }
        return $this->crawler->attr($attribute);
    }

    /**
     * @param $attribute
     * @return Value
     */
    public function attrValue($attribute): Value
    {
        return new Value((string) $this->attr($attribute));
    }

    /**
     * Returns the node name of the first node of the list.
     */
    public function nodeName(): ?string
    {
        if (!count($this->crawler)) {
            return null;
        }
        return $this->crawler->nodeName();
    }

    /**
     * Returns the node value of the first node of the list.
     */
    public function text(): ?string
    {
        if (!count($this->crawler)) {
            return null;
        }

        return $this->crawler->text();
    }

    /**
     * @return Value
     */
    public function textValue(): Value
    {
        return new Value((string) $this->text());
    }

    /**
     * Returns the first node of the list as HTML.
     */
    public function html(): ?string
    {
        if (!count($this->crawler)) {
            return null;
        }
        return $this->crawler->html();
    }

    /**
     * @return Value
     */
    public function htmlValue(): Value
    {
        return new Value((string) $this->html());
    }

    public function filter($selector): NullCrawler|static
    {
        $result = $this->crawler->filter($selector);
        if (!count($result)) {
            return new NullCrawler();
        }
        $crawler = new static();
        $crawler->setCrawler($result);
        return $crawler;
    }

    public function filterXPath($xpath): NullCrawler|static
    {
        $result = $this->crawler->filterXPath($xpath);
        if (!count($result)) {
            return new NullCrawler();
        }
        $crawler = new static();
        $crawler->setCrawler($result);
        return $crawler;
    }

    public function each(\Closure $closure): array
    {
        $data = array();
        foreach ($this->crawler as $i => $node) {
            $data[] = $closure($this->createSubCrawler($node), $i);
        }

        return $data;
    }

    /**
     * Slices the list of nodes by $offset and $length.
     *
     * @param int $offset
     * @param int $length
     *
     * @return self
     */
    public function slice($offset = 0, $length = -1): static
    {
        return $this->createSubCrawler(iterator_to_array(new \LimitIterator($this->crawler, $offset, $length)));
    }

    /**
     * Reduces the list of nodes by calling an anonymous function.
     *
     * To remove a node from the list, the anonymous function must return false.
     *
     * @param \Closure $closure An anonymous function
     *
     * @return self
     */
    public function reduce(\Closure $closure): static
    {
        $nodes = array();
        foreach ($this->crawler as $i => $node) {
            if (false !== $closure($this->createSubCrawler($node), $i)) {
                $nodes[] = $node;
            }
        }

        return $this->createSubCrawler($nodes);
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->crawler, $name], $arguments);
    }

    private function createSubCrawler($nodes): static
    {
        $crawler = new static($nodes);
        return $crawler;
    }

    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count(): int
    {
        return $this->crawler->count();
    }
}