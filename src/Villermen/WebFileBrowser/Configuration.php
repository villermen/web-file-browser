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

        // Parse browser base URL
        $this->baseUrl = DataHandling::encodeUri(DataHandling::formatDirectory("/", $request->getBasePath()));

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
        // TODO: Global black- and whitelists?

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

        $allBlacklist = (array)($directoryConfig["blacklist"]["all"] ?? []);

        if (isset($directoryConfig["blacklist"]["webpages"])) {
            $webpageBlacklist = array_merge($allBlacklist, $directoryConfig["blacklist"]["webpages"]);
        } else {
            $webpageBlacklist = $allBlacklist;
        }

        if (isset($directoryConfig["blacklist"]["directories"])) {
            $directoryBlacklist = array_merge($allBlacklist, $directoryConfig["blacklist"]["directories"]);
        } else {
            $directoryBlacklist = $allBlacklist;
        }

        if (isset($directoryConfig["blacklist"]["files"])) {
            $fileBlacklist = array_merge($allBlacklist, $directoryConfig["blacklist"]["files"]);
        } else {
            $fileBlacklist = $allBlacklist;
        }

        $allWhitelist = (array)($directoryConfig["whitelist"]["all"] ?? []);

        if (isset($directoryConfig["whitelist"]["webpages"])) {
            $webpageWhitelist = array_merge($allWhitelist, $directoryConfig["whitelist"]["webpages"]);
        } else {
            $webpageWhitelist = $allWhitelist;
        }

        if (isset($directoryConfig["whitelist"]["directories"])) {
            $directoryWhitelist = array_merge($allWhitelist, $directoryConfig["whitelist"]["directories"]);
        } else {
            $directoryWhitelist = $allWhitelist;
        }

        if (isset($directoryConfig["whitelist"]["files"])) {
            $fileWhitelist = array_merge($allWhitelist, $directoryConfig["whitelist"]["files"]);
        } else {
            $fileWhitelist = $allWhitelist;
        }

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
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
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
     * Returns a file browser url for the given directory.
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
     * Converts a relative path to a URL relative to the browser's base URL.
     *
     * @param string $path
     * @return string
     */
    public function getRelativeUrl(string $path): string
    {
        return DataHandling::encodeUri(DataHandling::formatPath($this->getBaseUrl(), $path));
    }

    /**
     * Converts an absolute file path to a path relative to the browser's base URL.
     *
     * @param string $path
     * @return string
     * @throws DataHandlingException
     */
    public function getRelativePath(string $path): string
    {
        return DataHandling::formatPath("/", DataHandling::makePathRelative($path, $this->getRoot()));
    }


}