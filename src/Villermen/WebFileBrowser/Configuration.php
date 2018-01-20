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
     * @var DirectorySettings[]
     */
    protected $cachedDirectorySettings = [];

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
        $this->resolvedConfiguration["webroot"] = DataHandling::formatDirectory($request->getSchemeAndHttpHost(), $this->resolvedConfiguration["webroot"]);

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
     * Will resolve with recursive settings from parent directories.
     *
     * @param string $directory Must be formatted and absolute.
     * @return DirectorySettings
     * @throws Exception Thrown when the directory is inaccessible or its configuration could not be parsed.
     */
    public function getDirectorySettings(string $directory): DirectorySettings
    {
        $directory = DataHandling::formatAndResolveDirectory($directory);

        if (isset($this->cachedDirectorySettings[$directory])) {
            return $this->cachedDirectorySettings[$directory];
        }

        // Parse config for requested path and its parent directories
        $configDirectoryTree = [];
        $requestPathParts = explode("/", $directory);
        for ($i = 1; $i < count($requestPathParts); $i++) {
            $configDirectoryTree[] = DataHandling::formatDirectory(implode("/", array_slice($requestPathParts, 0, $i)));
        }

        if (!in_array($this->getRoot(), $configDirectoryTree)) {
            throw new Exception("Directory is not a child of the root directory.");
        }

        $settings = new DirectorySettings();

        // TODO: Verify that this correctly recurses and removes the recursive settings when recursive is set to false
        // TODO: Throw exception if nothing can be shown

        $lastConfigDirectory = end($configDirectoryTree);
        foreach($configDirectoryTree as $configDirectory) {
            if (isset($this->configuration["directories"][$configDirectory])) {
                $directoryConfig = $this->configuration["directories"][$configDirectory];

                $recursive = $directoryConfig["recursive"] ?? false;

                // Settings for recursive parents, or the requested directory
                if ($recursive || $configDirectory == $lastConfigDirectory) {
                    if (isset($directoryConfig["display"]["webpages"])) {
                        $settings->setDisplayWebpages((bool)$directoryConfig["display"]["webpages"]);
                    }

                    if (isset($directoryConfig["display"]["directories"])) {
                        $settings->setDisplayDirectories((bool)$directoryConfig["display"]["directories"]);
                    }

                    if (isset($directoryConfig["display"]["files"])) {
                        $settings->setDisplayFiles((bool)$directoryConfig["display"]["files"]);
                    }

                    if (isset($directoryConfig["blacklist"]))
                    {
                        if (isset($directoryConfig["blacklist"]["webpages"])) {
                            $settings->addWebpageBlacklist((array)$directoryConfig["blacklist"]["webpages"]);
                        }

                        if (isset($directoryConfig["blacklist"]["directories"])) {
                            $settings->addDirectoryBlacklist((array)$directoryConfig["blacklist"]["directories"]);
                        }

                        if (isset($directoryConfig["blacklist"]["files"])) {
                            $settings->addFileBlacklist((array)$directoryConfig["blacklist"]["files"]);
                        }

                        if (isset($directoryConfig["blacklist"]["all"])) {
                            $allBlacklist = (array)$directoryConfig["blacklist"]["all"];
                            $settings->addWebpageBlacklist($allBlacklist);
                            $settings->addDirectoryBlacklist($allBlacklist);
                            $settings->addFileBlacklist($allBlacklist);
                        }
                    }

                    if (isset($directoryConfig["whitelist"]))
                    {
                        if (isset($directoryConfig["whitelist"]["webpages"])) {
                            $settings->addWebpageWhitelist((array)$directoryConfig["whitelist"]["webpages"]);
                        }

                        if (isset($directoryConfig["whitelist"]["directories"])) {
                            $settings->addDirectoryWhitelist((array)$directoryConfig["whitelist"]["directories"]);
                        }

                        if (isset($directoryConfig["whitelist"]["files"])) {
                            $settings->addFileWhitelist((array)$directoryConfig["whitelist"]["files"]);
                        }

                        if (isset($directoryConfig["whitelist"]["all"])) {
                            $allWhitelist = (array)$directoryConfig["whitelist"]["all"];
                            $settings->addWebpageWhitelist($allWhitelist);
                            $settings->addDirectoryWhitelist($allWhitelist);
                            $settings->addFileWhitelist($allWhitelist);
                        }
                    }

                    // Description is only ever applicable to the requested directory
                    if ($configDirectory == $lastConfigDirectory) {
                        if (isset($directoryConfig["description"])) {
                            $settings->setDescription((string)$directoryConfig["description"]);
                        }
                    }
                }

                $settings->addParsedConfiguration($configDirectory);
            }
        }

        $this->cachedDirectorySettings[$directory] = $settings;

        return $settings;
    }

    public function isDirectoryAccessible(string $directory): bool
    {
        // TODO: Use
        try {
            if ($this->getDirectorySettings($directory)) {
                return true;
            }
        } catch (Exception $exception) {
        }

        return false;
    }

    public function getRoot(): string
    {
        return $this->resolvedConfiguration["root"];
    }

    public function getWebroot(): string
    {
        return $this->resolvedConfiguration["webroot"];
    }
}