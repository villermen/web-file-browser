<?php

namespace Villermen\WebFileBrowser;

use DirectoryIterator;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig_Environment;
use Twig_Loader_Filesystem;
use Villermen\DataHandling\DataHandling;

// TODO: Move listing specific code to Listing.php
class App
{
    /**
     * @var Configuration
     */
    protected $configuration;

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

        $this->configuration = new Configuration($this->configFile, $request);

        $this->resolvePaths($request);

        $directorySettings = $this->configuration->getDirectorySettings($this->requestDirectory);
        $items = $this->getItems($directorySettings);

        $twigLoader = new Twig_Loader_Filesystem("views/");
        $twig = new Twig_Environment($twigLoader, [
            "autoescape" => "html"
        ]);

        $response = new Response($twig->render("listing.html.twig", [
            "webpages" => $items["webpages"],
            "directories" => $items["directories"],
            "files" => $items["files"],
            "currentDirectory" => "/" . $this->makeRelative($this->requestDirectory),
            "settings" => $directorySettings,
            "browserBaseUrl" => $this->browserBaseUrl
        ]));
        $response->send();
    }

    protected function resolvePaths(Request $request)
    {
        // Set base path for generating browser URLs
        $this->browserBaseUrl = DataHandling::formatDirectory($request->getSchemeAndHttpHost(), $request->getBasePath());

        // Parse requested directory
        $path = urldecode($request->getPathInfo());
        $path = DataHandling::formatDirectory($path);
        // $path = Path::breakEncoding($path);

        // Prevent directory traversal
        if (strpos("/" . $path, "/../") !== false) {
            throw new Exception("Directory traversal.");
        }

        // Resolve to absolute directory
        try {
            $this->requestDirectory = DataHandling::formatAndResolveDirectory($this->configuration->getRoot(), $path);

            if (!is_dir($this->requestDirectory)) {
                throw new Exception("Requested directory is not actually a directory.");
            }
        } catch (Exception $ex) {
            throw new Exception("Requested directory (" . $this->requestDirectory . ") does not exist.", 0, $ex);
        }
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
            $filename = $fileInfo->getFilename();

            // Make sure the entry is showable
            if ($fileInfo->isReadable() && $filename[0] != ".") {
                if ($fileInfo->isDir()) {
                    $path = DataHandling::formatDirectory($fileInfo->getPathname());

                    if ($directorySettings->isDisplayDirectories()) {
                        // Directories need to be accessible by this browser
                        $subdirectorySettings = $this->configuration->getDirectorySettings($path);
                        if ($subdirectorySettings->isDisplayWebpages() ||
                            $subdirectorySettings->isDisplayDirectories() ||
                            $subdirectorySettings->isDisplayFiles()) {
                            $items["directories"][] = [
                                "name" => $filename,
                                "url" => Path::encodeUrl($this->browserBaseUrl . $this->makeRelative($path))
                            ];
                        }
                    }

                    if ($directorySettings->isDisplayWebpages()) {
                        // Webpages need to have a registered index
                        foreach($this->configuration["webpageIndexFiles"] as $webpageIndexFile) {
                            if (file_exists($path . $webpageIndexFile)) {
                                $items["webpages"][] = [
                                    "name" => $filename,
                                    "url" => Path::encodeUrl($this->configuration["webroot"] . $this->makeRelative($path))
                                ];

                                break;
                            }
                        }
                    }
                } elseif ($directorySettings->isDisplayFiles() && $fileInfo->isFile()) {
                    $path = DataHandling::formatPath($fileInfo->getPathname());

                    $items["files"][] = [
                        "name" => $filename,
                        "url" =>  Path::encodeUrl($this->configuration["webroot"] . $this->makeRelative($path)),
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
        if (substr($absoluteDirectory, 0, strlen($this->configuration->getRoot())) != $this->configuration->getRoot()) {
            throw new Exception("Directory to make relative ({$absoluteDirectory}) is not a child of root directory.");
        }

        return substr($absoluteDirectory, strlen($this->configuration->getRoot()));
    }
}
