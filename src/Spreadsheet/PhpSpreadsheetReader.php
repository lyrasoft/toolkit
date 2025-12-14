<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Psr\Http\Message\StreamInterface;
use Traversable;
use Windwalker\Data\Collection;
use Windwalker\Filesystem\FileObject;
use Windwalker\Filesystem\Filesystem;
use Windwalker\Filesystem\Path;

use function Windwalker\collect;

class PhpSpreadsheetReader extends AbstractSpreadsheetReader
{
    protected Spreadsheet $spreadsheet;

    public protected(set) ReaderOptions $options;

    public function __construct(array|ReaderOptions $options = [])
    {
        $this->options = ReaderOptions::wrapWith($options);
    }

    public function load(string|StreamInterface $data, string $format = 'Xlsx'): static
    {
        $temp = Filesystem::createTemp($this->options->tempPath);
        $temp->deleteWhenDestruct();
        $temp->deleteWhenShutdown();

        $temp->write($data);

        $this->loadFile($temp, $format);

        return $this;
    }

    public function loadFile(string|\SplFileInfo $file, ?string $format = null): self
    {
        $file = FileObject::unwrap($file);

        $format = $format ?? Path::getExtension($file);

        $reader = $this->createReader($format);

        $this->spreadsheet = $reader->load($file);

        return $this;
    }

    public function getRowIterator(bool $asValue = false, int|string|null $sheet = null): ReaderRowIterator
    {
        $worksheet = $this->getWorksheet($sheet);

        return $this->iterateSheetRows($worksheet, $asValue);
    }

    public function getColumnIterator(bool $asValue = false, int|string|null $sheet = null): ReaderRowIterator
    {
        $worksheet = $this->getWorksheet($sheet);

        return $this->iterateSheetColumns($worksheet, $asValue);
    }

    /**
     * @param  bool|null  $asValue
     *
     * @return  \Generator<ReaderRowIterator>
     */
    public function getSheetsIterator(?bool $asValue = null): \Generator
    {
        $loop = function () use ($asValue) {
            $sheets = $this->spreadsheet->getAllSheets();

            foreach ($sheets as $sheet) {
                yield $sheet->getTitle() => $this->iterateSheetRows($sheet, $asValue);
            }
        };

        return $loop();
    }

    public function getSheetData(int|string|null $sheet = null): array
    {
        return iterator_to_array($this->getRowIterator(true, $sheet));
    }

    public function getAllData(): array
    {
        return array_map(
            'iterator_to_array',
            iterator_to_array($this->getSheetsIterator(true))
        );
    }

    /**
     * @param  Worksheet  $sheet
     * @param  bool|null  $asValue
     *
     * @return  ReaderRowIterator<Collection>
     */
    public function iterateSheetRows(Worksheet $sheet, ?bool $asValue = null): ReaderRowIterator
    {
        $runner = function (ReaderRowIterator $iterator) use ($sheet, $asValue) {
            $isHeaderAsField = $iterator->isHeaderAsField();
            $asValue ??= $iterator->isRowToValue();
            $startFrom = $iterator->getStartFrom();

            $fields = collect();

            foreach ($sheet->getRowIterator() as $i => $row) {
                if ($i < $startFrom) {
                    continue;
                }

                // First row
                if ($i === $startFrom && $isHeaderAsField) {
                    // Prepare fields title
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);

                    foreach ($cellIterator as $cell) {
                        $fields[$cell->getColumn()] = $col = $cell->getFormattedValue();

                        if ($col === '') {
                            $fields[$cell->getColumn()] = $cell->getColumn();
                        }
                    }

                    continue;
                }

                $item = collect();
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                foreach ($cellIterator as $cell) {
                    $column = $isHeaderAsField
                        ? $fields[$cell->getColumn()]
                        : $cell->getColumn();

                    if ($asValue) {
                        $item[$column] = $cell->getFormattedValue();
                    } else {
                        $item[$column] = $cell;
                    }
                }

                yield $i => $item;
            }
        };

        return new ReaderRowIterator($runner);
    }

    /**
     * @param  Worksheet  $sheet
     * @param  bool|null  $asValue
     *
     * @return  ReaderRowIterator<Collection>
     */
    public function iterateSheetColumns(Worksheet $sheet, ?bool $asValue = null): ReaderRowIterator
    {
        $runner = function (ReaderRowIterator $iterator) use ($sheet, $asValue) {
            $isHeaderAsField = $iterator->isHeaderAsField();
            $asValue ??= $iterator->isRowToValue();
            $startFrom = $iterator->getStartFrom();

            $fields = collect();

            foreach ($sheet->getColumnIterator() as $f => $row) {
                $i = Coordinate::columnIndexFromString($f);

                if ($i < $startFrom) {
                    continue;
                }

                // First row
                if ($i === $startFrom && $isHeaderAsField) {
                    // Prepare fields title
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);

                    foreach ($cellIterator as $cell) {
                        $fields[$cell->getRow()] = $col = $cell->getFormattedValue();

                        if ($col === '') {
                            $fields[$cell->getRow()] = $cell->getRow();
                        }
                    }
                }

                $item = collect();
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                foreach ($cellIterator as $cell) {
                    $column = $isHeaderAsField
                        ? $fields[$cell->getRow()]
                        : $cell->getRow();

                    if ($asValue) {
                        $item[$column] = $cell->getFormattedValue();
                    } else {
                        $item[$column] = $cell;
                    }
                }

                yield $f => $item;
            }
        };

        return new ReaderRowIterator($runner);
    }

    public function eachSheet(callable $handler, bool $asValue = false, $sheet = null): void
    {
        foreach ($this->getRowIterator($asValue, $sheet) as $key => $item) {
            $handler($item, $key);
        }
    }

    public function eachAll(callable $handler, bool $asValue = false): void
    {
        /** @var \Generator $sheet */
        foreach ($this->getSheetsIterator($asValue) as $sheet) {
            foreach ($sheet as $key => $item) {
                $handler($item, $key, $sheet);
            }
        }
    }

    public function createReader(?string $format = null): IReader
    {
        if (!class_exists(Spreadsheet::class)) {
            throw new \DomainException('Please install phpoffice/phpspreadsheet first.');
        }

        $format = $format ?? 'xlsx';

        $reader = IOFactory::createReader(ucfirst($format));
        $reader->setReadDataOnly(true);

        return $reader;
    }

    public function getIterator(): Traversable
    {
        return $this->getRowIterator(true);
    }

    /**
     * @param  int|string|null  $sheet
     *
     * @return  Worksheet
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function getWorksheet(int|string|null $sheet): Worksheet
    {
        if (is_int($sheet)) {
            $worksheet = $this->spreadsheet->getSheet($sheet);
        } elseif (is_string($sheet)) {
            $worksheet = $this->spreadsheet->getSheetByName($sheet);
        } else {
            $worksheet = $this->spreadsheet->getActiveSheet();
        }

        if (!$worksheet) {
            throw new \RuntimeException("Worksheet of `$sheet` not found.");
        }

        return $worksheet;
    }

    public function getActiveSheet(): Worksheet
    {
        return $this->spreadsheet->getActiveSheet();
    }

    public function getActiveSheetIndex(): int
    {
        return $this->spreadsheet->getActiveSheetIndex();
    }

    public function getSpreadsheet(): Spreadsheet
    {
        return $this->spreadsheet;
    }

    public function setSpreadsheet(Spreadsheet $spreadsheet): static
    {
        $this->spreadsheet = $spreadsheet;

        return $this;
    }
}
