<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * The SpreadsheetFactory class.
 */
class SpreadsheetKit
{
    public static function createWriter(string $driver, array $options = []): AbstractSpreadsheetWriter
    {
        return match (strtolower($driver)) {
            'php_spreadsheet' => static::createAfterCheckDependency(
                Spreadsheet::class,
                'phpoffice/phpspreadsheet',
                static fn () => new PhpSpreadsheetWriter($options)
            ),
            'spout', 'openspout' => static::createAfterCheckDependency(
                \OpenSpout\Writer\AbstractWriter::class,
                'openspout/openspout',
                static fn () => new SpoutWriter($options)
            ),
        };
    }

    public static function createReader(string $driver, array $options = []): AbstractSpreadsheetReader
    {
        return match (strtolower($driver)) {
            'php_spreadsheet' => static::createAfterCheckDependency(
                Spreadsheet::class,
                'phpoffice/phpspreadsheet',
                static fn () => new PhpSpreadsheetReader($options)
            ),
        };
    }

    public static function createPhpSpreadsheetWriter(array $options = []): PhpSpreadsheetWriter
    {
        return static::createWriter('php_spreadsheet', $options);
    }

    public static function createPhpSpreadsheetReader(array $options = []): PhpSpreadsheetReader
    {
        return static::createReader('php_spreadsheet', $options);
    }

    public static function createSpoutWriter(array $options = []): SpoutWriter
    {
        return static::createWriter('spout', $options);
    }

    public static function createAfterCheckDependency(
        string $className,
        string $package,
        \Closure $factory
    ) {
        if (!class_exists($className)) {
            throw new \DomainException("Please install $package first.");
        }

        return $factory();
    }
}
