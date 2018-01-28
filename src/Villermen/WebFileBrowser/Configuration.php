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

    /** @var string */
    protected $browserBaseUrl;

    /** @var string */
    protected $browserBaseDirectory;

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
        $this->browserBaseUrl = DataHandling::encodeUri(DataHandling::formatDirectory("/", $request->getBasePath()));
        $this->browserBaseDirectory = DataHandling::formatAndResolveDirectory($request->server->get("DOCUMENT_ROOT"), $request->getBasePath());

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
        $relativeDirectoryParts = explode("/", DataHandling::makePathRelative($directory, $this->getBaseDirectory()));
        for ($i = count($relativeDirectoryParts) - 1; $i >= 0; $i--) {
            $configDirectory = DataHandling::formatDirectory($this->getBaseDirectory(), implode("/", array_slice($relativeDirectoryParts, 0, $i)));
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

        $archivable = (bool)($directoryConfig["archivable"] ?? $this->resolvedConfiguration["archivable"] ?? false);

        $directory = new Directory(
            $directory, $this, $displayWebpages, $displayDirectories, $displayFiles, $webpageBlacklist,
            $directoryBlacklist, $fileBlacklist, $webpageWhitelist, $directoryWhitelist, $fileWhitelist, $description,
            $archivable
        );

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
     * Returns the URL to the data directory root, as set by the "webroot" configuration option.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->resolvedConfiguration["webroot"];
    }

    /**
     * Returns the path to the data directory root, as set by the "root" configuration option.
     *
     * @return string
     */
    public function getBaseDirectory(): string
    {
        return $this->resolvedConfiguration["root"];
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

    /**
     * @return string[]
     */
    public function getIndexFiles(): array
    {
        return (array)($this->resolvedConfiguration["indexFiles"] ?? []);
    }

    /**
     * Returns the name of the theme to use for styling.
     *
     * @return string
     */
    public function getTheme(): string
    {
        return (string)($this->resolvedConfiguration["theme"] ?? "dark");
    }

    /**
     * Title of the application to be used as suffix for the page name.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return (string)($this->resolvedConfiguration["title"] ?? "Viller's web file browser");
    }
}