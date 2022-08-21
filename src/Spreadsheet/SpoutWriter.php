<?php

/**
 * Part of earth project.
 *
 * @copyright  Copyright (C) 2022 __ORGANIZATION__.
 * @license    MIT
 */

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Spreadsheet;

use Lyrasoft\Toolkit\Spreadsheet\Spout\ColumnStyle;
use MyCLabs\Enum\Enum;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\AbstractWriterMultiSheets;
use Symfony\Component\OptionsResolver\OptionsResolver;
use OpenSpout\Writer;
use Windwalker\Http\Response\AttachmentResponse;
use Windwalker\Utilities\Arr;
use function Windwalker\value;

/**
 * The SpoutWriter class.
 *
 * @extends AbstractSpreadsheetWriter<Writer\AbstractWriter>
 *
 * @method Writer\AbstractWriter getDriver()
 * @method ColumnStyle addColumn(string $alias, string $title = '', array $options = [])
 * @method ColumnStyle setColumn(string|int $col, string $alias, string $title = '', array $options = [])
 * @method Row addRow(?callable $handler = null)
 * @method Cell setRowCell(string $alias, mixed $value, ?string $format = null)
 */
class SpoutWriter extends AbstractSpreadsheetWriter
{
    /**
     * @var array<array{ title: string, alias: string, index: int, width: int }>
     */
    protected array $columnItems = [];

    /**
     * @var Row[]
     */
    protected array $rows = [];

    protected ?Writer\Common\AbstractOptions $writerOptions = null;

    public function __construct(array $options = [])
    {
        parent::__construct($options);

        $this->prepareSheetInfo(0);
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->define('format')
            ->allowedTypes('string')
            ->allowedValues(
                'csv',
                'xlsx',
                'ods'
            )
            ->default('xlsx');
    }

    public function useFormat(string $format): static
    {
        $this->setOption('format', $format);

        return $this;
    }

    protected function setActiveSheetToDriver(int|string $indexOrName): Writer\Common\Entity\Sheet
    {
        $driver = $this->getDriver();

        if ($driver instanceof AbstractWriterMultiSheets) {
            $sheets = $driver->getSheets();

            if (is_string($indexOrName)) {
                $currentSheet = Arr::findFirst(
                    $sheets,
                    fn (Writer\Common\Entity\Sheet $sheet) => $sheet->getName() === $indexOrName
                );

                if ($currentSheet) {
                    return $currentSheet;
                }

                return $driver->addNewSheetAndMakeItCurrent()->setName($indexOrName);
            }

            $sheet = $sheets[$indexOrName];
            $driver->setCurrentSheet($sheet);

            return $driver->getCurrentSheet();
        }

        throw new \LogicException('Spout CSV not support multi-sheets');
    }

    public function getActiveSheetIndex(): int
    {
        if (!$this->driver) {
            return 0;
        }

        $driver = $this->getDriver();

        if ($driver instanceof AbstractWriterMultiSheets) {
            return $driver->getCurrentSheet()->getIndex();
        }

        return 0;
    }

    public function configureColumns(?callable $handler): void
    {
        if ($this->driver) {
            throw new \LogicException(
                'Spout must configure columns before Writer created.'
            );
        }

        $this->columnItems = [];
        $this->writerOptions = $this->getWriterOptions();

        parent::configureColumns($handler);



        $this->columnItems = [];
    }

    protected function prepareColumn(int $colIndex, string $alias, string $title = '', array $options = []): object
    {
        $this->columnItems[$alias] = [
            'title' => $title,
            'width' => 10,
            'index' => count($this->columnItems),
            'alias' => $alias
        ];

        $setWidth = function (int $width) use ($alias) {
            $this->columnItems[$alias]['width'] = $width;
        };

        return new ColumnStyle($setWidth);
    }

    public function useRow(int $row, ?callable $handler = null): Row
    {
        $this->rows[$row] ??= new Row([]);

        return parent::useRow($row, $handler);
    }

    protected function getRowObject(int $rowIndex): Row
    {
        return $this->rows[$rowIndex];
    }

    protected function setValueToCell(object $cell, mixed $value, ?string $format = null): Cell
    {
        if ($value instanceof Enum || $value instanceof \UnitEnum) {
            $value = value($value);
        }

        /** @var \Closure $cell */
        return $cell($value);
    }

    public function getCellByIndex(int|string $colIndex, int $rowIndex): object
    {
        if (is_string($colIndex)) {
            $colIndex = static::alpha2num($colIndex);
        }

        $this->rows[$rowIndex] ??= new Row([]);

        $cell = $this->rows[$rowIndex]->getCellAtIndex($colIndex);

        return function (mixed $value) use ($colIndex, $cell, $rowIndex) {
            $newCell = Cell::fromValue($value, $cell?->getStyle());

            $this->rows[$rowIndex]->setCellAtIndex(Cell::fromValue($value), $colIndex);

            return $newCell;
        };
    }

    public function getCellByCode(string $code): object
    {
        throw new \LogicException('SportWriter dose not support getCellByCode()');
    }

    protected function defaultCreateDriver(): object
    {
        $format = $this->getOption('format');

        $options = $this->writerOptions ??= $this->getWriterOptions();

        return match ($format) {
            'csv' => new Writer\CSV\Writer($options),
            'xlsx' => new Writer\XLSX\Writer($options),
            'ods' => new Writer\ODS\Writer($options),
        };
    }

    protected function preprocessDriver(object $driver)
    {
        //
    }

    public function toAttachmentResponse(
        ?string $filename = null,
        string $format = 'xlsx',
        $temp = 'php://temp'
    ): AttachmentResponse {
        throw new \LogicException('SpoutWriter does not support ' . __METHOD__);
    }

    public function download(?string $filename = null, string $format = ''): void
    {
        $filename ??= $this->prepareDownloadFilename(
            (string) $this->getOption('format')
        );

        $this->getDriver()->openToBrowser($filename);
    }

    public function save($file, string $format = ''): void
    {
        if (is_resource($file)) {
            throw new \LogicException('SpoutWriter can only output as file');
        }

        $driver = $this->getDriver();

        if ($file === 'php://output') {
            $filename = $this->prepareDownloadFilename((string) $this->getOption('format'));
            $driver->openToBrowser($filename);
            return;
        }

        $driver->openToFile($file);
    }

    public function writeDataToFile(): Writer\AbstractWriter
    {
        $driver = $this->getDriver();
        
        $cols = [];

        foreach (array_values($this->columnItems) as $i => $columnItem) {
            $cols[] = $columnItem['title'];

            $this->writerOptions->setColumnWidth($columnItem['width'], $i + 1);
        }

        // Add column row
        $row = Row::fromValues($cols);

        $driver->addRow($row);

        // Add rows
        foreach ($this->rows as $row) {
            $cells = [];

            foreach (array_values($this->columnItems) as $i => $columnItem) {
                $cell = $row->getCellAtIndex($i);

                if (!$cell) {
                    $cell = Cell::fromValue('');
                }

                $cells[] = $cell;
            }

            $driver->addRow(new Row($cells, $row->getStyle()));
        }

        return $driver;
    }

    public function finish(): void
    {
        $this->writeDataToFile();

        $this->getDriver()->close();
    }

    protected function getWriterOptions(): Writer\Common\AbstractOptions
    {
        return match ($this->getOption('format')) {
            'xlsx' => new Writer\XLSX\Options(),
            'csv' => new Writer\CSV\Options(),
            'ods' => new Writer\ODS\Options(),
        };
    }
}
