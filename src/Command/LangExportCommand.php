<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Command;

use Lyrasoft\Toolkit\Spreadsheet\PhpSpreadsheetWriter;
use Lyrasoft\Toolkit\Spreadsheet\SpreadsheetKit;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Windwalker\Console\CommandInterface;
use Windwalker\Console\CommandWrapper;
use Windwalker\Console\IOInterface;
use Windwalker\Core\Application\PathResolver;
use Windwalker\Core\Language\LangService;
use Windwalker\Filesystem\Filesystem;
use Windwalker\Filesystem\Path;

#[CommandWrapper(
    description: 'Export language ini to excel files.'
)]
class LangExportCommand implements CommandInterface
{
    protected IOInterface $io;

    public function __construct(
        protected LangService $langService,
        protected PathResolver $pathResolver,
    ) {
    }

    /**
     * configure
     *
     * @param  Command  $command
     *
     * @return  void
     */
    public function configure(Command $command): void
    {
        $command->addArgument(
            'dest',
            InputArgument::OPTIONAL,
            'Export to dest path'
        );

        $command->addOption(
            'lang',
            'l',
            InputOption::VALUE_REQUIRED,
            'The export language code, default will be system fallback language.',
        );

        $command->addOption(
            'merge',
            'm',
            InputOption::VALUE_NONE,
            'Merge all to one files.'
        );
    }

    /**
     * Executes the current command.
     *
     * @param  IOInterface  $io
     *
     * @return  int Return 0 is success, 1-255 is failure.
     */
    public function execute(IOInterface $io): int
    {
        $this->io = $io;

        $dest = Path::realpath(
            $io->getArgument('dest') ?: WINDWALKER_TEMP . '/lang'
        );
        $langCode = $io->getOption('lang') ?: $this->langService->getFallback();
        $merge = $io->getOption('merge');

        // Load ini
        $stringGroup = [];

        foreach ($this->langService->getClonedPaths() as $path) {
            $path = $this->pathResolver->resolve($path . '/' . $langCode);
            $files = Filesystem::glob($path . '/*.ini');

            foreach ($files as $file) {
                $basename = $file->getBasename('.ini');

                $strings = $this->langService->getLanguage()
                    ->getFormatRegistry()
                    ->loadFile($file);

                foreach ($strings as $key => $string) {
                    $stringGroup[$basename][$key] = $string;
                }
            }
        }

        if ($merge) {
            $this->saveLangsToFile($stringGroup, 'languages-' . $langCode, $dest);
        } else {
            foreach ($stringGroup as $basename => $strings) {
                $this->saveLangsToFile(
                    [
                        $basename => $strings,
                    ],
                    $basename,
                    $dest
                );
            }
        }

        return 0;
    }

    protected function saveLangsToFile(array $stringGroup, string $title, string $dest): void
    {
        $excel = SpreadsheetKit::createPhpSpreadsheetWriter();
        $mainSheetIndex = $excel->getActiveSheetIndex();

        foreach ($stringGroup as $basename => $strings) {
            /** @var Worksheet $sheet */
            $sheet = $excel->setActiveSheet($basename);
            $sheet->getStyle('A1:B9999')
                ->getFont()
                ->setName('Consolas');

            $sheet->setSelectedCells('A1');

            $excel->addColumn('key', 'Key')->setWidth(40);
            $excel->addColumn('value', 'Value')->setWidth(200);

            foreach ($strings as $key => $string) {
                $excel->addRow(
                    function (PhpSpreadsheetWriter $row) use ($key, $string) {
                        $row->setRowCell('key', $key);
                        $row->setRowCell('value', $string);
                    }
                );
            }
        }

        $spreadsheet = $excel->getDriver();
        $spreadsheet->removeSheetByIndex($mainSheetIndex);
        $spreadsheet->setActiveSheetIndex(0);

        $destPath = $dest . '/' . $title . '.xlsx';
        $excel->save($destPath, 'Xlsx');
        $this->io->writeln('[Write] <info>' . $destPath . '</info>');
    }
}
