<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit;

use Windwalker\Core\Package\AbstractPackage;
use Windwalker\Core\Package\PackageInstaller;

/**
 * The ToolkitPackage class.
 */
class ToolkitPackage extends AbstractPackage
{
    public function install(PackageInstaller $installer): void
    {
        $installer->installFiles(
            __DIR__ . '/../.ide/phpstorm/idea/**/*',
            '.idea/',
            'ide'
        );
    }
}
