<?php

namespace Villermen\WebFileBrowser;

class FileEntry extends Entry
{
    /**
     * @var string
     */
    protected $size;

    /**
     * @var int
     */
    protected $bytes;

    public function __construct(string $name, string $path, string $size, int $bytes)
    {
        parent::__construct($name, $path);

        $this->size = $size;
        $this->bytes = $bytes;
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