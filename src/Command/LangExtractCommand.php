<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Windwalker\Console\CommandInterface;
use Windwalker\Console\CommandWrapper;
use Windwalker\Console\IOInterface;
use Windwalker\Core\Language\LangService;
use Windwalker\Core\Package\PackageRegistry;
use Windwalker\Filesystem\Filesystem;
use Windwalker\Filesystem\Path;
use Windwalker\Utilities\Str;

#[CommandWrapper(
    description: 'Extract lang code from file.',
    hidden: true
)]
class LangExtractCommand implements CommandInterface
{
    public function __construct(
        protected PackageRegistry $packageRegistry,
        protected LangService $langService,
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
            'file',
            InputArgument::REQUIRED
        );
        $command->addArgument(
            'start',
            InputArgument::REQUIRED
        );
        $command->addArgument(
            'end',
            InputArgument::REQUIRED
        );
        $command->addArgument(
            'code',
            InputArgument::REQUIRED
        );
        $command->addOption(
            'lang-prefix',
            '',
            InputOption::VALUE_REQUIRED,
            'Language prefix',
            env('LANG_EXTRACT_PREFIX') ?: 'app.'
        );
        $command->addOption(
            'output',
            'o',
            InputOption::VALUE_REQUIRED,
            'Output file name',
            env('LANG_EXTRACT_OUTPUT') ?: 'main.ini'
        );
        $command->addOption(
            'pkg',
            'p',
            InputOption::VALUE_REQUIRED,
            'The target package, default is root app.',
            env('LANG_EXTRACT_PACKAGE')
        );
        $command->addOption(
            'replace',
            'r',
            InputOption::VALUE_OPTIONAL,
            'Replace type',
            false
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
        $file = $io->getArgument('file');
        $start = $io->getArgument('start');
        $end = $io->getArgument('end');
        $code = $io->getArgument('code');
        $langPrefix = (string) $io->getOption('lang-prefix') ?: 'app.';
        $output = (string) $io->getOption('output');
        $pkg = (string) $io->getOption('pkg');
        $replaceType = $io->getOption('replace');
        $langPrefix = Str::ensureRight($langPrefix, '.');

        $file = Path::realpath($file);
        $outputFile = $this->handleOutput($output, $pkg);
        $langCode = Str::ensureLeft($code, $langPrefix);

        if (!is_file($file)) {
            throw new \RuntimeException('File not exists');
        }

        $startPos = $this->convertToFilePosition($start, $file);
        $endPos = $this->convertToFilePosition($end, $file);

        if ($startPos > $endPos) {
            throw new \RuntimeException('Wrong position, start position should not larger than end position.');
        }

        $length = $endPos - ($startPos);

        $content = file_get_contents($file);
        $text = mb_substr($content, $startPos, $length);

        if (file_exists($outputFile)) {
            $outputContent = (string) file_get_contents($outputFile);
        } else {
            $outputContent = '';
        }

        $outputContent = rtrim($outputContent, "\n");
        $outputContent .= sprintf(
            "\n%s=\"%s\"\n",
            $langCode,
            str_replace('"', '\"', trim($text, '\''))
        );

        Filesystem::mkdir(dirname($outputFile));

        file_put_contents($outputFile, $outputContent);

        // Replace
        if ($replaceType !== false) {
            $replaceText = $this->createReplaceText($langCode, (string) $replaceType);
            $content = static::rangeReplace($content, $replaceText, $startPos, $endPos);
            file_put_contents($file, $content);
        }

        $io->writeln(
            sprintf(
                'Append <info>%s</info>="%s" to file: <comment>%s</comment>',
                $langCode,
                Str::truncate($text, 10, '...'),
                $outputFile
            )
        );

        return 0;
    }

    public function convertToFilePosition(string $pos, string $file): int
    {
        if (str_contains($pos, ':')) {
            [$line, $col] = explode(':', $pos, 2);

            $line = (int) $line;
            $col = (int) $col;

            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            $sum = 0;

            if (count($lines) < $line) {
                throw new \RuntimeException(
                    "Unable to find position: `{$pos}`"
                );
            }

            foreach ($lines as $i => $lineContent) {
                if ($i + 1 === $line) {
                    break;
                }

                $sum += (mb_strlen($lineContent) + 1);
            }

            return $sum + $col - 1;
        }

        return (int) $pos;
    }

    protected function handleOutput(string $output, string $pkg): string
    {
        $output = Path::clean($output, '/');
        $langCode = env('LANG_EXTRACT_DEFAULT') ?: $this->langService->getFallback();

        if ($pkg === '') {
            return Path::normalize(WINDWALKER_RESOURCES . '/languages/' . $langCode . '/' . $output, '/');
        }

        $package = $this->packageRegistry->getPackage($pkg);

        return Path::normalize($package::path('languages/' . $langCode . '/' . $output), '/');
    }

    protected function createReplaceText(string $langCode, string $type): string
    {
        if ($type === 'func') {
            return sprintf('$lang(\'%s\')', $langCode);
        }

        if ($type === 'method') {
            return sprintf('$this->trans(\'%s\')', $langCode);
        }

        if ($type === 'blade') {
            return sprintf('@lang(\'%s\')', $langCode);
        }

        return sprintf($type, $langCode);
    }

    protected static function rangeReplace(
        string $content,
        string $replace,
        int $start,
        int $end
    ): string {
        return mb_substr($content, 0, $start) . $replace . mb_substr($content, $end);
    }
}
