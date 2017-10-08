<?php

namespace Arachne;

use Respect\Validation\Validator as v;

class Item implements \Serializable
{
    protected $id;
    protected $type = 'default';

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
        if (property_exists($this, $var)) {
            $this->$var = $val;
        } else {
            throw new \DomainException('Setting unknown property ' . $var);
        }
    }

    /**
     * @return bool
     * @throws \Exception
     */
    final public function validate()
    {
        $validator = $this->getValidator();
        if (!$validator || !$validator instanceof \Respect\Validation\Validator) {
            throw new \Exception(
                sprintf('Validator expected to be \Respect\Validation\Validator, %s given', gettype($validator))
            );
        }
        $validator->attribute('id', v::notEmpty()->scalarVal())
            ->attribute('type', v::notEmpty()->alnum());
        return $validator->assert($this);
    }


    /**
     * @return array
     */
    final public function asArray(): array
    {
        return get_object_vars($this);
    }


    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        return serialize($this->asArray());
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    public static function uuid()
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        $fields = [
            'time_low' => substr($hex, 0, 8),
            'time_mid' => substr($hex, 8, 4),
            'time_hi_and_version' => substr($hex, 12, 4),
            'clock_seq_hi_and_reserved' => substr($hex, 16, 2),
            'clock_seq_low' => substr($hex, 18, 2),
            'node' => substr($hex, 20, 12),
        ];

        return vsprintf(
            '%08s-%04s-%04s-%02s%02s-%012s',
            $fields
        );
    }

}
