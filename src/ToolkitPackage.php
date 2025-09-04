<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit;

use Lyrasoft\Toolkit\Api\LoginRequireApi;
use Lyrasoft\Toolkit\Command\LangExportCommand;
use Lyrasoft\Toolkit\Command\LangExtractCommand;
use Lyrasoft\Toolkit\Command\LangImportCommand;
use Lyrasoft\Toolkit\Command\S3VCSCommand;
use Lyrasoft\Toolkit\Command\TypeEntityCommand;
use Lyrasoft\Toolkit\Command\TypeEnumCommand;
use Windwalker\Core\Application\AppClient;
use Windwalker\Core\Application\ApplicationInterface;
use Windwalker\Core\Package\AbstractPackage;
use Windwalker\Core\Package\PackageInstaller;
use Windwalker\DI\Attributes\AttributeType;
use Windwalker\DI\Container;
use Windwalker\DI\ServiceProviderInterface;

/**
 * The ToolkitPackage class.
 */
class ToolkitPackage extends AbstractPackage implements ServiceProviderInterface
{
    public function __construct(protected ApplicationInterface $app)
    {
    }

    public function install(PackageInstaller $installer): void
    {
        $installer->installFiles(
            __DIR__ . '/../.ide/phpstorm/idea/**/*',
            '.idea/',
            'ide'
        );

        $installer->installConfig(__DIR__ . '/../etc/*.php', 'config');
    }

    public function register(Container $container): void
    {
        if ($this->app->getClient() === AppClient::CONSOLE) {
            $container->mergeParameters(
                'commands',
                [
                    'lang:extract' => LangExtractCommand::class,
                    'lang:export' => LangExportCommand::class,
                    'lang:import' => LangImportCommand::class,
                    'type:entity' => TypeEntityCommand::class,
                    'type:enum' => TypeEnumCommand::class,
                    's3:vcs' => S3VCSCommand::class,
                ]
            );
        }

        $container->getAttributesResolver()
            ->registerAttribute(LoginRequireApi::class, AttributeType::METHODS);
    }
}
