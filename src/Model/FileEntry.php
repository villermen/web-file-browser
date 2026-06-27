<?php

namespace Villermen\WebFileBrowser\Model;

readonly class FileEntry extends Entry
{
    public function __construct(
        string $name,
        string $path,
        public string $size,
        public int $bytes,
        public \DateTimeInterface $modified,
    ) {
        parent::__construct($name, $path);
    }
}
