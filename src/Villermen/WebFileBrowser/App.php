<?php

namespace Villermen\WebFileBrowser;

use DirectoryIterator;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;
use Twig_Environment;
use Twig_Loader_Filesystem;

// TODO: Move listing specific code to Listing.php
class App
{
    /**
     * @var mixed
     */
    protected $config;

    /**
     * @var string
     */
    protected $requestDirectory;

    /**
     * @var DirectorySettings
     */
    protected $directorySettings;

    /**
     * @var string
     */
    protected $browserBaseUrl;

    /**
     * @var string
     */
    protected $configFile;

    public function __construct(string $configFile)
    {
        // Change to project root for relative directory pointing
        chdir(__DIR__ . "/../../../");

        $this->configFile = $configFile;
    }

    public function run()
    {
        $request = Request::createFromGlobals();

        $this->config = $this->loadConfig($this->configFile, $request);

        $this->resolvePaths($request);

        $directorySettings = $this->getDirectorySettings($this->requestDirectory);
        $items = $this->getItems($directorySettings);

        $twigLoader = new Twig_Loader_Filesystem("views/");
        $twig = new Twig_Environment($twigLoader, [
            "autoescape" => "html"
        ]);

        $response = new Response($twig->render("listing.html.twig", [
            "webpages" => $items["webpages"],
            "directories" => $items["directories"],
            "files" => $items["files"],
            "currentDirectory" => Path::fixEncoding($this->makeRelative($this->requestDirectory)),
            "settings" => $directorySettings
        ]));
        $response->send();
    }

    protected function resolvePaths(Request $request)
    {
        // Set base path for generating browser URLs
        $this->browserBaseUrl = Path::normalizeDirectory($request->getSchemeAndHttpHost() . $request->getBasePath());

        // Parse requested directory
        $path = urldecode($request->getPathInfo());
        $path = Path::normalizeDirectory($path);
        $path = Path::breakEncoding($path);

        // Prevent directory traversal
        if (strpos("/" . $path, "/../") !== false) {
            throw new Exception("Directory traversal.");
        }

        // Resolve to absolute directory
        try {
            $this->requestDirectory = Path::normalizeDirectory($this->config["rootdir"] . $path, true);

            if (!is_dir($this->requestDirectory)) {
                throw new Exception("Requested directory is not actually a directory.");
            }
        } catch (Exception $ex) {
            throw new Exception("Requested directory (" . $this->requestDirectory . ") does not exist.", 0, $ex);
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
     * Parses the configuration for the specified directory.
     *
     * @param string $path Must be normalized and absolute.
     * @return DirectorySettings
     */
    protected function getDirectorySettings(string $path) : DirectorySettings
    {
        // Parse config for requested path and its parent directories
        $configDirectoryTree = [];
        $requestPathParts = explode("/", $path);
        for ($i = 1; $i < count($requestPathParts); $i++) {
            $configDirectoryTree[] = Path::normalizeDirectory(implode("/", array_slice($requestPathParts, 0, $i)));
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
                    if ($configDirectory == $lastConfigDirectory) {
                        if ($directoryConfig["description"]) {
                            $settings->setDescription((string)$directoryConfig["description"]);
                        }
                    }
                }

                $settings->addParsedConfiguration($configDirectory);
            }
        }

        return $settings;
    }

    protected function getItems(DirectorySettings $directorySettings)
    {
        $items = [
            "webpages" => [],
            "directories" => [],
            "files" => []
        ];

        $directoryIterator = new DirectoryIterator($this->requestDirectory);

        foreach($directoryIterator as $fileInfo) {
            $filename = Path::fixEncoding($fileInfo->getFilename());

            // Make sure the entry is showable
            if ($fileInfo->isReadable() && $filename[0] != ".") {
                if ($fileInfo->isDir()) {
                    $path = Path::normalizeDirectory($fileInfo->getPathname());

                    if ($directorySettings->isDisplayDirectories()) {
                        // Directories need to be accessible by this browser
                        $subdirectorySettings = $this->getDirectorySettings($path);
                        if ($subdirectorySettings->isDisplayWebpages() ||
                            $subdirectorySettings->isDisplayDirectories() ||
                            $subdirectorySettings->isDisplayFiles()) {
                            $items["directories"][] = [
                                "name" => $filename,
                                "url" => $this->browserBaseUrl . $this->makeRelative($path)
                            ];
                        }
                    }

                    if ($directorySettings->isDisplayWebpages()) {
                        // Webpages need to have a registered index
                        foreach($this->config["webpageIndexFiles"] as $webpageIndexFile) {
                            if (file_exists($path . $webpageIndexFile)) {
                                $items["webpages"][] = [
                                    "name" => $filename,
                                    "url" => $this->config["webroot"] . $this->makeRelative($path)
                                ];

                                break;
                            }
                        }
                    }
                } elseif ($directorySettings->isDisplayFiles() && $fileInfo->isFile()) {
                    $path = Path::normalizeFile($fileInfo->getPathname());

                    $items["files"][] = [
                        "name" => $filename,
                        "url" =>  $this->config["webroot"] . $this->makeRelative($path),
                        "size" => self::bytesize($fileInfo->getSize()),
                        "bytes" => $fileInfo->getSize()
                    ];
                }
            }
        }

        $items["webpages"] = $this->filterNames($items["webpages"], $directorySettings->getWebpageBlacklist(), $directorySettings->getWebpageWhitelist());
        $items["directories"] = $this->filterNames($items["directories"], $directorySettings->getDirectoryBlacklist(), $directorySettings->getDirectoryWhitelist());
        $items["files"] = $this->filterNames($items["files"], $directorySettings->getFileBlacklist(), $directorySettings->getFileWhitelist());

        return $items;
    }

    protected function loadConfig(string $configFile, Request $request)
    {
        $config = Yaml::parse(file_get_contents($configFile))["config"];

        // Parse and verify rootdir
        try {
            $config["rootdir"] = Path::normalizeDirectory(Path::breakEncoding($config["rootdir"]), true);
        } catch (Exception $ex) {
            throw new Exception("rootdir config option does not point to a valid directory.", 0, $ex);
        }

        // Parse webroot
        $webroot = $request->getSchemeAndHttpHost() . Path::breakEncoding($config["webroot"]);
        $config["webroot"] = Path::normalizeDirectory($webroot);

        // Normalize directories
        $parsedDirectorySettings = [];
        foreach($config["directories"] as $directory => $directorySettings) {
            $directory = Path::breakEncoding($directory);
            $directory = $config["rootdir"] . ltrim($directory, "/");
            $directory = Path::normalizeDirectory($directory);
            $parsedDirectorySettings[$directory] = $directorySettings;
        }
        $config["directories"] = $parsedDirectorySettings;

        return $config;
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

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private static function listItemToRegex($listItem)
    {
        return "/^" . str_replace("*", ".*", $listItem) . "$/i";
    }

    private static function bytesize(int $size)
    {
        $suffixes = [
            "B", "KiB", "MiB", "GiB" // "TiB", "PiB", "EiB", "ZiB", "YiB"
        ];

        $level = 1;
        for ($exponent = 0; $exponent < count($suffixes); $exponent++) {
            $nextLevel = pow(1024, $exponent + 1);
            if ($nextLevel > $size) {
                $smallSize = $size / $level;

                if ($smallSize < 10) {
                    $decimals = 2;
                } elseif ($smallSize < 100) {
                    $decimals = 1;
                } else {
                    $decimals = 0;
                }

                return round($smallSize, $decimals) . " " . $suffixes[$exponent];
            }

            $level = $nextLevel;
        }

        return "Large.";
    }

    private function makeRelative(string $absoluteDirectory)
    {
        if (substr($absoluteDirectory, 0, strlen($this->config["rootdir"])) != $this->config["rootdir"]) {
            throw new Exception("Directory to make relative ({$absoluteDirectory}) is not a child of root directory.");
        }

        return substr($absoluteDirectory, strlen($this->config["rootdir"]));
    }
}
