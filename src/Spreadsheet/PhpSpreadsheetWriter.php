<?php

/**
 * Part of earth project.
 *
 * @copyright  Copyright (C) 2022 __ORGANIZATION__.
 * @license    __LICENSE__
 */

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\ColumnDimension;
use PhpOffice\PhpSpreadsheet\Worksheet\RowDimension;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use Windwalker\Filesystem\Filesystem;

/**
 * The PhpSpreadsheetExporter class.
 *
 * @extends AbstractSpreadsheetWriter<Spreadsheet>
 *
 * @method Spreadsheet getDriver()
 * @method ColumnDimension addColumn(string $alias, string $title = '', array $options = [])
 * @method ColumnDimension setColumn(string|int $col, string $alias, string $title = '', array $options = [])
 * @method RowDimension useRow(int|string $row, ?callable $handler = null)
 * @method RowDimension addRow(?callable $handler = null)
 * @method Cell setRowCell(string $alias, mixed $value, ?string $format = null)
 * @method Cell setRowCellTo(string $alias, int $rowIndex, mixed $value, ?string $format = null)
 * @method Cell getCell(string $alias, int $rowIndex)
 */
class PhpSpreadsheetWriter extends AbstractSpreadsheetWriter
{
    protected function setActiveSheetToDriver(int|string $indexOrName): Worksheet
    {
        $driver = $this->getDriver();

        if (is_string($indexOrName)) {
            if (!$driver->getSheetByName($indexOrName)) {
                $driver->createSheet()->setTitle($indexOrName);
            }

            $sheet = $driver->setActiveSheetIndexByName($indexOrName);
        } else {
            $sheet = $driver->setActiveSheetIndex($indexOrName + 1);
        }

        return $sheet;
    }

    public function getActiveSheetIndex(): int
    {
        return $this->getDriver()->getActiveSheetIndex();
    }

    protected function prepareColumn(
        int $colIndex,
        string $alias,
        string $title = '',
        array $options = []
    ): ColumnDimension {
        $driver = $this->getDriver();

        $showHeader = $this->options['show_header'];

        $sheet = $driver->getActiveSheet();

        if ($showHeader) {
            $cell = $sheet->getCell([$colIndex, 1]);
            $cell->setValue($title);
        }

        return $sheet->getColumnDimensionByColumn($colIndex);
    }

    protected function defaultCreateDriver(): Spreadsheet
    {
        if (!class_exists(Spreadsheet::class)) {
            throw new \DomainException('Please install phpoffice/phpspreadsheet first.');
        }

        return new Spreadsheet();
    }

    protected function getRowObject(int $rowIndex): RowDimension
    {
        $driver = $this->getDriver();

        return $driver->getActiveSheet()->getRowDimension($rowIndex);
    }

    protected function setValueToCell(object $cell, mixed $value, ?string $format = null): object
    {
        /** @var Cell $cell */
        if ($value instanceof \DateTimeInterface) {
            $value = Date::dateTimeToExcel($value);
        }

        if ($format !== null) {
            $cell->getStyle()
                ->getNumberFormat()
                ->setFormatCode($format);
        }

        $cell->setValue($value);

        return $cell;
    }

    public function getCellByIndex(int|string $colIndex, int $rowIndex): Cell
    {
        if (is_string($colIndex)) {
            $colIndex = static::alpha2num($colIndex);
        }

        $driver = $this->getDriver();

        return $driver->getActiveSheet()
            ->getCell([$colIndex + 1, $rowIndex]);
    }

    public function getCellByCode(string $code): Cell
    {
        $driver = $this->getDriver();

        return $driver->getActiveSheet()->getCell($code);
    }

    protected function preprocessDriver(object $driver): void
    {
        $creator = (string) $this->getOption('creator');
        $title = (string) $this->getOption('title');
        $desc = (string) $this->getOption('description');

        /** @var Spreadsheet $driver */
        $properties = $driver->getProperties();

        if ($creator !== '') {
            $properties->setCreator($this->getOption('creator'));
        }

        if ($title !== '') {
            $properties->setTitle($title)
                ->setSubject($title);
        }

        if ($desc !== '') {
            $properties->setDescription($desc);
        }
    }

    /**
     * @param  resource|string  $file
     * @param  string           $format
     *
     * @return  void
     */
    public function save($file, string $format = 'xlsx'): void
    {
        if (is_string($file) && !str_contains($file, 'php://')) {
            Filesystem::mkdir(dirname($file));
        }

        $this->getIOWriter($format)->save($file);
    }

    protected function getIOWriter(string $format): IWriter
    {
        $driver = $this->getDriver();

        return IOFactory::createWriter($driver, ucfirst($format));
    }
}
