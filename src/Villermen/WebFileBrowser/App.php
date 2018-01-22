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

            $twigLoader = new Twig_Loader_Filesystem("views/");
            $twig = new Twig_Environment($twigLoader, [
                "autoescape" => "html",
                "strict_variables" => true
            ]);

            return (new Response($twig->render("listing.html.twig", [
                "configuration" => $configuration,
                "directory" => $directory
            ])))->send();
        }

        return (new Response("404 Not Found", 404))->send();
    }
}
