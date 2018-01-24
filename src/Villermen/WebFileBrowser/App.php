<?php

namespace Villermen\WebFileBrowser;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig_Environment;
use Twig_Loader_Filesystem;
use Villermen\DataHandling\DataHandling;

class App
{
    /**
     * @var string
     */
    protected $requestDirectory;

    /**
     * @var Directory
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

    /**
     * @var Twig_Environment
     */
    private $twig;

    public function __construct(string $configFile)
    {
        // Change to project root for relative directory pointing
        chdir(__DIR__ . "/../../../");

        $this->configFile = $configFile;
    }

    public function run()
    {
        $request = Request::createFromGlobals();

        $configuration = new Configuration($this->configFile, $request);

        $requestedDirectory = DataHandling::formatDirectory($configuration->getRoot(), $request->getPathInfo());

        $accessible = $configuration->isDirectoryAccessible($requestedDirectory, $reason);

        if ($accessible) {
            $directory = $configuration->getDirectory($requestedDirectory);

            $path = "/" . rtrim($configuration->getRelativePath($directory->getPath()), "/");

            return (new Response($this->getTwig()->render("listing.html.twig", [
                "configuration" => $configuration,
                "directory" => $directory,
                "pathParts" => $this->getPathParts($path, $configuration, $directory),
                "path" => $path
            ])))->send();
        }

        return (new Response("404 Not Found", 404))->send();
    }

    private function getTwig(): Twig_Environment
    {
        if ($this->twig) {
            return $this->twig;
        }

        $twigLoader = new Twig_Loader_Filesystem("views/");
        $this->twig = new Twig_Environment($twigLoader, [
            "autoescape" => "html",
            "strict_variables" => true
        ]);

        return $this->twig;
    }

    private function getPathParts(string $path, Configuration $configuration, Directory $directory): array
    {
        // Construct path parts for navigation
        $relativePathParts = explode("/", $path);

        $pathParts = [];
        for($i = 0; $i < count($relativePathParts); $i++) {
            $absolutePath = DataHandling::formatDirectory($configuration->getRoot(), ...array_slice($relativePathParts, 0, $i + 1));

            // Add an href only if possible
            $href = "";
            if ($configuration->isDirectoryAccessible($absolutePath)) {
                $href = $configuration->getBrowserUrl($absolutePath);
            }

            $pathParts[] = [
                "name" => $relativePathParts[$i],
                "href" => $href
            ];
        }

        return $pathParts;
    }
}
