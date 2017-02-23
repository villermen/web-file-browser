<?php

namespace Villermen\WebFileBrowser;

use Exception;

class Path
{
    /**
     * @param string $path
     * @param bool $resolve
     * @return string
     *
     * @throws Exception
     */
    public static function normalizeFilePath(string $path, bool $resolve = true): string
    {
        if ($resolve) {
            $path = realpath($path);

            if (!$path) {
                throw new Exception("Given path does not exist.");
            }
        }

        $path = str_replace("\\", "/", $path);

        $replacements = 0;
        do {
            $path = str_replace(["/./", "//"], "/", $path, $replacements);
        } while ($replacements > 0);

        return $path;
    }

    /**
     * @param string $path
     * @param bool $resolve
     * @return string
     */
    public static function normalizeDirectoryPath(string $path, $resolve = true)
    {
        return rtrim(self::normalizeFilePath($path, $resolve), "/") . "/";
    }
}