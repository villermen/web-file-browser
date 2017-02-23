<?php

namespace Villermen\WebFileBrowser;

use DirectoryIterator;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

class App
{
    /**
     * @var mixed
     */
    protected $config;

    /**
     * @var string
     */
    protected $requestDirectoryAbsolute;

    /**
     * @var string
     */
    protected $requestDirectoryRelative;

    /**
     * @var DirectorySettings
     */
    protected $directorySettings;

    public function __construct(string $configFile)
    {
        // Change to project root for relative directory pointing
        chdir(__DIR__ . "../../../../");

        $this->loadConfig($configFile);
    }

    public function run()
    {
        $request = Request::createFromGlobals();

        $this->resolveRequestedPath($request);

        $directorySettings = $this->getDirectorySettings($this->getRequestDirectoryRelative());
        $items = $this->getItems($directorySettings);

        $view = new View("listing.phtml");
        $response = new Response($view->render([
            "webpages" => $items["webpages"],
            "directories" => $items["directories"],
            "files" => $items["files"],
            "currentDirectory" => $this->requestDirectoryRelative,
            "settings" => $directorySettings
        ]));
        $response->send();
    }

    protected function resolveRequestedPath(Request $request)
    {
        // Parse requested directory
        $this->requestDirectoryRelative = Path::normalizeDirectoryPath(ltrim($request->getPathInfo(), "/"), false);

        // Prevent directory traversal
        if (strpos("/" . $this->requestDirectoryRelative, "/../") !== false) {
            throw new Exception("Directory traversal.");
        }

        // Resolve to absolute directory
        try {
            $this->requestDirectoryAbsolute = Path::normalizeDirectoryPath($this->getRootDirectory() . $this->requestDirectoryRelative);

            if (!is_dir($this->requestDirectoryAbsolute)) {
                throw new Exception("Requested directory is not actually a directory.");
            }
        } catch (Exception $ex) {
            throw new Exception("Requested directory does not exist.", 0, $ex);
        }
    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return string
     */
    protected function getRequestDirectoryRelative(): string
    {
        return $this->requestDirectoryRelative;
    }

    /**
     * @return string
     */
    protected function getRequestDirectoryAbsolute(): string
    {
        return $this->requestDirectoryAbsolute;
    }

    /**
     * Parses the configuration for the specified request path.
     *
     * @param string $requestPath
     * @return DirectorySettings
     */
    protected function getDirectorySettings(string $requestPath) : DirectorySettings
    {
        // Parse config for requested path and its parent directories
        $configDirectoryTree = [ "/" ];
        $requestPathParts = explode("/", $requestPath);
        for ($i = 1; $i < count($requestPathParts); $i++) {
            $configDirectoryTree[] = Path::normalizeDirectoryPath(strtolower(implode("/", array_slice($requestPathParts, 0, $i))), false);
        }

        $configDirectoryTree = array_unique($configDirectoryTree);

        $settings = new DirectorySettings();

        $lastConfigDirectory = end($configDirectoryTree);
        foreach($configDirectoryTree as $configDirectory) {
            if (isset($this->config["directories"][$configDirectory])) {
                $directoryConfig = $this->config["directories"][$configDirectory];

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
                    if ($directoryConfig == $lastConfigDirectory) {
                        if ($directoryConfig["description"]) {
                            $settings->setDescription((string)$directoryConfig["description"]);
                        }
                    }
                }
            }
        }

        return $settings;
    }

    protected function getRootDirectory()
    {
        return Path::normalizeDirectoryPath($this->config["rootdir"]);
    }

    protected function getWebroot()
    {
        return Path::normalizeDirectoryPath($this->config["webroot"], false);
    }

    protected function getItems(DirectorySettings $directorySettings)
    {
        $items = [
            "webpages" => [],
            "directories" => [],
            "files" => []
        ];

        $directoryIterator = new DirectoryIterator($this->getRequestDirectoryAbsolute());

        foreach($directoryIterator as $fileInfo) {
            // Make sure the entry is showable
            if ($fileInfo->isReadable() && $fileInfo->getFilename()[0] != ".") {
                if ($fileInfo->isDir()) {
                    $directoryPath = Path::normalizeDirectoryPath($fileInfo->getPathname(), false);

                    if ($directorySettings->isDisplayDirectories()) {
                        // TODO: Directories need to be accessible
                        $directorySettings = $this->getDirectorySettings($directoryPath);
                        if ($directorySettings->isDisplayWebpages() ||
                            $directorySettings->isDisplayDirectories() ||
                            $directorySettings->isDisplayFiles()) {
                            $items["directories"][] = [
                                "name" => $fileInfo->getFilename(),
                                "path" => $directoryPath
                            ];
                        }
                    }

                    if ($directorySettings->isDisplayWebpages()) {
                        // TODO: Webpages need to have a registered index
                        foreach($this->config["webpageIndexFiles"] as $webpageIndexFile) {
                            if (file_exists($directoryPath . $webpageIndexFile)) {
                                $items["webpages"][] = [
                                    "name" => $fileInfo->getFilename(),
                                    "path" => $directoryPath
                                ];

                                break;
                            }
                        }
                    }
                } elseif ($directorySettings->isDisplayFiles() && $fileInfo->isFile()) {
                    $items["files"][] = [
                        "name" => $fileInfo->getFilename(),
                        "path" => Path::normalizeFilePath($fileInfo->getPathname(), false),
                        "size" => $fileInfo->getSize()
                    ];
                }
            }
        }

        $items["webpages"] = $this->filterNames($items["webpages"], $directorySettings->getWebpageBlacklist(), $directorySettings->getWebpageWhitelist());
        $items["directories"] = $this->filterNames($items["directories"], $directorySettings->getDirectoryBlacklist(), $directorySettings->getDirectoryWhitelist());
        $items["files"] = $this->filterNames($items["files"], $directorySettings->getFileBlacklist(), $directorySettings->getFileWhitelist());

        return $items;
    }

    protected function loadConfig(string $configFile)
    {
        $config = Yaml::parse(file_get_contents($configFile))["config"];

        if (!is_dir($config["rootdir"])) {
            throw new Exception("rootdir config option does not point to a valid directory.");
        }

        // Normalize directories
        $normalizedDirectorySettings = [];
        foreach($config["directories"] as $directory => $directorySettings) {
            $normalizedDirectory = Path::normalizeDirectoryPath(ltrim($directory, "/"), false);
            $normalizedDirectorySettings[$normalizedDirectory] = $directorySettings;
        }
        $config["directories"] = $normalizedDirectorySettings;

        $this->config = $config;
    }

    protected function filterNames(array $items, array $blacklist, array $whitelist) : array
    {
        $blacklistRegexes = array_map("self::listItemToRegex", $blacklist);
        $whitelistRegexes = array_map("self::listItemToRegex", $whitelist);

        if ($whitelist) {
            $items = array_filter($items, function($item) use ($whitelistRegexes) {
                foreach($whitelistRegexes as $whitelistRegex) {
                    if (preg_match($whitelistRegex, $item["name"])) {
                        return true;
                    }
                }

                return false;
            });
        }

        if ($blacklist) {
            $items = array_filter($items, function($item) use ($blacklistRegexes) {
                foreach($blacklistRegexes as $blacklistRegex) {
                    if (preg_match($blacklistRegex, $item["name"])) {
                        return false;
                    }
                }

                return true;
            });
        }

        return $items;
    }

    private static function listItemToRegex($listItem)
    {
        return "/^" . str_replace("*", ".*", $listItem) . "$/i";
    }
}
