<?php

namespace Villermen\WebFileBrowser\Service;

use Exception;
use Villermen\DataHandling\DataHandling;
use Villermen\DataHandling\DataHandlingException;
use ZipArchive;

class Archiver
{
    /** @var int */
    private static $creationTimeLimit = 5 * 60;

    /** @var Configuration */
    protected $configuration;

    /** @var Directory */
    protected $directory;

    /** @var UrlGenerator */
    protected $urlGenerator;

    /** @var int */
    private $directoryChecksum = null;

    /** @var int */
    private $contentChecksum = null;

    public function __construct(Configuration $configuration, Directory $directory, UrlGenerator $urlGenerator)
    {
        $this->configuration = $configuration;
        $this->directory = $directory;
        $this->urlGenerator = $urlGenerator;
    }

    public function canArchive()
    {
        return $this->directory->isArchivable() && count($this->directory->getFiles()) > 0;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getArchivePath()
    {
        $relativeDirectoryPath = "/" . $this->urlGenerator->getRelativePath($this->directory->getPath());

        if (!$this->directoryChecksum) {
            // Calcuate checksums
            $this->directoryChecksum = hash("crc32b", $relativeDirectoryPath);

            $contentHash = hash_init("crc32b");
            foreach($this->directory->getFiles() as $file) {
                hash_update($contentHash, $file->getName());
                hash_update($contentHash, $file->getBytes());
                hash_update($contentHash, $file->getModified()->getTimestamp());
            }
            $this->contentChecksum = hash_final($contentHash);
        }

        // Ensure that the actual root directory name is not exposed as archive filename
        if (strlen($relativeDirectoryPath) > 1) {
            $basename = basename($relativeDirectoryPath);
        } else {
            $basename = "root";
        }

        return DataHandling::formatPath(
            $this->getOrCreateCacheDirectory(), $this->directoryChecksum . $this->contentChecksum, $basename . ".zip"
        );
    }

    /**
     * @return string
     * @throws Exception
     */
    private function getOrCreateCacheDirectory()
    {
        $cacheDirectory = DataHandling::formatDirectory($this->urlGenerator->getBrowserBaseDirectory(), "cache");

        if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0775)) {
            throw new Exception("Failed to create cache directory.");
        }

        return $cacheDirectory;
    }

    /**
     * @return string
     * @throws Exception
     */
    private function getLockPath()
    {
        return $this->getArchivePath() . ".lock";
    }

    /**
     * Returns whether an archive exists for the directory in its current state.
     * If it does, it will update its modification time so that it does not expire for a while.
     *
     * @return bool
     * @throws Exception
     */
    public function isArchiveReady()
    {
        $archivePath = $this->getArchivePath();

        if (!is_file($archivePath)) {
            return false;
        }

        @touch($archivePath);

        return true;
    }

    /**
     * Returns whether the archive is being created.
     *
     * @return bool
     * @throws Exception
     */
    public function isArchiving()
    {
        $lockPath = $this->getLockPath();

        $lockFileExists = is_file($lockPath);

        if (!$lockFileExists) {
            return false;
        }

        // Remove the lock file if the maximum creation time has expired
        if (time() - filemtime($lockPath) > self::$creationTimeLimit) {
            return !@unlink($lockPath);
        }

        return true;
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

        set_time_limit(self::$creationTimeLimit);

        try {
            $archiveDirectory = dirname($this->getArchivePath());

            if (!file_exists($archiveDirectory)) {
                if (!mkdir($archiveDirectory, 0775)) {
                    throw new Exception("Could not create archive directory");
                }
            }

            if (!touch($this->getLockPath())) {
                throw new Exception("Could not create lock file.");
            }

            // Archiver! Do the Thing!
            $archive = new ZipArchive();

            if (!@$archive->open($this->getLockPath(), ZipArchive::OVERWRITE)) {
                throw new Exception("Could not create the archive.");
            }

            foreach ($this->directory->getFiles() as $file) {
                if (!@$archive->addFile($file->getPath(), $file->getName())) {
                    throw new Exception(sprintf("Could not add %s to the archive.", $file->getName()));
                }
            }

            if (!@$archive->close() || !rename($this->getLockPath(), $this->getArchivePath())) {
                throw new Exception("Could not finalize the archive.");
            }
        } finally {
            try {
                if (isset($archive)) {
                    @$archive->close();
                }
            } catch (Exception $exception) {
            }

            if (file_exists($this->getLockPath())) {
                @unlink($this->getLockPath());
            }
        }
    }

    /**
     * Loops until the archive file has been created.
     *
     * @throws Exception
     */
    public function waitForCreation()
    {
        if (!$this->isArchiving() && !$this->isArchiveReady()) {
            throw new Exception("Archive is not being created, so waiting for that is pretty pointless.");
        }

        set_time_limit(self::$creationTimeLimit);

        do {
            if (file_exists($this->getArchivePath())) {
                break;
            }

            sleep(3);
        } while (true);
    }

    /**
     * Removes obsolete previous versions of the archive for this directory.
     *
     * @throws Exception
     */
    public function deleteObsoleteVersions()
    {
        $archivePath = $this->getArchivePath();

        // Find all archives with the same directory checksum, and delete the ones that are not the current one
        $matchedArchives = glob($this->getOrCreateCacheDirectory() . $this->directoryChecksum . "*/*.zip");
        foreach($matchedArchives as $matchedArchive) {
            if ($matchedArchive !== $archivePath) {
                $this->deleteArchive($matchedArchive);
            }
        }
    }

    /**
     * Removes all archives that haven't been downloaded for the configured lifetime.
     * Take note that this may remove an archive that we are about to download.
     *
     * @throws Exception
     */
    public function deleteExpiredArchives()
    {
        $archives = glob($this->getOrCreateCacheDirectory() . "*/*.zip");

        foreach($archives as $archive) {
            if (time() - filemtime($archive) > $this->configuration->getArchiveLifetime()) {
                $this->deleteArchive($archive);
            }
        }
    }

    /**
     * Deletes an archive, including its directory.
     *
     * @param $archive
     * @throws Exception
     */
    private function deleteArchive($archive)
    {
        $archiveDirectory = dirname($archive) . "/";

        // Delete all files in directory
        $matchedFiles = glob($archiveDirectory . "*");
        foreach($matchedFiles as $matchedFile) {
            if (!@unlink($matchedFile)) {
                throw new Exception("Could not delete cache file " . $matchedFile);
            }
        }

        // Delete (now empty) directory
        if (!@rmdir($archiveDirectory)) {
            throw new Exception("Could not delete cache directory " . $archiveDirectory);
        }
    }
}
