<?php
declare(strict_types=1);

namespace Arachne\Parser;

class Regex
{
    private string $delimiters = '~';
    private string $modifiers = 'isu';

    /**
     * @return string
     */
    public function getDelimiters(): string
    {
        return $this->delimiters;
    }

    /**
     * @param string $delimiters
     * @return Regex
     */
    public function setDelimeters(string $delimiters): static
    {
        $this->delimiters = $delimiters;
        return $this;
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
     * @return Regex
     */
    public function setModifiers(string $modifiers): static
    {
        $this->modifiers = $modifiers;
        return $this;
    }

    public function __construct(private readonly string $content)
    {
    }
    
    public function match(string $regex, $subPatternName = 1)
    {
        return $this->evaluate($regex, $subPatternName);
    }
    
    public function matchAll(string $regex, $subPatternName = 1)
    {
        return $this->evaluate($regex, $subPatternName, true);
    }

    protected function evaluate(string $regex, $subPatternName, $matchAll = false ): mixed
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
