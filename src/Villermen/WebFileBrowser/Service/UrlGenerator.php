<?php

namespace Villermen\WebFileBrowser\Service;

use Symfony\Component\HttpFoundation\Request;
use Villermen\DataHandling\DataHandling;
use Villermen\DataHandling\DataHandlingException;

/**
 * Allows generation of URLs to browser and data directories.
 */
class UrlGenerator
{
    /** @var string */
    private $browserBaseUrl;

    /** @var string */
    private $browserBaseDirectory;

    /** @var Configuration */
    protected $configuration;

    /**
     * @param Configuration $configuration
     * @param Request $request
     * @throws DataHandlingException
     */
    public function __construct(Configuration $configuration, Request $request)
    {
        $this->configuration = $configuration;

        // Parse browser base URL and directory
        $this->browserBaseUrl = DataHandling::encodeUri(DataHandling::formatDirectory("/", $request->getBasePath()));
        $this->browserBaseDirectory = DataHandling::formatAndResolveDirectory($request->server->get("DOCUMENT_ROOT"), $request->getBasePath());
    }

    /**
     * Returns the URL to the data directory root, as set by the "webroot" configuration option.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->configuration->getWebroot();
    }

    /**
     * Returns the path to the data directory root, as set by the "root" configuration option.
     *
     * @return string
     */
    public function getBaseDirectory(): string
    {
        return $this->configuration->getRoot();
    }

    /**
     * Returns the URL to the file browser's public directory.
     *
     * @return string
     */
    public function getBrowserBaseUrl(): string
    {
        return $this->browserBaseUrl;
    }

    /**
     * Returns the path to the file browser's public directory.
     *
     * @return string
     */
    public function getBrowserBaseDirectory()
    {
        return $this->browserBaseDirectory;
    }

    /**
     * Returns a data URL for the given absolute path.
     *
     * @param string $path
     * @return string
     * @throws DataHandlingException
     */
    public function getUrl(string $path): string
    {
        return DataHandling::encodeUri(DataHandling::formatPath($this->getBaseUrl(), DataHandling::makePathRelative($path, $this->getBaseDirectory())));
    }

    /**
     * Returns a path relative to the data root based on the given absolute path.
     *
     * @param string $absolutePath
     * @return string
     * @throws DataHandlingException
     */
    public function getRelativePath(string $absolutePath): string
    {
        return DataHandling::makePathRelative($absolutePath, $this->getBaseDirectory());
    }

    /**
     * Returns a file browser URL for the given absolute path.
     *
     * @param string $path
     * @return string
     * @throws DataHandlingException
     */
    public function getBrowserUrl(string $path): string
    {
        return DataHandling::encodeUri(DataHandling::formatPath($this->getBrowserBaseUrl(), DataHandling::makePathRelative($path, $this->getBrowserBaseDirectory())));
    }

    /**
     * Returns a file browser URL for the given path to a data directory or file.
     *
     * @param string $path
     * @return string
     * @throws DataHandlingException
     */
    public function getBrowserUrlFromDataPath(string $path): string
    {
        $browserUrl = DataHandling::encodeUri(DataHandling::formatPath($this->getBrowserBaseUrl(), $this->getRelativePath($path)));

        // Trailing slash might have been removed by getRelativePath(). Add back in.
        if (DataHandling::endsWith($path, "/") && !DataHandling::endsWith($browserUrl, "/")) {
            $browserUrl .= "/";
        }

        return $browserUrl;
    }
}
