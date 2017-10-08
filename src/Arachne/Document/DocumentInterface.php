<?php

namespace Arachne\Document;

/**
 * Interface DocumentInterface
 * @package Arachne\Document
 */
interface DocumentInterface
{
    /**
     * @param string $type
     * @param string $id
     * @param array $data
     * @return mixed
     */
    public function create(string $type, string $id, array $data);

    /**
     * @param string $type
     * @param string $id
     * @param array $data
     * @return mixed
     */
    public function update(string $type, string $id, array $data);

    /**
     * @param string $type
     * @param string $id
     * @return mixed
     */
    public function get(string $type, string $id);

    /**
     * @param $type
     * @param $id
     * @return bool
     */
    public function exists(string $type, string $id);

    /**
     * @param string $type
     * @param string $id
     * @return mixed
     */
    public function delete(string $type, string $id);

    /**
     * @param string|null $type
     * @return mixed
     */
    public function getIterator(string $type = null);

    /**
     * @return mixed
     */
    public function clear();

    /**
     * @param string|null $type
     * @return mixed
     */
    public function count(string $type=null) : int;

    /**
     * @return mixed
     */
    public function getTypes();
}
