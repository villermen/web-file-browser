<?php

namespace Villermen\WebFileBrowser;


class View
{
    private static $viewRootDirectory = "views/";

    /**
     * @var string
     */
    private $viewFile;

    public function __construct(string $viewFile)
    {
        $this->viewFile = Path::normalizeFilePath(self::$viewRootDirectory . $viewFile);
    }

    function render(array $arguments = [])
    {
        extract($arguments, EXTR_SKIP);

        ob_start();
        require($this->viewFile);
        return ob_get_clean();
    }
}