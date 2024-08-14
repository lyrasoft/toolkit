<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Windwalker\Console\CommandInterface;
use Windwalker\Console\CommandWrapper;
use Windwalker\Console\IOInterface;
use Windwalker\Crypt\SecretToolkit;
use Windwalker\Crypt\Symmetric\CipherInterface;
use Windwalker\Crypt\Symmetric\SodiumCipher;

#[CommandWrapper(
    description: 'Encrypt DSN'
)]
class DsnEncryptCommand implements CommandInterface
{
    public function __construct()
    {
    }

    /**
     * configure
     *
     * @param  Command  $command
     *
     * @return  void
     */
    public function configure(Command $command): void
    {
        $command->addArgument(
            'str',
            InputArgument::REQUIRED,
            'The string to encrypt',
        );
    }

    /**
     * Executes the current command.
     *
     * @param  IOInterface  $io
     *
     * @return  int Return 0 is success, 1-255 is failure.
     */
    public function execute(IOInterface $io): int
    {
        $str = $io->getArgument('str');

        $cipher = new SodiumCipher();
        $key = SecretToolkit::decode(env('ENC_KEY'));

        $enc = $cipher->encrypt($str, $key);

        $io->writeln($enc);

        return 0;
    }
}
