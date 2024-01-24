<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Spreadsheet;

use Traversable;

class ReaderRowIterator implements \IteratorAggregate
{
    protected int $startFrom = 1;

    protected bool $headerAsField = true;

    protected bool $rowToValue = true;

    public function __construct(protected \Closure $runner)
    {
    }

    public function getIterator(): Traversable
    {
        return $this->run();
    }

    public function run(): \Generator
    {
        foreach (($this->runner)($this) as $i => $row) {
            yield $i => $row;
        }
    }

    public function getStartFrom(): int
    {
        return $this->startFrom;
    }

    public function startFrom(int $startFrom): static
    {
        $this->startFrom = $startFrom;

        return $this;
    }

    public function isHeaderAsField(): bool
    {
        return $this->headerAsField;
    }

    public function headerAsField(bool $headerAsField): static
    {
        $this->headerAsField = $headerAsField;

        return $this;
    }

    public function isRowToValue(): bool
    {
        return $this->rowToValue;
    }

    public function rowToValue(bool $rowToValue): static
    {
        $this->rowToValue = $rowToValue;

        return $this;
    }
}
