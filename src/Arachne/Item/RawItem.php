<?php
declare(strict_types=1);

namespace Arachne\Item;

use Arachne\Serializable;

class RawItem extends GenericItem implements Serializable
{
    private array $data;
    public function __construct(array $data = [])
    {
        $this->id = self::uuid();
        $this->data = $data;
    }

    public function validate(): bool
    {
        return true;
    }

    public function asArray(): array
    {
        return $this->data;
    }

    /**
     * @param $var
     * @return mixed
     * @throws \Exception
     */
    final public function __get($var)
    {
        if (array_key_exists($var, $this->data)) {
            return $this->data[$var];
        }
        throw new \DomainException('Getting unknown property ' . $var);
    }

    /**
     * @param $var
     * @param $val
     */
    final public function __set($var, $val)
    {
        if ( $var !== 'type') {
            $this->data[$var] = $val;
        } else {
            throw new \DomainException('Type is readonly');
        }
    }

    public function __unserialize(array $data): void
    {
        $this->data = $data;
    }
}


