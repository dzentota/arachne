<?php

namespace Arachne\Parser;

class Regex
{
    private $delimiters = '~';
    private $modifiers = 'isu';
    private $content;

    /**
     * @return string
     */
    public function getDelimiters(): string
    {
        return $this->delimiters;
    }

    /**
     * @param string $delimiters
     */
    public function setDelimeters(string $delimiters)
    {
        $this->delimiters = $delimiters;
    }

    /**
     * @return string
     */
    public function getModifiers(): string
    {
        return $this->modifiers;
    }

    /**
     * @param string $modifiers
     */
    public function setModifiers(string $modifiers)
    {
        $this->modifiers = $modifiers;
    }

    public function __construct($content)
    {
        $this->content = $content;
    }
    
    public function match(string $regex, $subPatternName = 1)
    {
        return $this->evaluate($regex, $subPatternName);
    }
    
    public function matchAll(string $regex, $subPatternName = 1)
    {
        return $this->evaluate($regex, $subPatternName, true);
    }

    protected function evaluate(string $regex, $subPatternName, $matchAll = false )
    {
        $rule = $this->delimiters . $regex . $this->delimiters . $this->modifiers;
        if ($matchAll) {
            if (preg_match_all($rule, $this->content, $m)) {
                if (isset($m[$subPatternName])) {
                    return $m[$subPatternName];
                }
                throw new \InvalidArgumentException("Unknown subpattern name {$subPatternName}");
            }
        } else {
            if (preg_match($rule, $this->content, $m)) {
                if (isset($m[$subPatternName])) {
                    return $m[$subPatternName];
                }
                throw new \InvalidArgumentException("Unknown subpattern name {$subPatternName}");
            }
        }
        return null;
    }
}
