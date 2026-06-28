<?php

namespace Villermen\WebFileBrowser\Service;

use Villermen\DataHandling\Path;
use Villermen\WebFileBrowser\Exception\ArchiverException;
use Villermen\WebFileBrowser\Exception\UrlGeneratorException;
use ZipArchive;

class Archiver
{
    private const int CREATION_TIME_LIMIT = 5 * 60;

    private ?string $directoryChecksum = null;

    private ?string $contentChecksum = null;

    private readonly string $cacheDirectory;

    private readonly string $archivePath;

    /**
     * @throws ArchiverException
     */
    public function __construct(
        private readonly Configuration $configuration,
        private readonly Directory $directory,
        private readonly UrlGenerator $urlGenerator,
    ) {
        if (!$this->directory->canArchive()) {
            throw new ArchiverException('Directory "%s" can\'t be archived.');
        }

        $this->cacheDirectory = $this->createCacheDirectory();
        $this->archivePath = $this->createArchivePath();
    }

    public function getArchivePath(): string
    {
        return $this->archivePath;
    }

    private function getLockPath(): string
    {
        return $this->getArchivePath() . '.lock';
    }

    /**
     * Returns whether an archive exists for the directory in its current state.
     * If it does, it will update its modification time so that it does not expire for a while.
     */
    public function isArchiveReady(): bool
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
     */
    public function isArchiving(): bool
    {
        $lockPath = $this->getLockPath();

        $lockFileExists = is_file($lockPath);

        if (!$lockFileExists) {
            return false;
        }

        // Remove the lock file if the maximum creation time has expired
        if (time() - filemtime($lockPath) > self::CREATION_TIME_LIMIT) {
            return !@unlink($lockPath);
        }

        return true;
    }

    /**
     * Create the archive.
     *
     * @throws ArchiverException
     */
    public function createArchive(): void
    {
        if ($this->isArchiving()) {
            throw new ArchiverException('Archive is already being created.');
        }

        set_time_limit(self::CREATION_TIME_LIMIT);

        try {
            $archiveDirectory = dirname($this->getArchivePath());

            if (!file_exists($archiveDirectory)) {
                if (!mkdir($archiveDirectory, 0775)) {
                    throw new ArchiverException('Could not create archive directory');
                }
            }

            if (!touch($this->getLockPath())) {
                throw new ArchiverException('Could not create lock file.');
            }

            // Archiver! Do the Thing!
            $archive = new ZipArchive();

            if (!@$archive->open($this->getLockPath(), ZipArchive::OVERWRITE)) {
                throw new ArchiverException('Could not create the archive.');
            }

            foreach ($this->directory->getFiles() as $file) {
                if (!@$archive->addFile($file->path, $file->name)) {
                    throw new ArchiverException(sprintf('Could not add %s to the archive.', $file->name));
                }
            }

            if (!@$archive->close() || !rename($this->getLockPath(), $this->getArchivePath())) {
                throw new ArchiverException('Could not finalize the archive.');
            }
        } finally {
            try {
                if (isset($archive)) {
                    @$archive->close();
                }
            } catch (\Throwable) {
            }

            if (file_exists($this->getLockPath())) {
                @unlink($this->getLockPath());
            }
        }
    }

    /**
     * Loops until the archive file has been created.
     *
     * @throws ArchiverException
     */
    public function waitForCreation(): void
    {
        if (!$this->isArchiving() && !$this->isArchiveReady()) {
            throw new ArchiverException('Archive is not being created, so waiting for that is pretty pointless.');
        }

        set_time_limit(self::CREATION_TIME_LIMIT);

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
     * @throws ArchiverException
     */
    public function deleteObsoleteVersions(): void
    {
        // Find all archives with the same directory checksum, and delete the ones that are not the current one
        $matchedArchives = glob($this->cacheDirectory . $this->directoryChecksum . '*/*.zip');
        foreach($matchedArchives as $matchedArchive) {
            if ($matchedArchive !== $this->getArchivePath()) {
                $this->deleteArchive($matchedArchive);
            }
        }
    }

    /**
     * Removes all archives that haven't been downloaded for the configured lifetime.
     * Take note that this may remove an archive that we are about to download.
     *
     * @throws ArchiverException
     */
    public function deleteExpiredArchives(): void
    {
        $archives = glob($this->cacheDirectory . '*/*.zip');

        foreach($archives as $archive) {
            if (time() - filemtime($archive) > $this->configuration->getArchiveLifetime()) {
                $this->deleteArchive($archive);
            }
        }
    }

    /**
     * Deletes an archive, including its directory.
     *
     * @throws ArchiverException
     */
    private function deleteArchive(string $archive): void
    {
        $archiveDirectory = dirname($archive) . '/';

        // Delete all files in directory
        $matchedFiles = glob($archiveDirectory . '*');
        foreach($matchedFiles as $matchedFile) {
            if (!@unlink($matchedFile)) {
                throw new ArchiverException('Could not delete cache file ' . $matchedFile);
            }
        }

        // Delete (now empty) directory
        if (!@rmdir($archiveDirectory)) {
            throw new ArchiverException('Could not delete cache directory ' . $archiveDirectory);
        }
    }

    /**
     * @throws UrlGeneratorException
     */
    private function createArchivePath(): string
    {
        $relativeDirectoryPath = '/' . $this->urlGenerator->getRelativePath($this->directory->getPath());

        if (!$this->directoryChecksum) {
            // Calculate checksums
            $this->directoryChecksum = hash('crc32b', $relativeDirectoryPath);

            $contentHash = hash_init('crc32b');
            foreach($this->directory->getFiles() as $file) {
                hash_update($contentHash, $file->name);
                hash_update($contentHash, $file->bytes);
                hash_update($contentHash, $file->modified->getTimestamp());
            }
            $this->contentChecksum = hash_final($contentHash);
        }

        // Ensure that the actual root directory name is not exposed as archive filename
        if (strlen($relativeDirectoryPath) > 1) {
            $basename = basename($relativeDirectoryPath);
        } else {
            $basename = 'root';
        }

        return Path::format(
            $this->cacheDirectory,
            $this->directoryChecksum . $this->contentChecksum,
            $basename . '.zip',
        );
    }

    /**
     * @throws ArchiverException
     */
    private function createCacheDirectory(): string
    {
        $cacheDirectory = Path::format($this->urlGenerator->getBrowserBaseDirectory(), 'cache/');
        if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0775)) {
            throw new ArchiverException('Failed to create cache directory.');
        }

        return $cacheDirectory;
    }
}
