<?php

namespace Villermen\WebFileBrowser\Service;

use DateTime;
use DirectoryIterator;
use Villermen\DataHandling\DataHandling;
use Villermen\WebFileBrowser\Model\Entry;
use Villermen\WebFileBrowser\Model\FileEntry;

class Directory
{
    /** @var Entry[]|null */
    private ?array $webpages = null;

    /** @var Entry[]|null */
    private ?array $directories = null;

    /** @var FileEntry[]|null */
    private ?array $files = null;

    /**
     * @param string[] $webpageBlacklist
     * @param string[] $directoryBlacklist
     * @param string[] $fileBlacklist
     * @param string[] $webpageWhitelist
     * @param string[] $directoryWhitelist
     * @param string[] $fileWhitelist
     */
    public function __construct(
        private readonly string $path,
        private readonly Configuration $configuration,
        private readonly bool $displayWebpages,
        private readonly bool $displayDirectories,
        private readonly bool $displayFiles,
        private readonly array $webpageBlacklist,
        private readonly array $directoryBlacklist,
        private readonly array $fileBlacklist,
        private readonly array $webpageWhitelist,
        private readonly array $directoryWhitelist,
        private readonly array $fileWhitelist,
        private readonly string $description,
        private readonly bool $archivable
    ) {
    }

    public function canDisplayWebpages(): bool
    {
        return $this->displayWebpages;
    }

    public function canDisplayDirectories(): bool
    {
        return $this->displayDirectories;
    }

    public function canDisplayFiles(): bool
    {
        return $this->displayFiles;
    }

    /**
     * Scans the directory and populates the entry fields if not already done.
     */
    private function fetchEntries(): void
    {
        if ($this->webpages !== null) {
            return;
        }

        $this->webpages = [];
        $this->directories = [];
        $this->files = [];

        foreach (new DirectoryIterator($this->path) as $fileInfo) {
            $filename = $fileInfo->getFilename();

            // Make sure the entry is presentable (readable and not hidden)
            if ($fileInfo->isReadable() && $filename[0] != '.') {
                if ($fileInfo->isDir()) {
                    $path = DataHandling::formatDirectory($fileInfo->getPathname());

                    if ($this->canDisplayWebpages() && $this->passesWebpageFilter($filename)) {
                        // Webpages need to have an index file present
                        foreach ($this->configuration->getIndexFiles() as $indexFile) {
                            if (file_exists(DataHandling::mergePaths($path, $indexFile))) {
                                $this->webpages[] = new Entry($filename, $path);

                                break;
                            }
                        }
                    }

                    if ($this->canDisplayDirectories() && $this->passesDirectoryFilter($filename)) {
                        // Directories need to be accessible by this browser
                        if ($this->configuration->isDirectoryAccessible($path)) {
                            $this->directories[] = new Entry($filename, $path);
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
        $sortFunction = function (Entry $entry1, Entry $entry2) {
            return strnatcmp($entry1->name, $entry2->name);
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
     * @return Entry[]
     */
    public function getWebpages(): array
    {
        $this->fetchEntries();

        return $this->webpages;
    }

    /**
     * @return Entry[]
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

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Returns the path to the directory.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    public function isEmpty(): bool
    {
        return count($this->getWebpages()) + count($this->getDirectories()) + count($this->getFiles()) == 0;
    }

    public function isArchivable(): bool
    {
        return $this->archivable;
    }

    public function canArchive(): bool
    {
        return $this->isArchivable() && count($this->getFiles()) > 0;
    }
}
