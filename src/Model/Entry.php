<?php

namespace Villermen\WebFileBrowser\Model;

readonly class Entry
{
    /**
     * @param string $name File or directory name.
     * @param string $path Absolute path to the file or directory.
     */
    public function __construct(
        public string $name,
        public string $path
    ) {
    }
}
