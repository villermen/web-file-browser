<?php

namespace Villermen\WebFileBrowser;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig_Environment;
use Twig_Loader_Filesystem;
use Villermen\DataHandling\DataHandling;
use Villermen\DataHandling\DataHandlingException;

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
        $response = $this->handleRequest(Request::createFromGlobals());
        $response->send();
    }

    /**
     * Turns the supplied request into a rendered response.
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    private function handleRequest(Request $request): Response
    {
        $configuration = new Configuration($this->configFile, $request);

        $requestedDirectory = DataHandling::formatDirectory($configuration->getRoot(), $request->getPathInfo());

        $accessible = $configuration->isDirectoryAccessible($requestedDirectory, $reason);

        $twig = $this->getTwig($configuration);

        // Show a page not found page when the directory does not exist or is not accessible
        if (!$accessible) {
            return new Response($twig->render("not-found.html.twig"), Response::HTTP_NOT_FOUND);
        }

        $directory = $configuration->getDirectory($requestedDirectory);

        $path = "/" . rtrim($configuration->getRelativePath($directory->getPath()), "/");

        return new Response($twig->render("listing.html.twig", [
            "directory" => $directory,
            "pathParts" => $this->getPathParts($path, $configuration, $directory),
            "path" => $path
        ]));
    }

    private function getTwig(Configuration $configuration): Twig_Environment
    {
        if ($this->twig) {
            return $this->twig;
        }

        $twigLoader = new Twig_Loader_Filesystem("views/");
        $twig = new Twig_Environment($twigLoader, [
            "autoescape" => "html",
            "strict_variables" => true
        ]);

        $twig->addGlobal("configuration", $configuration);

        $this->twig = $twig;

        return $twig;
    }

    /**
     * @param string $path
     * @param Configuration $configuration
     * @param Directory $directory
     * @return array
     * @throws DataHandlingException
     */
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
