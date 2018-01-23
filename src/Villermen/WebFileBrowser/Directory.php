<?php

namespace Villermen\WebFileBrowser;


use DirectoryIterator;
use Villermen\DataHandling\DataHandling;

class Directory
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var bool
     */
    protected $displayWebpages;

    /**
     * @var bool
     */
    protected $displayDirectories;

    /**
     * @var bool
     */
    protected $displayFiles;

    /**
     * @var string[]
     */
    protected $webpageBlacklist;

    /**
     * @var string[]
     */
    protected $directoryBlacklist;

    /**
     * @var string[]
     */
    protected $fileBlacklist;

    /**
     * @var string[]
     */
    protected $webpageWhitelist;

    /**
     * @var string[]
     */
    protected $directoryWhitelist;

    /**
     * @var string[]
     */
    protected $fileWhitelist;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var WebpageEntry[]|null
     */
    private $webpages = null;

    /**
     * @var DirectoryEntry[]|null
     */
    private $directories = null;

    /**
     * @var FileEntry[]|null
     */
    private $files = null;

    public function __construct(string $path, Configuration $configuration,  bool $displayWebpages, bool $displayDirectories, bool $displayFiles,
        array $webpageBlacklist, array $directoryBlacklist, array $fileBlacklist, array $webpageWhitelist,
        array $directoryWhitelist, array $fileWhitelist, string $description)
    {
        $this->path = $path;
        $this->configuration = $configuration;
        $this->displayWebpages = $displayWebpages;
        $this->displayDirectories = $displayDirectories;
        $this->displayFiles = $displayFiles;
        $this->webpageBlacklist = $webpageBlacklist;
        $this->directoryBlacklist = $directoryBlacklist;
        $this->fileBlacklist = $fileBlacklist;
        $this->webpageWhitelist = $webpageWhitelist;
        $this->directoryWhitelist = $directoryWhitelist;
        $this->fileWhitelist = $fileWhitelist;
        $this->description = $description;
    }

    /**
     * @return bool
     */
    public function canDisplayWebpages(): bool
    {
        return $this->displayWebpages;
    }

    /**
     * @return bool
     */
    public function canDisplayDirectories(): bool
    {
        return $this->displayDirectories;
    }

    /**
     * @return bool
     */
    public function canDisplayFiles(): bool
    {
        return $this->displayFiles;
    }

    /**
     * Scans the directory
     */
    private function fetchEntries(): void
    {
        if ($this->webpages !== null) {
            return;
        }

        $this->webpages = [];
        $this->directories = [];
        $this->files = [];

        foreach(new DirectoryIterator($this->path) as $fileInfo) {
            $filename = $fileInfo->getFilename();

            // Make sure the entry is presentable
            if ($fileInfo->isReadable() && $filename[0] != ".") {
                if ($fileInfo->isDir()) {
                    $path = DataHandling::formatDirectory($fileInfo->getPathname());

                    if ($this->canDisplayWebpages() && $this->passesWebpageFilter($filename)) {
                        // Webpages need to have an index file present
                        foreach($this->configuration->getIndexFiles() as $indexFile) {
                            if (file_exists(DataHandling::mergePaths($path, $indexFile))) {
                                $this->webpages[] = new WebpageEntry($filename, $this->configuration->getUrl($path));

                                break;
                            }
                        }
                    }

                    if ($this->canDisplayDirectories() && $this->passesDirectoryFilter($filename)) {
                        // Directories need to be accessible by this browser
                        if ($this->configuration->isDirectoryAccessible($path)) {
                            $this->directories[] = new DirectoryEntry($filename, $this->configuration->getBrowserUrl($path));
                        }
                    }
                } elseif ($this->canDisplayFiles() && $fileInfo->isFile() && $this->passesFileFilter($filename)) {
                    $path = DataHandling::formatPath($fileInfo->getPathname());

                    $this->files[] = new FileEntry($filename, $this->configuration->getUrl($path),
                        DataHandling::formatBytesize($fileInfo->getSize()), $fileInfo->getSize());
                }
            }
        }
    }

    private function passesWebpageFilter($filename): bool
    {
        if (DataHandling::matchesFilterInsensitive($filename, $this->webpageWhitelist)) {
            return true;
        }

        if (DataHandling::matchesFilterInsensitive($filename, $this->webpageBlacklist)) {
            return false;
        }

        return true;
    }

    private function passesDirectoryFilter($filename): bool
    {
        if (DataHandling::matchesFilterInsensitive($filename, $this->directoryWhitelist)) {
            return true;
        }

        if (DataHandling::matchesFilterInsensitive($filename, $this->directoryBlacklist)) {
            return false;
        }

        return true;
    }

    private function passesFileFilter($filename): bool
    {
        if (DataHandling::matchesFilterInsensitive($filename, $this->fileWhitelist)) {
            return true;
        }

        if (DataHandling::matchesFilterInsensitive($filename, $this->fileBlacklist)) {
            return false;
        }

        return true;
    }

    /**
     * @return WebpageEntry[]
     */
    public function getWebpages(): array
    {
        $this->fetchEntries();

        return $this->webpages;
    }

    /**
     * @return DirectoryEntry[]
     */
    public function getDirectories(): array
    {
        $this->fetchEntries();

        return $this->directories;
    }

    /**
     * @return FileEntry[]
     */
    public function getFiles(): array
    {
        $this->fetchEntries();

        return $this->files;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Returns the path to the directory.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    public function isEmpty(): bool
    {
        return count($this->getWebpages()) + count($this->getDirectories()) + count($this->getFiles()) == 0;
    }
}