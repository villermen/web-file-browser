<?php

namespace Villermen\WebFileBrowser;

class Path
{
    /**
     * Paths in PHP on Windows apparently use Windows-1252 encoding...
     *
     * @param string $path
     * @return string
     */
    public static function fixEncoding(string $path) : string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) == "WIN") {
            return mb_convert_encoding($path, "UTF-8", "Windows-1252");
        }

        return $path;
    }

    /**
     * The inverse of the fixEncoding function.
     *
     * @param string $path
     * @return string
     */
    public static function breakEncoding(string $path) : string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) == "WIN") {
            return mb_convert_encoding($path, "Windows-1252", "UTF-8");
        }

        return $path;
    }

    public static function encodeUrl(string $url) : string
    {
        $url = rawurlencode($url);
        return str_replace([ "%3A%2F%2F", "%2F" ], [ "://", "/" ], $url);
    }
}