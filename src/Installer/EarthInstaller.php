<?php

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

        $npmDist = static::getRootDir('.npmrc.dist');
        $npmrc = static::getRootDir('.npmrc');
        $yarnDist = static::getRootDir('.yarnrc.yml.dist');
        $yarnrc = static::getRootDir('.yarnrc.yml');

        if (is_file($npmrc) && is_file($yarnrc)) {
            $io->write('.npmrc or .yarnrc.yml file already exists.');
            return;
        }

        $io->write('');
        $io->write('Provide FontAwesome Pro Token (leave empty to use ${FA_TOKEN}, use [N] to ignore).');
        $token = trim((string) $io->ask('[Token]: '));

        if (strtoupper($token) === 'N') {
            return;
        }

        if (!is_file($npmrc)) {
            $rcContent = file_get_contents($npmDist);

            if ($token !== '') {
                $rcContent = str_replace('${FA_TOKEN}', $token, $rcContent);
            }

            file_put_contents($npmrc, $rcContent);

            $io->write('Create: ' . realpath($npmrc));
        }

        if (!is_file($yarnrc)) {
            $rcContent = file_get_contents($yarnDist);

            if ($token !== '') {
                $rcContent = str_replace('${FA_TOKEN}', $token, $rcContent);
            }

            file_put_contents($yarnrc, $rcContent);

            $io->write('Create: ' . realpath($yarnrc));
        }
    }

    protected static function getRootDir(string $path): string
    {
        return __DIR__ . '/../../../../../' . $path;
    }
}
