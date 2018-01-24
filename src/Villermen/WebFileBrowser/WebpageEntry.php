<?php

namespace Villermen\WebFileBrowser;

class WebpageEntry extends Entry
{
    public function __construct(string $name, string $path)
    {
        parent::__construct($name, $path);
    }
}