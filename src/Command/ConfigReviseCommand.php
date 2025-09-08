<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Windwalker\Console\CommandInterface;
use Windwalker\Console\CommandWrapper;
use Windwalker\Console\Input\InputArgument;
use Windwalker\Console\IOInterface;
use Windwalker\Core\Attributes\ConfigModule;
use Windwalker\Core\Package\AbstractPackage;
use Windwalker\Filesystem\FileObject;
use Windwalker\Filesystem\Path;

use Windwalker\Utilities\Attributes\AttributesAccessor;

use function Windwalker\fs;

#[CommandWrapper(
    description: 'Revise config files.'
)]
class ConfigReviseCommand implements CommandInterface
{
    public function configure(Command $command): void
    {
        $command->addArgument(
            'from',
            InputArgument::OPTIONAL,
            'The source dir or file.',
            WINDWALKER_ETC . '/packages'
        );

        $command->addOption(
            'remove',
            'r',
            InputOption::VALUE_NONE,
            'Remove the original file at dest dir.'
        );
    }

    public function execute(IOInterface $io): int
    {
        $from = $io->getArgument('from');
        $remove = $io->getOption('remove');

        $from = fs(Path::realpath($from));

        if ($from->isDir()) {
            $files = iterator_to_array($from->files());
        } else {
            $files = [$from];
        }

        $deletedCache = [];

        /** @var FileObject $file */
        foreach ($files as $file) {
            $config = include $file->getPathname();

            if ($config instanceof \Closure) {
                $module = AttributesAccessor::getFirstAttributeInstance($config, ConfigModule::class);

                $package = $module->belongsTo;

                /** @var class-string<AbstractPackage> $package */
                if ($package) {
                    $configDir = $package::path('etc');

                    if (!is_dir($configDir)) {
                        $configDir = $package::path('config');
                    }

                    if (is_dir($configDir)) {
                        if ($remove && !($deletedCache[$package] ?? false)) {
                            /** @var FileObject $currentFile */
                            foreach (fs($configDir)->files() as $currentFile) {
                                $io->writeln(
                                    sprintf(
                                        '<comment>[REMOVE]</comment> %s',
                                        $currentFile->getPathname()
                                    )
                                );
                                $currentFile->delete();
                            }

                            $deletedCache[$package] = true;
                        }

                        $file->copyTo($configDir);

                        $io->writeln(
                            sprintf(
                                '<question>[COPY]</question> %s => <info>%s</info>',
                                $file->getPathname(),
                                $configDir
                            )
                        );
                    } else {
                        $io->writeln(
                            sprintf(
                                '<error>[MISS]</error> %s package config dir not found.',
                                $package
                            )
                        );
                    }
                } else {
                    $io->writeln(
                        sprintf(
                            '<error>[MISS]</error> %s not belongs to any package.',
                            $file->getPathname()
                        )
                    );
                }
            }
        }

        return 0;
    }
}
