<?php

namespace Arachne\Item;

use Arachne\Serializable;
use Respect\Validation\Validator;
use Respect\Validation\Validator as v;

class Item extends GenericItem implements Serializable
{
    public function __construct(array $data = [])
    {
        $this->id = static::uuid();
        foreach ($data as $k => $v) {
            if (property_exists($this, $k)) {//exclude type?
                $this->$k = $v;
            }
        }
    }

    /**
     * @return v
     * Usage:
     *  return v::attribute('name', v::scalarVal()->length(1,32))
     *          ->attribute('birthdate', v::date()->age(18));
     */
    protected function getValidator()
    {
        return v::create();
    }

    /**
     * @param $var
     * @return mixed
     * @throws \Exception
     */
    final public function __get($var)
    {
        if (property_exists($this, $var)) {
            return $this->$var;
        }
        throw new \DomainException('Getting unknown property ' . $var);
    }

    /**
     * @param $var
     * @param $val
     * @throws \Exception
     */
    final public function __set($var, $val)
    {
        if (property_exists($this, $var) && $var !== 'type') {
            $this->$var = $val;
        } else {
            throw new \DomainException('Setting unknown property ' . $var);
        }
    }

    /**
     * @return bool
     * @throws \Exception
     */
    final public function validate(): bool
    {
        $validator = $this->getValidator();
        if (!$validator instanceof Validator) {
            throw new \Exception(
                sprintf('Validator expected to be %s, %s given', Validator::class, gettype($validator))
            );
        }
        $validator->attribute('id', v::notEmpty()->scalarVal())
            ->attribute('type', v::notEmpty()->alnum())
            ->assert($this);
        return true;
    }

    /**
     * @return array
     */
    final public function asArray(): array
    {
        return get_object_vars($this);
    }

    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
}
