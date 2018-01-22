<?php

namespace Villermen\WebFileBrowser;

class FileEntry
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $size;

    /**
     * @var int
     */
    protected $bytes;

    /**
     * @param string $name
     * @param string $url
     * @param string $size
     * @param int $bytes
     */
    public function __construct(string $name, string $url, string $size, int $bytes)
    {
        $this->name = $name;
        $this->url = $url;
        $this->size = $size;
        $this->bytes = $bytes;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getSize(): string
    {
        return $this->size;
    }

    /**
     * @return int
     */
    public function getBytes(): int
    {
        return $this->bytes;
    }
}