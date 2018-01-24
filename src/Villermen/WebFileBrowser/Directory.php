<?php

namespace Villermen\WebFileBrowser;


use DateTime;
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
     * Scans the directory and populates the entry fields if not already done.
     */
    private function fetchEntries()
    {
        if ($this->webpages !== null) {
            return;
        }

        $this->webpages = [];
        $this->directories = [];
        $this->files = [];

        foreach(new DirectoryIterator($this->path) as $fileInfo) {
            $filename = $fileInfo->getFilename();

            // Make sure the entry is presentable (readable and not hidden)
            if ($fileInfo->isReadable() && $filename[0] != ".") {
                if ($fileInfo->isDir()) {
                    $path = DataHandling::formatDirectory($fileInfo->getPathname());

                    if ($this->canDisplayWebpages() && $this->passesWebpageFilter($filename)) {
                        // Webpages need to have an index file present
                        foreach($this->configuration->getIndexFiles() as $indexFile) {
                            if (file_exists(DataHandling::mergePaths($path, $indexFile))) {
                                $this->webpages[] = new WebpageEntry($filename, $path);

                                break;
                            }
                        }
                    }

                    if ($this->canDisplayDirectories() && $this->passesDirectoryFilter($filename)) {
                        // Directories need to be accessible by this browser
                        if ($this->configuration->isDirectoryAccessible($path)) {
                            $this->directories[] = new DirectoryEntry($filename, $path);
                        }
                    }
                } elseif ($this->canDisplayFiles() && $fileInfo->isFile() && $this->passesFileFilter($filename)) {
                    $path = DataHandling::formatPath($fileInfo->getPathname());

                    $this->files[] = new FileEntry(
                        $filename, $path, DataHandling::formatBytesize($fileInfo->getSize()), $fileInfo->getSize(),
                        (new DateTime())->setTimestamp($fileInfo->getMTime())
                    );
                }
            }
        }

        // Sort entries
        $sortFunction = function(Entry $entry1, Entry $entry2) {
            return strnatcmp($entry1->getName(), $entry2->getName());
        };

        usort($this->webpages, $sortFunction);
        usort($this->directories, $sortFunction);
        usort($this->files, $sortFunction);
    }

    private function passesWebpageFilter($filename): bool
    {
        // Whitelist is only used if it is not empty, and overrules the blacklist
        if (count($this->webpageWhitelist)) {
            return DataHandling::matchesFilterInsensitive($filename, $this->webpageWhitelist);
        }

        return !DataHandling::matchesFilterInsensitive($filename, $this->webpageBlacklist);
    }

    private function passesDirectoryFilter($filename): bool
    {
        if (count($this->directoryWhitelist)) {
            return DataHandling::matchesFilterInsensitive($filename, $this->directoryWhitelist);
        }

        return !DataHandling::matchesFilterInsensitive($filename, $this->directoryBlacklist);
    }

    private function passesFileFilter($filename): bool
    {
        if (count($this->fileWhitelist)) {
            return DataHandling::matchesFilterInsensitive($filename, $this->fileWhitelist);
        }

        return !DataHandling::matchesFilterInsensitive($filename, $this->fileBlacklist);
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