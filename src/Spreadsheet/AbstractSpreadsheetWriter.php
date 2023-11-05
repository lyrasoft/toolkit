<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Windwalker\Core\DateTime\Chronos;
use Windwalker\Http\Helper\HeaderHelper;
use Windwalker\Http\Output\Output;
use Windwalker\Http\Response\AttachmentResponse;
use Windwalker\Http\Response\Response;
use Windwalker\Stream\Stream;
use Windwalker\Utilities\Options\OptionsResolverTrait;

/**
 * The AbstractSpreadsheetExporter class.
 *
 * @template T
 */
abstract class AbstractSpreadsheetWriter
{
    use OptionsResolverTrait;

    protected array $currentRowIndex = [];

    protected array $maxRowIndex = [];

    protected array $columnMapping = [];

    protected ?\Closure $createDriverHandler = null;

    protected ?object $driver = null;

    protected ?\Closure $preprocess = null;

    public function __construct(array $options = [])
    {
        $this->resolveOptions($options, [$this, 'configureOptions']);
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('show_header')
            ->allowedTypes('bool')
            ->default(true);

        $resolver->define('creator')
            ->allowedTypes('string')
            ->default('Windwalker');

        $resolver->define('title')
            ->allowedTypes('string')
            ->default('');

        $resolver->define('description')
            ->allowedTypes('string')
            ->default('');
    }

    public function setActiveSheet(int|string $indexOrName, ?\Closure $handler = null): object
    {
        $sheet = $this->setActiveSheetToDriver($indexOrName);

        $sheetIndex = $this->getActiveSheetIndex();

        $this->prepareSheetInfo($sheetIndex);

        if ($handler) {
            $handler($this, $sheet, $sheetIndex);
        }

        return $sheet;
    }

    abstract protected function setActiveSheetToDriver(int|string $indexOrName): object;

    abstract public function getActiveSheetIndex(): int;

    public function addColumn(string $alias, string $title = '', array $options = []): object
    {
        $sheetIndex = $this->getActiveSheetIndex();

        $this->columnMapping[$sheetIndex][] = $alias;

        return $this->prepareColumn(
            array_key_last($this->columnMapping[$sheetIndex]) + 1,
            $alias,
            $title,
            $options
        );
    }

    public function configureColumns(?callable $handler)
    {
        $handler($this);
    }

    public function setColumn(string|int $col, string $alias, string $title = '', array $options = []): object
    {
        if (is_int($col)) {
            $colIndex = $col;
        } else {
            $colIndex = static::alpha2num($col);
        }

        $this->columnMapping[$this->getActiveSheetIndex()][$colIndex - 1] = $alias;

        return $this->prepareColumn(
            $colIndex,
            $alias,
            $title,
            $options
        );
    }

    abstract protected function prepareColumn(
        int $colIndex,
        string $alias,
        string $title = '',
        array $options = []
    ): mixed;

    public function getColumnIndexByAlias(string $alias): int
    {
        $index = array_search($alias, $this->columnMapping[$this->getActiveSheetIndex()], true);

        if ($index === false) {
            throw new \OutOfBoundsException('Column alias: ' . $alias . ' not found.');
        }

        return $index;
    }

    /**
     * @param  int            $row
     * @param  callable|null  $handler
     *
     * @return object
     */
    public function useRow(int $row, ?callable $handler = null): object
    {
        $sheetIndex = $this->getActiveSheetIndex();

        $this->currentRowIndex[$sheetIndex] = $row;
        $this->maxRowIndex[$sheetIndex] = max($this->maxRowIndex[$sheetIndex], $row);

        $rowObject = $this->getRowObject($row);

        if ($handler) {
            $handler($this, $rowObject, $row);
        }

        return $rowObject;
    }

    public function addRow(?callable $handler = null): object
    {
        $sheetIndex = $this->getActiveSheetIndex();

        return $this->useRow(
            $this->maxRowIndex[$sheetIndex] + 1,
            $handler
        );
    }

    protected function getTargetRowIndex(int $index): int
    {
        $showHeader = $this->options['show_header'];

        if ($showHeader) {
            ++$index;
        }

        return $index;
    }

    abstract protected function getRowObject(int $rowIndex): object;

    /**
     * @return int
     */
    public function getCurrentRowIndex(): int
    {
        $sheetIndex = $this->getActiveSheetIndex();

        return $this->currentRowIndex[$sheetIndex];
    }

    public function setRowCell(string $alias, mixed $value, ?string $format = null): object
    {
        $cell = $this->getCell($alias, $this->getCurrentRowIndex());

        return $this->setValueToCell($cell, $value, $format);
    }

    public function setRowCellTo(string $alias, int $rowIndex, mixed $value, ?string $format = null): object
    {
        $cell = $this->getCell($alias, $rowIndex);

        return $this->setValueToCell($cell, $value, $format);
    }

    public function setCellValueByCode(string $code, mixed $value, ?string $format = null): object
    {
        $cell = $this->getCellByCode($code);

        return $this->setValueToCell($cell, $value, $format);
    }

    /**
     * @template C
     *
     * @param  object|C     $cell
     * @param  mixed        $value
     * @param  string|null  $format
     *
     * @return  object|C
     */
    abstract protected function setValueToCell(object $cell, mixed $value, ?string $format = null): object;

    abstract public function getCellByIndex(int|string $colIndex, int $rowIndex): object;

    abstract public function getCellByCode(string $code): object;

    public function getCell(string $alias, int $rowIndex): object
    {
        $index = $this->getColumnIndexByAlias($alias);

        return $this->getCellByIndex($index, $rowIndex);
    }

    /**
     * @return  object|T
     */
    public function getDriver(): object
    {
        return $this->driver ??= $this->createDriver();
    }

    protected function setDriver(object $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    protected function createDriver(): object
    {
        $driver = $this->getCreateDriverHandler()();

        $driver = $this->preprocessDriver($driver) ?? $driver;

        if ($this->preprocess) {
            $driver = ($this->preprocess)($driver, $this->options) ?? $driver;
        }

        $this->prepareSheetInfo(0);

        return $driver;
    }

    abstract protected function defaultCreateDriver(): object;

    /**
     * @return \Closure
     */
    public function getCreateDriverHandler(): \Closure
    {
        return $this->createDriverHandler ?? fn() => $this->defaultCreateDriver();
    }

    /**
     * @param  \Closure|null  $createDriverHandler
     *
     * @return  static  Return self to support chaining.
     */
    public function setCreateDriverHandler(?\Closure $createDriverHandler): static
    {
        $this->createDriverHandler = $createDriverHandler;

        return $this;
    }

    /**
     * @return \Closure|null
     */
    public function getPreprocessHandler(): ?\Closure
    {
        return $this->preprocess;
    }

    /**
     * @param  \Closure|null  $preprocess
     *
     * @return  static  Return self to support chaining.
     */
    public function preprocess(?\Closure $preprocess): static
    {
        $this->preprocess = $preprocess;

        return $this;
    }

    /**
     * @param  int  $sheetIndex
     *
     * @return  void
     */
    protected function prepareSheetInfo(int $sheetIndex): void
    {
        $this->currentRowIndex[$sheetIndex] ??= $this->getTargetRowIndex(0);
        $this->maxRowIndex[$sheetIndex] ??= $this->getTargetRowIndex(0);
        $this->columnMapping[$sheetIndex] ??= [];
    }

    abstract protected function preprocessDriver(object $driver);

    /**
     * @param  string           $format
     * @param  resource|string  $temp
     *
     * @return  string
     */
    public function render(string $format = 'xlsx', $temp = 'php://temp'): string
    {
        return (string) $this->saveAsPsrStream($format, $temp);
    }

    public function renderHtmlTable($temp = 'php://temp'): string
    {
        return (string) $this->saveAsPsrStream('html', $temp);
    }

    public function printHtmlTable(): void
    {
        $this->renderHtmlTable('php://output');
    }

    public function saveAsPsrStream(string $format = 'xlsx', $temp = 'php://temp'): StreamInterface
    {
        $fp = fopen($temp, 'rb+');

        $this->save($fp, $format);

        $stream = new Stream($fp);

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $stream;
    }

    public function toAttachmentResponse(
        ?string $filename = null,
        string $format = 'xlsx',
        $temp = 'php://temp'
    ): AttachmentResponse {
        $filename ??= $this->prepareDownloadFilename($format);

        $body = $this->saveAsPsrStream($format, $temp);

        return \Windwalker\response()
            ->attachment($body)
            ->withFilename($filename);
    }

    public function download(
        ?string $filename = null,
        string $format = 'xlsx',
    ): void {
        $filename ??= $this->prepareDownloadFilename($format);

        // Redirect output to a client’s web browser (Xlsx)
        $response = HeaderHelper::prepareAttachmentHeaders(new Response(), $filename);

        (new Output())->sendHeaders($response);

        $this->save('php://output', $format);
        die;
    }

    /**
     * @param  resource|string  $file
     * @param  string           $format
     *
     * @return  void
     */
    abstract public function save($file, string $format = 'xlsx'): void;

    protected function prepareDownloadFilename(string $format): string
    {
        $ext = strtolower($format);

        if ($this->getOption('title')) {
            return $this->getOption('title') . '.' . $ext;
        }

        return 'Export-' . Chronos::now('Y-m-d-H-i-s') . '.' . $ext;
    }

    /**
     * @see    https://stackoverflow.com/a/5554413
     *
     * @param  int  $n
     *
     * @return  string
     */
    public static function num2alpha(int $n): string
    {
        for ($r = ''; $n >= 0; $n = (int) ($n / 26) - 1) {
            $r = chr($n % 26 + 0x41) . $r;
        }

        return $r;
    }

    /**
     * @see    https://stackoverflow.com/a/5554413
     *
     * @param  string  $a
     *
     * @return  int
     */
    public static function alpha2num(string $a): int
    {
        $l = strlen($a);
        $n = 0;

        for ($i = 0; $i < $l; $i++) {
            $n = $n * 26 + ord($a[$i]) - 0x40;
        }

        return $n - 1;
    }
}
