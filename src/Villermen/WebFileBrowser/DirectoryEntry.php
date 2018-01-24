<?php

namespace Villermen\WebFileBrowser;

class DirectoryEntry extends Entry
{
    public function __construct(string $name, string $path)
    {
        parent::__construct($name, $path);
    }
}