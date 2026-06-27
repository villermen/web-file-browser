<?php

namespace Villermen\WebFileBrowser;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Villermen\DataHandling\DataHandling;
use Villermen\DataHandling\DataHandlingException;
use Villermen\WebFileBrowser\Exception\ArchiverException;
use Villermen\WebFileBrowser\Exception\ConfigurationException;
use Villermen\WebFileBrowser\Service\Archiver;
use Villermen\WebFileBrowser\Service\Configuration;
use Villermen\WebFileBrowser\Service\Directory;
use Villermen\WebFileBrowser\Service\UrlGenerator;

class App
{
    private string $configFile;

    private ?Environment $twig = null;

    public function __construct(string $configFile)
    {
        // Change to project root for relative directory resolving
        chdir(__DIR__ . '/../');

        $this->configFile = $configFile;
    }

    public function run(): void
    {
        $response = $this->handleRequest(Request::createFromGlobals());
        $response->send();
    }

    /**
     * Turns the supplied request into a rendered response.
     */
    private function handleRequest(Request $request): Response
    {
        try {
            $configuration = new Configuration($this->configFile);
            $urlGenerator = new UrlGenerator($configuration, $request);

            $requestedDirectory = DataHandling::formatDirectory($configuration->getRoot(), $request->getPathInfo());

            $accessible = $configuration->isDirectoryAccessible($requestedDirectory, $reason);

            // Show a page not found page when the directory does not exist or is not accessible
            if (!$accessible) {
                return new Response($this->getTwig($configuration, $urlGenerator)->render('not-found.html.twig'), Response::HTTP_NOT_FOUND);
            }

            $directory = $configuration->getDirectory($requestedDirectory);

            if ($directory->canArchive()) {
                $archiver = new Archiver($configuration, $directory, $urlGenerator);

                if ($request->query->has('prepare-download')) {
                    return $this->prepareDownload($archiver, $urlGenerator);
                } else {
                    // Delete expired archives only when we are not preparing one, so that we are not deleting an archive that is to be used just moments after
                    $archiver->deleteExpiredArchives();
                }
            }

            return $this->showListing($configuration, $urlGenerator, $directory);
        } catch (\Throwable $exception) {
            error_log($exception);

            if (php_sapi_name() === 'cli-server' && function_exists('dump')) {
                dump($exception);
            }

            return new Response('<html lang="en"><body><h1>Internal Server Error</h1><p>An internal server error occurred.</p></body></html>', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @throws DataHandlingException
     * @throws \Twig\Error
     */
    private function showListing(Configuration $configuration, UrlGenerator $urlGenerator, Directory $directory): Response
    {
        $path = '/' . rtrim($urlGenerator->getRelativePath($directory->getPath()), '/');

        return new Response($this->getTwig($configuration, $urlGenerator)->render('listing.html.twig', [
            'directory' => $directory,
            'pathParts' => $this->getPathParts($path, $configuration, $urlGenerator),
            'path' => $path,
            'downloadable' => $directory->canArchive(),
        ]));
    }

    /**
     * @throws ArchiverException
     * @throws DataHandlingException
     */
    private function prepareDownload(Archiver $archiver, UrlGenerator $urlGenerator): JsonResponse
    {
        $archiver->deleteObsoleteVersions();

        if (!$archiver->isArchiveReady()) {
            if (!$archiver->isArchiving()) {
                $archiver->createArchive();
            } else {
                $archiver->waitForCreation();
            }
        }

        return new JsonResponse([
            'archiveUrl' => $urlGenerator->getBrowserUrl($archiver->getArchivePath())
        ]);
    }

    private function getTwig(Configuration $configuration, UrlGenerator $urlGenerator): Environment
    {
        if ($this->twig) {
            return $this->twig;
        }

        $twig = new Environment(New FilesystemLoader('views/'), [
            'autoescape' => 'html',
            'strict_variables' => true
        ]);

        $twig->addGlobal('configuration', $configuration);
        $twig->addGlobal('urlGenerator', $urlGenerator);

        $this->twig = $twig;

        return $twig;
    }

    /**
     * Construct path parts for navigation.
     *
     * @return array<array{name: string, href: string}>
     * @throws DataHandlingException
     */
    private function getPathParts(string $path, Configuration $configuration, UrlGenerator $urlGenerator): array
    {
        // Construct path parts for navigation
        if ($path === '/') {
            $relativePathParts = [''];
        } else {
            $relativePathParts = explode('/', $path);
        }

        $pathParts = [];
        for($i = 0; $i < count($relativePathParts); $i++) {

            $absolutePath = DataHandling::formatDirectory($configuration->getRoot(), ...array_slice($relativePathParts, 0, $i + 1));

            // Add an href only if possible
            $href = '';
            if ($configuration->isDirectoryAccessible($absolutePath)) {
                $href = $urlGenerator->getBrowserUrlFromDataPath($absolutePath);
            }

            $pathParts[] = [
                'name' => $relativePathParts[$i],
                'href' => $href
            ];
        }

        return $pathParts;
    }
}
