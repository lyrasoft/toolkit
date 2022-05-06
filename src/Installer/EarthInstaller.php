<?php

/**
 * Part of earth project.
 *
 * @copyright  Copyright (C) 2022 __ORGANIZATION__.
 * @license    __LICENSE__
 */

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Installer;

use Composer\Script\Event;

/**
 * The EarthInstaller class.
 */
class EarthInstaller
{
    public static function install(Event $event): void
    {
        $io = $event->getIO();
        $gitignore = static::getRootDir('.gitignore');

        $ignore = file_get_contents($gitignore);

        $ignore = preg_replace('~# @Dev(.*)# @EndDev\s+~sm', '', $ignore);

        file_put_contents($gitignore, $ignore);

        $io->write('Remove .gitignore dev files.');
    }

    public static function npmrc(Event $event): void
    {
        $io = $event->getIO();

        $dist = static::getRootDir('.npmrc.dist');
        $npmrc = static::getRootDir('.npmrc');

        if (is_file($npmrc)) {
            $io->write('.npmrc file already exists.');
            return;
        }

        $io->write('');
        $io->write('Provide FontAwesome Pro Token (leave empty to ignore).');
        $token = trim((string) $io->ask('[Token]: '));

        if ($token === '') {
            return;
        }

        $rcContent = file_get_contents($dist);

        $rcContent = str_replace('$FA_TOKEN', '', $rcContent);
        $rcContent = str_replace(':_authToken=', ':_authToken=' . $token, $rcContent);

        file_put_contents($npmrc, $rcContent);

        $io->write('Create: ' . realpath($npmrc));
    }

    protected static function getRootDir(string $path): string
    {
        return __DIR__ . '/../../../../../' . $path;
    }
}
