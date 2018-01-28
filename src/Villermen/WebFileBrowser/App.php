<?php

namespace Villermen\WebFileBrowser;

use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig_Environment;
use Twig_Loader_Filesystem;
use Villermen\DataHandling\DataHandling;
use Villermen\DataHandling\DataHandlingException;
use Villermen\WebFileBrowser\Service\Archiver;
use Villermen\WebFileBrowser\Service\Configuration;
use Villermen\WebFileBrowser\Service\Directory;
use Villermen\WebFileBrowser\Service\UrlGenerator;

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
        // Change to project root for relative directory resolving
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
     */
    private function handleRequest(Request $request): Response
    {
        try {
            $configuration = new Configuration($this->configFile, $request);
            $urlGenerator = new UrlGenerator($configuration, $request);

            $requestedDirectory = DataHandling::formatDirectory($configuration->getRoot(), $request->getPathInfo());

            $accessible = $configuration->isDirectoryAccessible($requestedDirectory, $reason);

            // Show a page not found page when the directory does not exist or is not accessible
            if (!$accessible) {
                return new Response($this->getTwig($configuration, $urlGenerator)->render("not-found.html.twig"), Response::HTTP_NOT_FOUND);
            }

            $directory = $configuration->getDirectory($requestedDirectory);

            $archiver = new Archiver($configuration, $directory, $urlGenerator);

            if ($request->query->has("prepare-download")) {
                return $this->prepareDownload($archiver, $urlGenerator);
            }

            return $this->showListing($configuration, $urlGenerator, $directory, $archiver);
        } catch (Exception $exception) {
            error_log($exception);
            return new Response("<html><body><h1>Internal Server Error</h1><p>An internal server error occurred.</p></body></html>", Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @param Configuration $configuration
     * @param UrlGenerator $urlGenerator
     * @param Directory $directory
     * @param Archiver $archiver
     * @return Response
     * @throws DataHandlingException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    private function showListing(Configuration $configuration, UrlGenerator $urlGenerator, Directory $directory, Archiver $archiver)
    {
        $path = "/" . rtrim($urlGenerator->getRelativePath($directory->getPath()), "/");

        return new Response($this->getTwig($configuration, $urlGenerator)->render("listing.html.twig", [
            "directory" => $directory,
            "pathParts" => $this->getPathParts($path, $configuration, $urlGenerator),
            "path" => $path,
            "downloadable" => $archiver->canArchive()
        ]));
    }

    /**
     * @param Archiver $archiver
     * @param UrlGenerator $urlGenerator
     * @return JsonResponse
     * @throws DataHandlingException
     * @throws Exception
     */
    private function prepareDownload(Archiver $archiver, UrlGenerator $urlGenerator)
    {
        $archiver->removeObsoleteVersions();

        if (!$archiver->canArchive()) {
            throw new Exception("Unable to archive directory.");
        }

        if (!$archiver->isArchiveReady()) {
            if (!$archiver->isArchiving()) {
                $archiver->createArchive();
            } else {
                $archiver->waitForCreation();
            }
        }

        return new JsonResponse([
            "archiveUrl" => $urlGenerator->getBrowserUrl($archiver->getArchivePath())
        ]);
    }

    private function getTwig(Configuration $configuration, UrlGenerator $urlGenerator): Twig_Environment
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
        $twig->addGlobal("urlGenerator", $urlGenerator);

        $this->twig = $twig;

        return $twig;
    }

    /**
     * Construct path parts for navigation.
     *
     * @param string $path
     * @param Configuration $configuration
     * @param UrlGenerator $urlGenerator
     * @return array
     * @throws DataHandlingException
     */
    private function getPathParts(string $path, Configuration $configuration, UrlGenerator $urlGenerator): array
    {
        // Construct path parts for navigation
        if ($path === "/") {
            $relativePathParts = [""];
        } else {
            $relativePathParts = explode("/", $path);
        }

        $pathParts = [];
        for($i = 0; $i < count($relativePathParts); $i++) {

            $absolutePath = DataHandling::formatDirectory($configuration->getRoot(), ...array_slice($relativePathParts, 0, $i + 1));

            // Add an href only if possible
            $href = "";
            if ($configuration->isDirectoryAccessible($absolutePath)) {
                $href = $urlGenerator->getBrowserUrlFromDataPath($absolutePath);
            }

            $pathParts[] = [
                "name" => $relativePathParts[$i],
                "href" => $href
            ];
        }

        return $pathParts;
    }
}
