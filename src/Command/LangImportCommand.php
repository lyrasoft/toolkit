<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Command;

use Lyrasoft\Toolkit\Spreadsheet\PhpSpreadsheetReader;
use Lyrasoft\Toolkit\Spreadsheet\SpreadsheetKit;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Windwalker\Console\CommandInterface;
use Windwalker\Console\CommandWrapper;
use Windwalker\Console\InteractInterface;
use Windwalker\Console\IOInterface;
use Windwalker\Filesystem\FileObject;
use Windwalker\Filesystem\Filesystem;
use Windwalker\Filesystem\Path;

use function Windwalker\collect;
use function Windwalker\fs;

#[CommandWrapper(
    description: 'Import language sheet (xls, xlsx, csv) to ini files.'
)]
class LangImportCommand implements CommandInterface, InteractInterface
{
    protected IOInterface $io;

    /**
     * @var string[]
     */
    protected array $allowExtensions = ['xlsx', 'xls', 'csv'];

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
            'src',
            InputArgument::REQUIRED,
            'The import file or folder path.'
        );

        $command->addArgument(
            'lang',
            InputArgument::REQUIRED,
            'The import target language code.',
        );
    }

    /**
     * @inheritDoc
     */
    public function interact(IOInterface $io): void
    {
        if (!$io->getArgument('lang')) {
            $languages = Filesystem::folders(WINDWALKER_LANGUAGES)
                ->map(fn (FileObject $folder) => $folder->getBasename())
                ->toArray();

            $qn = new ChoiceQuestion(
                '<question>Please select lang code to import:</question>',
                $languages
            );

            $io->newLine();
            $selected = $io->ask($qn);

            $io->setArgument('lang', $selected);
        }
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

        $src = fs(Path::realpath($io->getArgument('src')));

        $langCode = $io->getArgument('lang');

        if ($src->isDir()) {
            $this->importDir($src, $langCode);
        } elseif ($src->isFile()) {
            $this->importFile($src, $langCode);
        } else {
            throw new \RuntimeException('The source file or folder not exists.');
        }

        return 0;
    }

    protected function importFile(FileObject $file, string $langCode): void
    {
        $excel = SpreadsheetKit::createPhpSpreadsheetReader();
        $excel->loadFile($file);

        foreach ($excel->getSpreadsheet()->getAllSheets() as $sheet) {
            $this->importWorksheet($excel, $sheet, $langCode);
        }
    }

    protected function importDir(FileObject $dir, string $langCode): void
    {
        $files = $dir->files();

        foreach ($files as $file) {
            $ext = $file->getExtension();

            if (!in_array($ext, $this->allowExtensions, true)) {
                continue;
            }

            // Ignore temp file
            if (str_starts_with($file->getBasename(), '~')) {
                continue;
            }

            $excel = SpreadsheetKit::createPhpSpreadsheetReader();
            $excel->loadFile($file);

            $ext = $file->getExtension();
            $title = $file->getBasename('.' . $ext);

            $sheet = $excel->getActiveSheet();

            $this->importWorksheet($excel, $sheet, $langCode, $title);
        }
    }

    protected function importWorksheet(
        PhpSpreadsheetReader $reader,
        Worksheet $sheet,
        string $langCode,
        ?string $title = null
    ): void {
        $strings = collect();
        $title ??= $sheet->getTitle();

        $rows = $reader->getRowIterator(true, $sheet->getTitle());

        foreach ($rows as $row) {
            if (!$row['Key']) {
                continue;
            }

            $strings[$row['Key']] = $row['Value'];
        }

        $iniString = $strings->toIni();

        $fileName = WINDWALKER_LANGUAGES . '/' . $langCode . '/' . $title . '.ini';

        Filesystem::mkdir(dirname($fileName));

        file_put_contents($fileName, $iniString);

        $this->io->writeln("[Write] <info>$fileName</info>");
    }
}
