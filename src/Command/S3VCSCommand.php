<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Unicorn\Storage\Adapter\S3Storage;
use Unicorn\Storage\StorageManager;
use Windwalker\Console\CommandInterface;
use Windwalker\Console\CommandWrapper;
use Windwalker\Console\CompletionContext;
use Windwalker\Console\CompletionHandlerInterface;
use Windwalker\Console\Input\InputOption;
use Windwalker\Console\IOInterface;
use Windwalker\Core\Application\ApplicationInterface;

#[CommandWrapper(
    description: 'Control S3 Versioning Control System (VCS).',
)]
class S3VCSCommand implements CommandInterface, CompletionHandlerInterface
{
    public const string STATUS_ENABLE = 'enable';
    public const string STATUS_DISABLE = 'disable';
    public const string STATUS_VIEW = 'status';
    public const array STATUSES = [
        self::STATUS_ENABLE,
        self::STATUS_DISABLE,
        self::STATUS_VIEW,
    ];

    public function __construct(protected ApplicationInterface $app, protected StorageManager $storageManager)
    {
    }

    public function configure(Command $command): void
    {
        $command->addArgument(
            'status',
            InputArgument::OPTIONAL,
            'The S3 VCS status to control (enable|disable).'
        );
        $command->addOption(
            'profile',
            'p',
            InputOption::VALUE_REQUIRED,
            'The storage profile name.'
        );
        $command->addOption(
            'bucket',
            'b',
            InputOption::VALUE_REQUIRED,
            'The bucket name.'
        );
    }

    public function execute(IOInterface $io): int
    {
        $profile = $io->getOption('profile');
        $status = $io->getArgument('status');
        $bucket = $io->getOption('bucket');

        if (!$status) {
            $status = $io->askChoice(
                'Please provide an action? ',
                [
                    static::STATUS_DISABLE,
                    static::STATUS_ENABLE,
                    static::STATUS_VIEW,
                ],
                2
            );
        }

        $storage = $this->storageManager->get($profile);

        if (!$storage instanceof S3Storage) {
            $profile ??= '__default__';
            throw new \LogicException(
                "The storage '$profile' is not S3Storage."
            );
        }

        $s3Service = $storage->getS3Service();
        $client = $s3Service->getClient();

        $bucket ??= $s3Service->getBucketName();

        if ($status === static::STATUS_VIEW) {
            $result = $client->getBucketVersioning(
                [
                    'Bucket' => $bucket,
                ]
            );

            $currentStatus = $result['Status'] ?? 'Disabled';
            $statusColored = $currentStatus === 'Enabled'
                ? "<info>ENABLED</info>"
                : "<comment>DISABLED</comment>";

            $io->writeln("S3 VCS is currently $statusColored on bucket <info>$bucket</info>.");
            $io->writeln("- Endpoint: <info>{$client->getEndpoint()}</info>.");
            $io->writeln("- Region: <info>{$client->getRegion()}</info>.");

            return 0;
        }

        $result = $client->putBucketVersioning(
            [
                'Bucket' => $bucket,
                'VersioningConfiguration' => [
                    'Status' => $status === self::STATUS_ENABLE ? 'Enabled' : 'Suspended',
                ],
            ]
        );

        $statusColored = $status === self::STATUS_ENABLE
            ? "<info>ENABLED</info>"
            : "<comment>DISABLED</comment>";

        $io->writeln("<info>[OK]</info> S3 VCS $statusColored applied to bucket <info>$bucket</info>.");
        $io->writeln("- Endpoint: <info>{$client->getEndpoint()}</info>.");
        $io->writeln("- Region: <info>{$client->getRegion()}</info>.");

        return 0;
    }

    public function handleCompletions(CompletionContext $context): ?array
    {
        if ($context->isArgument() && $context->name === 'status') {
            return self::STATUSES;
        }

        return null;
    }
}
