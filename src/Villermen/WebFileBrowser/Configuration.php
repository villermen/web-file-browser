<?php

namespace Villermen\WebFileBrowser;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;
use Villermen\DataHandling\DataHandling;
use Villermen\DataHandling\DataHandlingException;

class Configuration
{
    /**
     * @var mixed[] An array containing the loaded configuration with resolved absolute paths.
     */
    protected $resolvedConfiguration;

    /**
     * @var Directory[]
     */
    protected $cachedDirectories = [];

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var string
     */
    protected $baseDirectory;

    /**
     * Configuration constructor.
     * @param string $filePath
     * @param Request $request
     * @throws Exception
     */
    public function __construct(string $filePath, Request $request)
    {
        $this->resolvedConfiguration = Yaml::parse(file_get_contents($filePath))["config"];

        // Parse and verify root directory
        try {
            $this->resolvedConfiguration["root"] = DataHandling::formatAndResolveDirectory($this->resolvedConfiguration["root"]);
        } catch (DataHandlingException $ex) {
            throw new Exception("root config option does not point to a valid directory.", 0, $ex);
        }

        // Parse webroot
        $this->resolvedConfiguration["webroot"] = DataHandling::encodeUri(DataHandling::formatDirectory($this->resolvedConfiguration["webroot"]));

        // Parse browser base URL and directory
        $this->baseUrl = DataHandling::encodeUri(DataHandling::formatDirectory("/", $request->getBasePath()));
        $this->baseDirectory = DataHandling::formatAndResolveDirectory($request->server->get("DOCUMENT_ROOT"));

        // Normalize directories
        $parsedDirectorySettings = [];
        foreach($this->resolvedConfiguration["directories"] as $directory => $directorySettings) {
            $directory = DataHandling::formatDirectory($this->resolvedConfiguration["root"], $directory);
            $parsedDirectorySettings[$directory] = $directorySettings;
        }
        $this->resolvedConfiguration["directories"] = $parsedDirectorySettings;
    }

    /**
     * Parses the configuration for the specified directory.
     * Recursive settings will be applied when the closest configured directory has recursive set to true.
     *
     * @param string $directory Must be formatted and absolute.
     * @return Directory
     * @throws Exception Thrown when the directory is inaccessible or its configuration could not be parsed.
     */
    public function getDirectory(string $directory): Directory
    {
        $directory = DataHandling::formatAndResolveDirectory($directory);

        if (isset($this->cachedDirectories[$directory])) {
            return $this->cachedDirectories[$directory];
        }

        // Parse configuration to find this or the first recursive parent directory
        $directoryConfig = false;
        $configDirectory = "";
        $relativeDirectoryParts = explode("/", DataHandling::makePathRelative($directory, $this->getRoot()));
        for ($i = count($relativeDirectoryParts) - 1; $i >= 0; $i--) {
            $configDirectory = DataHandling::formatDirectory($this->getRoot(), implode("/", array_slice($relativeDirectoryParts, 0, $i)));
            $directoryConfig = $this->resolvedConfiguration["directories"][$configDirectory] ?? false;

            if ($directoryConfig) {
                break;
            }
        }

        if (!$directoryConfig) {
            throw new Exception("No configuration could be obtained for the requested directory.");
        }

        $recursive = $directoryConfig["recursive"] ?? false;
        $exactMatch = $configDirectory === $directory;

        if (!$exactMatch && !$recursive) {
            throw new Exception("First parent configuration entry encountered is not recursive.");
        }

        // Parse configuration
        $displayWebpages = (bool)($directoryConfig["display"]["webpages"] ?? false);
        $displayDirectories = (bool)($directoryConfig["display"]["directories"] ?? false);
        $displayFiles = (bool)($directoryConfig["display"]["files"] ?? false);

        if (!$displayWebpages && !$displayDirectories && !$displayFiles) {
            throw new Exception("Directory is not able to display anything.");
        }

        // Merge global and directory blacklists and whitelists
        $allBlacklist = array_merge(
            $this->resolvedConfiguration["blacklist"]["all"] ?? [],
            $directoryConfig["blacklist"]["all"] ?? []
        );

        $webpageBlacklist = array_merge(
            $allBlacklist,
            $this->resolvedConfiguration["blacklist"]["webpages"] ?? [],
            $directoryConfig["blacklist"]["webpages"] ?? []
        );

        $directoryBlacklist = array_merge(
            $allBlacklist,
            $this->resolvedConfiguration["blacklist"]["directories"] ?? [],
            $directoryConfig["blacklist"]["directories"] ?? []
        );

        $fileBlacklist = array_merge(
            $allBlacklist,
            $this->resolvedConfiguration["blacklist"]["files"] ?? [],
            $directoryConfig["blacklist"]["files"] ?? []
        );

        $allWhitelist = array_merge(
            $this->resolvedConfiguration["whitelist"]["all"] ?? [],
            $directoryConfig["whitelist"]["all"] ?? []
        );

        $webpageWhitelist = array_merge(
            $allWhitelist,
            $this->resolvedConfiguration["whitelist"]["webpages"] ?? [],
            $directoryConfig["whitelist"]["webpages"] ?? []
        );

        $directoryWhitelist = array_merge(
            $allWhitelist,
            $this->resolvedConfiguration["whitelist"]["directories"] ?? [],
            $directoryConfig["whitelist"]["directories"] ?? []
        );

        $fileWhitelist = array_merge(
            $allWhitelist,
            $this->resolvedConfiguration["whitelist"]["files"] ?? [],
            $directoryConfig["whitelist"]["files"] ?? []
        );

        $description = $exactMatch ? (string)($directoryConfig["description"] ?? "") : "";

        $directory = new Directory($directory, $this, $displayWebpages, $displayDirectories, $displayFiles, $webpageBlacklist,
            $directoryBlacklist, $fileBlacklist, $webpageWhitelist, $directoryWhitelist, $fileWhitelist, $description);

        return $directory;
    }

    /**
     * @param string $directory
     * @param string $reason Set to the reason for not being accessible if that is the case.
     * @return bool
     */
    public function isDirectoryAccessible(string $directory, &$reason = ""): bool
    {
        try {
            if ($this->getDirectory($directory)) {
                return true;
            }
        } catch (Exception $exception) {
            $reason = $exception->getMessage();
        }

        return false;
    }

    /**
     * @return string
     */
    public function getRoot(): string
    {
        return $this->resolvedConfiguration["root"];
    }

    /**
     * @return string
     */
    public function getWebroot(): string
    {
        return $this->resolvedConfiguration["webroot"];
    }

    /**
     * Returns the URL to the browser's public directory.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Returns the path to the browser's public directory.
     *
     * @return string
     */
    public function getBaseDirectory()
    {
        return $this->baseDirectory;
    }

    /**
     * @return string[]
     */
    public function getIndexFiles(): array
    {
        return (array)($this->resolvedConfiguration["indexFiles"] ?? []);
    }

    /**
     * Returns an URL to the given absolute path.
     *
     * @param string $path
     * @return string
     * @throws DataHandlingException
     */
    public function getUrl(string $path): string
    {
        return DataHandling::encodeUri(DataHandling::formatPath($this->getWebroot(), DataHandling::makePathRelative($path, $this->getRoot())));
    }

    /**
     * Returns a file browser url for the given absolute directory.
     *
     * @param string $directory
     * @return string
     * @throws DataHandlingException
     */
    public function getBrowserUrl(string $directory): string
    {
        return DataHandling::encodeUri(DataHandling::formatDirectory($this->getBaseUrl(), DataHandling::makePathRelative($directory, $this->getRoot())));
    }

    /**
     * Converts a relative path to a URL relative to the file browser's base URL.
     *
     * @param string $path
     * @return string
     */
    public function getRelativeUrl(string $path): string
    {
        return DataHandling::encodeUri(DataHandling::formatPath($this->getBaseUrl(), $path));
    }

    /**
     * Converts an absolute path to a path relative to the file browser's base URL.
     *
     * @param string $path
     * @return string
     * @throws DataHandlingException
     */
    public function getRelativeBrowserPath(string $path): string
    {
        return DataHandling::formatPath(DataHandling::makePathRelative($path, $this->getRoot()));
    }

    /**
     * Converts a path relative to the file browser's base URL to an absolute path.
     *
     * @param string $relativePath
     * @return string
     */
    public function getAbsoluteBrowserPath(string $relativePath): string
    {
        return DataHandling::formatPath($this->getBaseDirectory(), $relativePath);
    }

    /**
     * Returns the name of the theme to use for styling.
     *
     * @return string
     */
    public function getTheme(): string
    {
        return $this->resolvedConfiguration["theme"] ?? "dark";
    }

    public function getTitle(): string
    {
        return $this->resolvedConfiguration["title"] ?? "Viller's web file browser";
    }
}