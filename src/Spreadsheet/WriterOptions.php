<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Spreadsheet;

use Windwalker\Utilities\Options\RecordOptionsTrait;

class WriterOptions
{
    use RecordOptionsTrait;

    public function __construct(
        public bool $showHeader = true,
        public string $creator = 'Windwalker',
        public string $title = '',
        public string $description = '',
        public string $format = '',
    ) {
    }
}
