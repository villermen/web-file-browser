<?php

namespace Villermen\WebFileBrowser;

use Composer\Script\Event;

class ComposerScripts
{
    public static function postCreateProject(Event $event)
    {
        self::copyDefaultConfiguration($event);
    }

    public static function postInstall(Event $event)
    {
        self::copyDefaultConfiguration($event);
    }

    /**
     * Copies config.dist.yml to config.yml if the latter doesn't exist yet.
     */
    private static function copyDefaultConfiguration(Event $event)
    {
        $cwd = getcwd();

        if (!file_exists($cwd . "/config/config.yml")) {
            if (copy($cwd . "/config/config.dist.yml", $cwd . "/config/config.yml")) {
                $event->getIO()->write("Created default config.yml.");
            } else {
                $event->getIO()->writeError("Could not copy default config.yml!");
            }
        }
    }
}