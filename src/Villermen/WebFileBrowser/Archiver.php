<?php

namespace Villermen\WebFileBrowser;

use Exception;
use Villermen\DataHandling\DataHandling;
use ZipArchive;

class Archiver
{
    /** @var Configuration */
    protected $configuration;

    /** @var Directory */
    protected $directory;

    /** @var int */
    private $directoryChecksum = null;

    /** @var int */
    private $contentChecksum = null;

    public function __construct(Configuration $configuration, Directory $directory)
    {
        $this->configuration = $configuration;
        $this->directory = $directory;
    }

    public function canArchive()
    {
        return count($this->directory->getFiles()) > 0;
    }

    public function getArchivePath()
    {
        $relativeDirectoryPath = "/" . $this->configuration->getRelativePath($this->directory->getPath());

        if ($this->directoryChecksum) {
            // Ensure that the actual root directory name is not exposed
            if (strlen($relativeDirectoryPath) > 1) {
                $basename = basename($relativeDirectoryPath);
            } else {
                $basename = "root";
            }

            return DataHandling::formatPath(
                $this->configuration->getBrowserBaseDirectory(), "cache",
                $this->directoryChecksum . $this->contentChecksum, $basename . ".zip"
            );
        }

        $this->directoryChecksum = hash("crc32b", $relativeDirectoryPath);

        $contentHash = hash_init("crc32b");
        foreach($this->directory->getFiles() as $file) {
            hash_update($contentHash, $file->getName());
            hash_update($contentHash, $file->getBytes());
            hash_update($contentHash, $file->getModified()->getTimestamp());
        }
        $this->contentChecksum = hash_final($contentHash);

        return $this->getArchivePath();
    }

    /**
     * Returns whether an archive exists for the directory in its current state.
     *
     * @return bool
     */
    public function isArchiveReady()
    {
        return file_exists($this->getArchivePath());
    }

    /**
     * Returns whether the archive is being created.
     *
     * @return bool
     */
    public function isArchiving()
    {
        return file_exists($this->getLockPath());
    }

    /**
     * Create the archive.
     *
     * @throws Exception
     */
    public function createArchive()
    {
        if ($this->isArchiving()) {
            throw new Exception("Archive is already being created.");
        }

        try {
            $archiveDirectory = dirname($this->getArchivePath());

            if (!file_exists($archiveDirectory)) {
                if (!mkdir($archiveDirectory, 0755)) {
                    throw new Exception("Could not create archive directory");
                }
            }

            if (!touch($this->getLockPath())) {
                throw new Exception("Could not create lock file.");
            }

            // Archiver! Do the Thing!
            $archive = new ZipArchive();

            if (!$archive->open($this->getLockPath(), ZipArchive::OVERWRITE)) {
                throw new Exception("Could not create the archive.");
            }

            foreach ($this->directory->getFiles() as $file) {
                if (!$archive->addFile($file->getPath(), $file->getName())) {
                    throw new Exception(sprintf("Could not add %s to the archive.", $file->getName()));
                }
            }

            if (!$archive->close() || !rename($this->getLockPath(), $this->getArchivePath())) {
                throw new Exception("Could not finalize the archive.");
            }
        } finally {
            if (file_exists($this->getLockPath())) {
                unlink($this->getLockPath());
            }
        }
    }

    /**
     * Loops until the archive file has been created.
     */
    public function waitForCreation()
    {
        if (!$this->isArchiving() && !$this->isArchiveReady()) {
            throw new Exception("Archive is not being created, so waiting for that is pretty pointless.");
        }

        do {
            if (file_exists($this->getArchivePath())) {
                break;
            }

            sleep(3);
        } while (true);
    }

    /**
     * Removes obsolete previous versions of the archive for this directory.
     */
    public function removeObsoleteVersions()
    {
        $archivePath = $this->getArchivePath();

        $matchedDirectories = glob(dirname($archivePath, 2) . "/" . $this->directoryChecksum . "*", GLOB_ONLYDIR);

        foreach($matchedDirectories as $matchedDirectory) {
            if (!DataHandling::endsWith(basename($matchedDirectory), $this->contentChecksum)) {
                // Remove directory (and files directly in it)
                $matchedFiles = glob($matchedDirectory. "/*");

                foreach($matchedFiles as $matchedFile) {
                    @unlink($matchedFile);
                }

                @rmdir($matchedDirectory);
            }
        }
    }

    private function getLockPath()
    {
        return DataHandling::formatPath($this->getArchivePath() . ".lock");
    }
}
