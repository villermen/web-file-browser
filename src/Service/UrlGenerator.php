<?php

namespace Villermen\WebFileBrowser\Service;

use Symfony\Component\HttpFoundation\Request;
use Villermen\DataHandling\Clean;
use Villermen\DataHandling\Path;
use Villermen\WebFileBrowser\Exception\UrlGeneratorException;

/**
 * Allows generation of URLs to browser and data directories.
 */
class UrlGenerator
{
    private readonly string $browserBaseUrl;

    private readonly string $browserBaseDirectory;

    /**
     * @throws UrlGeneratorException
     */
    public function __construct(
        private readonly Configuration $configuration,
        Request $request
    ) {
        // Parse browser base URL and directory
        $this->browserBaseUrl = Clean::url(Path::format('/', $request->getBasePath(), '/'));
        $this->browserBaseDirectory = Path::format($request->server->get('DOCUMENT_ROOT'), $request->getBasePath(), '/');
        if (!is_dir($this->browserBaseDirectory)) {
            throw new UrlGeneratorException(sprintf('Failed to resolve browser base directory "%s".', $this->browserBaseDirectory));
        }
    }

    /**
     * Returns the URL to the data directory root, as set by the "webroot" configuration option.
     */
    public function getBaseUrl(): string
    {
        return $this->configuration->getWebroot();
    }

    /**
     * Returns the path to the data directory root, as set by the "root" configuration option.
     */
    public function getBaseDirectory(): string
    {
        return $this->configuration->getRoot();
    }

    /**
     * Returns the URL to the file browser's public directory.
     */
    public function getBrowserBaseUrl(): string
    {
        return $this->browserBaseUrl;
    }

    /**
     * Returns the path to the file browser's public directory.
     */
    public function getBrowserBaseDirectory(): string
    {
        return $this->browserBaseDirectory;
    }

    /**
     * Returns a data URL for the given absolute path.
     *
     * @throws UrlGeneratorException
     */
    public function getUrl(string $path): string
    {
        return Clean::url(Path::format($this->getBaseUrl(), $this->getRelativePath($path)));
    }

    /**
     * Returns a path relative to the data root based on the given absolute path.
     *
     * @throws UrlGeneratorException
     */
    public function getRelativePath(string $absolutePath): string
    {
        $relativePath = Path::makeRelative($absolutePath, $this->getBaseDirectory());
        if ($relativePath === null) {
            throw new UrlGeneratorException(sprintf('Failed to create relative path for "%s".', $absolutePath));
        }

        return $relativePath;
    }

    /**
     * Returns a file browser URL for the given absolute path.
     *
     * @throws UrlGeneratorException
     */
    public function getBrowserUrl(string $path): string
    {
        $relativePath = Path::makeRelative($path, $this->getBrowserBaseDirectory());
        if ($relativePath === null) {
            throw new UrlGeneratorException(sprintf('Unable to make path "%s" relative.', $path));
        }

        return Clean::url(Path::format($this->getBrowserBaseUrl(), $relativePath));
    }

    /**
     * Returns a file browser URL for the given path to a data directory or file.
     *
     * @throws UrlGeneratorException
     */
    public function getBrowserUrlFromDataPath(string $path): string
    {
        $browserUrl = Clean::url(Path::format($this->getBrowserBaseUrl(), $this->getRelativePath($path)));

        // Trailing slash might have been removed by getRelativePath(). Add back in.
        if (str_ends_with($path, '/') && !str_ends_with($browserUrl, '/')) {
            $browserUrl .= '/';
        }

        return $browserUrl;
    }
}
