<?php

namespace Villermen\WebFileBrowser\Model;

/**
 * Base class for entries.
 */
abstract class Entry
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $path;

    protected function __construct(string $name, string $path)
    {
        $this->name = $name;
        $this->path = $path;
    }

    /**
     * Returns the file or directory name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the absolute path to the file or directory.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
