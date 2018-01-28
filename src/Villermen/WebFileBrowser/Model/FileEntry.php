<?php

namespace Villermen\WebFileBrowser\Model;

use DateTime;

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

    /**
     * @var DateTime
     */
    protected $modified;

    public function __construct(string $name, string $path, string $size, int $bytes, DateTime $modified)
    {
        parent::__construct($name, $path);

        $this->size = $size;
        $this->bytes = $bytes;
        $this->modified = $modified;
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

    /**
     * @return DateTime
     */
    public function getModified(): DateTime
    {
        return $this->modified;
    }
}
