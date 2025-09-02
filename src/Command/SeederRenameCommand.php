<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Windwalker\Console\CommandInterface;
use Windwalker\Console\CommandWrapper;
use Windwalker\Console\IOInterface;
use Windwalker\Filesystem\Path;
use Windwalker\Utilities\Str;

use function Windwalker\fs;

#[CommandWrapper(
    description: 'Rename seeder to new type.',
    hidden: true,
)]
class SeederRenameCommand implements CommandInterface
{
    public function configure(Command $command): void
    {
        $command->addArgument(
            name: 'dir',
            mode: InputArgument::REQUIRED,
            description: 'The directory of the seeder file to rename.'
        );
    }

    public function execute(IOInterface $io): int
    {
        $dir = $io->getArgument('dir');
        $dir = Path::realpath($dir);

        $dirObject = fs($dir);

        foreach ($dirObject->files() as $file) {
            if (str_ends_with($file->getFilename(), '-seeder.php')) {
                $newName = Str::removeRight($file->getFilename(), '-seeder.php') . '.seeder.php';

                $file->moveTo($file->getPath() . '/' . $newName);

                $io->writeln("Renamed: " . $file->getFilename() . " to " . $newName);
            }
        }

        return 0;
    }
}
