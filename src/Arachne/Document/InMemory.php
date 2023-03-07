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
    private array $documents = [];
    private string $storage;

    public function __construct(?string $storage = null)
    {
        $this->storage = $storage?? sys_get_temp_dir();
        if (file_exists($this->storage . '/arachne_documents.php')) {
            $this->documents = require $this->storage . '/arachne_documents.php';
        }
        register_shutdown_function(function () {
            if (!file_exists($this->storage)) {
                mkdir($this->storage);
            }
            $data = '<?php return ' . var_export($this->documents, true) . ';';
            file_put_contents($this->storage . '/arachne_documents.php', $data);
        });
    }

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
        $this->documents[$type][$id] = $data;
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
        $this->documents[$type][$id] = array_replace_recursive($this->documents[$type][$id]??[], $data);
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
        return $this->documents[$type][$id];
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
        unset($this->documents[$type][$id]);
    }

    /**
     * @param string|null $type
     * @return mixed
     */
    public function getIterator(string $type = null)
    {
        if (null !== $type) {
            if (!empty($this->documents[$type])) {
                return new \ArrayIterator($this->documents[$type]);
            } else {
                return new \ArrayIterator([]);
            }
        } else {
            $storage = [];
            foreach ($this->documents as $type => $data) {
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
        if (file_exists($this->storage . '/arachne_documents.php')) {
            @unlink($this->storage . '/arachne_documents.php');
        }
        $this->documents = [];
    }

    /**
     * @param string|null $type
     * @return mixed
     */
    public function count(string $type = null) : int
    {
        if (null !== $type) {
            return count($this->documents[$type]);
        }
        $count = 0;
        foreach ($this->documents as $type => $data) {
            $count += count($data);
        }
        return $count;
    }

    /**
     * @return mixed
     */
    public function getTypes()
    {
        return array_keys($this->documents);
    }

    /**
     * @param $type
     * @param $id
     * @return bool
     */
    public function exists(string $type, string $id)
    {
        return isset($this->documents[$type][$id]);
    }
}
