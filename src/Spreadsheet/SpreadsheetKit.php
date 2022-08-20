<?php

/**
 * Part of earth project.
 *
 * @copyright  Copyright (C) 2022 __ORGANIZATION__.
 * @license    __LICENSE__
 */

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Spreadsheet;

/**
 * The SpreadsheetFactory class.
 */
class SpreadsheetKit
{
    public static function createWriter(string $driver, array $options = []): AbstractSpreadsheetWriter
    {
        return match (strtolower($driver)) {
            'php_spreadsheet' => new PhpSpreadsheetWriter($options),
            'spout', 'openspout' => new SpoutWriter($options),
        };
    }

    public static function createPhpSpreadsheetWriter(array $options = []): PhpSpreadsheetWriter
    {
        return static::createWriter('php_spreadsheet', $options);
    }

    public static function createSpoutWriter(array $options = []): SpoutWriter
    {
        return static::createWriter('spout', $options);
    }
}
