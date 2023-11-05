<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Spreadsheet\Spout;

/**
 * The ColumnStyle class.
 */
class ColumnStyle
{
    public function __construct(protected \Closure $setWidth)
    {
        //
    }

    public function setWidth(int $width): static
    {
        ($this->setWidth)($width);

        return $this;
    }
}
