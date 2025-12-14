<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Spreadsheet;

use Windwalker\Utilities\Options\RecordOptionsTrait;

class ReaderOptions
{
    use RecordOptionsTrait;

    public function __construct(
        public string $tempPath = WINDWALKER_TEMP,
    ) {
    }
}
