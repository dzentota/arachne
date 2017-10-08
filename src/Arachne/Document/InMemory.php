<?php

namespace Arachne\Document;

/**
 * Class InMemory
 * @package Arachne\Document
 */
class InMemory implements DocumentInterface
{
    /**
     * @var array
     */
    private $storage = [];

    /**
     * @param string $type
     * @param string $id
     * @param array $data
     * @return mixed
     */
    public function create(string $type, string $id, array $data)
    {
        if ($this->exists($type, $id)) {
            throw new \DomainException('Document already exists');
        }
        $this->storage[$type][$id] = $data;
    }

    /**
     * @param string $type
     * @param string $id
     * @param array $data
     * @return mixed
     */
    public function update(string $type, string $id, array $data)
    {
        if (!$this->exists($type, $id)) {
            throw new \DomainException('Document does not exist');
        }
        $this->storage[$type][$id] = array_replace_recursive($this->storage[$type][$id]??[], $data);
    }

    /**
     * @param string $type
     * @param string $id
     * @return mixed
     */
    public function get(string $type, string $id)
    {
        if (!$this->exists($type, $id)) {
            return null;
        }
        return $this->storage[$type][$id];
    }

    /**
     * @param string $type
     * @param string $id
     * @return mixed
     */
    public function delete(string $type, string $id)
    {
        if (!$this->exists($type, $id)) {
            throw new \DomainException('Document does not exist');
        }
        unset($this->storage[$type][$id]);
    }

    /**
     * @param string|null $type
     * @return mixed
     */
    public function getIterator(string $type = null)
    {
        if (null !== $type) {
            if (!empty($this->storage[$type])) {
                return new \ArrayIterator($this->storage[$type]);
            } else {
                return new \ArrayIterator([]);
            }
        } else {
            $storage = [];
            foreach ($this->storage as $type => $data) {
                foreach ($data as $id => $doc) {
                    $storage[$id] = $doc;
                }
            }
            return new \ArrayIterator($storage);
        }
    }

    /**
     * @return mixed
     */
    public function clear()
    {
        $this->storage = [];
    }

    /**
     * @param string|null $type
     * @return mixed
     */
    public function count(string $type = null) : int
    {
        if (null !== $type) {
            return count($this->storage[$type]);
        }
        $count = 0;
        foreach ($this->storage as $type => $data) {
            $count += count($data);
        }
        return $count;
    }

    /**
     * @return mixed
     */
    public function getTypes()
    {
        return array_keys($this->storage);
    }

    /**
     * @param $type
     * @param $id
     * @return bool
     */
    public function exists(string $type, string $id)
    {
        return isset($this->storage[$type][$id]);
    }
}
