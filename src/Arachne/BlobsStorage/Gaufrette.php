<?php

namespace Arachne\BlobsStorage;

use Gaufrette\Filesystem;
use Arachne\Resource;
use Psr\Log\LoggerInterface;

/**
 * Class Gaufrette
 * @package Arachne\BlobsStorage
 */
class Gaufrette implements BlobsStorageInterface
{
    /**
     * Max length of filename
     */
    const MAX_NAME = 255;

    /**
     * @var string
     */
    private $indexFileName;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var bool
     */
    private $useHashFilename;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @return Filesystem
     */
    public function getFilesystem()
    {
        return $this->filesystem;
    }

    /**
     * @return boolean
     */
    public function getUseHashFilename()
    {
        return $this->useHashFilename;
    }

    /**
     * @return string
     */
    public function getIndexFileName()
    {
        return $this->indexFileName;
    }

    /**
     * Gaufrette constructor.
     * @param LoggerInterface $logger
     * @param Filesystem $filesystem
     * @param bool $useHashFilename
     * @param string $indexFileName
     */
    public function __construct(LoggerInterface $logger, Filesystem $filesystem, bool $useHashFilename = false, $indexFileName = 'index.dat')
    {
        $this->logger = $logger;
        $this->filesystem = $filesystem;
        $this->useHashFilename = $useHashFilename;
        $this->indexFileName = $indexFileName;
    }

    /**
     * @param Resource $resource
     * @return string
     */
    protected function getPath(Resource $resource)
    {
        $url = $resource->getHttpRequest()->getUri();
        $url = preg_replace('/^[a-z0-9]+:\/\//', '', $url);
        $sections = explode('/', $url);
        $sections = array_map(
            function ($section) use ($resource) {
                $section = rawurldecode($section);
                $section = $this->truncate($section, $resource);
                return rawurldecode($section);
            },
            $sections
        );

        $last = end($sections);
        if ($last === '') {
            array_splice($sections, -1, 1, $this->getIndexFileName());
        }
        if ($this->getUseHashFilename()) {
            array_splice($sections, -1, 1, sha1($last));
        }

        return implode(DIRECTORY_SEPARATOR, $sections);
    }

    /**
     * @param string $fileName
     * @param \Arachne\Resource|Resource $resource
     * @return string
     */
    protected function truncate(string $fileName, Resource $resource)
    {
        if ($this->strlen($fileName) > self::MAX_NAME) {
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $extensionLength = $this->strlen($extension);
            if ($extensionLength > 0 && $extensionLength <= 4) {
                $suffix = '.' . $extension;
            } else {
                $suffix = '';
            }
            $fileName = $this->substr($fileName, 0, self::MAX_NAME - 8 - $this->strlen($suffix))
                . '-' . $this->substr($resource->getHash(), 0, 7) . $suffix;
        }

        return $fileName;
    }

    private function strlen(string $str)
    {
        return mb_strlen($str, 'utf-8');
    }

    /**
     * @param string $str
     * @param int $start
     * @param null|int $length
     * @return string
     */
    private function substr(string $str, int $start, int $length = null)
    {
        return mb_substr($str, $start, $length, 'utf-8');
    }


    /**
     * Returns an array of all keys (files and directories)
     *
     * @return array
     */
    public function keys()
    {
        $this->logger->debug('Getting keys from the Blob Storage');
        return $this->getFilesystem()->keys();
    }

    /**
     * @param \Arachne\Resource $resource
     * @param string $contents
     * @param bool $overwrite
     * @return mixed
     */
    public function write(Resource $resource, string $contents = '', bool $overwrite = true)
    {
        $path = $this->getPath($resource);
        $this->logger->debug(sprintf('Saving blob resource [%s] to [%s]', $resource->getUrl(), $path));
        $this->getFilesystem()->write($path, $contents, $overwrite);
        return $path;
    }

    /**
     * @param \Arachne\Resource|Resource $resource
     * @return mixed
     */
    public function read(Resource $resource)
    {
        $path = $this->getPath($resource);
        $this->logger->debug(sprintf('Reading [%s] from the Blobs Storage', $path));
        return $this->getFilesystem()->read($path);
    }

    /**
     * @param \Arachne\Resource|Resource $resource
     * @return mixed
     */
    public function exists(Resource $resource)
    {
        $path = $this->getPath($resource);
        $this->logger->debug(sprintf('Checking if [%s] exists in Blobs Storage',$path));
        return $this->getFilesystem()->has($path);
    }

    /**
     * @param \Arachne\Resource|Resource $resource
     * @return mixed
     */
    public function delete(Resource $resource)
    {
        $path = $this->getPath($resource);
        $this->logger->debug(sprintf('Deleting [%s] from the Blobs Storage', $path));
        return $this->getFilesystem()->delete($path);
    }

    /**
     *
     */
    public function clear()
    {
        $this->logger->debug('Clearing Blobs Storage');
        $keys = $this->keys();
        foreach($keys as $key) {
            $this->getFilesystem()->delete($key);
        }
    }
}
