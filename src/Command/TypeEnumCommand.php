<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Command;

use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Windwalker\Console\CommandInterface;
use Windwalker\Console\CommandWrapper;
use Windwalker\Console\IOInterface;
use Windwalker\Core\Command\CommandPackageResolveTrait;
use Windwalker\Core\Console\ConsoleApplication;
use Windwalker\Core\Database\Command\CommandDatabaseTrait;
use Windwalker\Core\Utilities\ClassFinder;
use Windwalker\DI\Attributes\Autowire;
use Windwalker\Filesystem\FileObject;
use Windwalker\Filesystem\Path;
use Windwalker\Utilities\Str;
use Windwalker\Utilities\StrNormalize;

use function Windwalker\collect;
use function Windwalker\fs;
use function Windwalker\piping;

use const Windwalker\Stream\READ_WRITE_CREATE_FROM_BEGIN;

#[CommandWrapper(
    description: 'Generate typescript enum file.'
)]
class TypeEnumCommand implements CommandInterface, CompletionAwareInterface
{
    use CommandPackageResolveTrait;
    use CommandDatabaseTrait;

    protected IOInterface $io;

    public function __construct(
        #[Autowire] protected ClassFinder $classFinder,
        protected ConsoleApplication $app,
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
            'ns',
            InputArgument::REQUIRED,
            'The entity class or namespace.'
        );

        $command->addArgument(
            'dest',
            InputArgument::OPTIONAL,
            'The dest path.'
        );

        $command->addOption(
            'pkg',
            null,
            InputOption::VALUE_REQUIRED,
            'The package name to find namespace.'
        );

        $command->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force override exists files.'
        );

        $command->addOption(
            'no-index',
            null,
            InputOption::VALUE_NONE,
            'Do not add export line to index.d.ts file.'
        );

        $command->addOption(
            'prefix',
            null,
            InputOption::VALUE_REQUIRED,
            'The namespace prefix.',
            'App\\Enum\\'
        );
    }

    protected static function getDefaultDest(): string
    {
        $dest = env('TOOLKIT_TYPE_GEN_DEST');

        if ($dest) {
            return $dest . '/enum';
        }

        return WINDWALKER_RESOURCES . '/assets/src/enum';
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

        $ns = $io->getArgument('ns');
        $dest = $io->getArgument('dest') ?: static::getDefaultDest();
        $dest = fs(Path::realpath($dest));

        $prefix = piping($io->getOption('prefix'))
            ->pipe(fn($v) => str_replace('/', '\\', $v))
            ->pipe(fn($v) => Str::ensureRight($v, '\\'))
            ->value;

        if ($ns === '*') {
            $baseNs = $this->getPackageNamespace($io, 'Enum') ?? $prefix;

            $ns = $baseNs . '\\*';
        }

        if (str_contains($ns, '*')) {
            $ns = Str::removeRight($ns, '\\*');
            $ns = StrNormalize::toClassNamespace($ns);
            $classes = $this->classFinder->findClasses($ns);
            $this->handleClasses($classes, $dest);

            return 0;
        }

        if (!class_exists($ns)) {
            $baseNs = $this->getPackageNamespace($io, 'Enum') ?? $prefix;
            $ns = $baseNs . $ns;
        }

        if (!class_exists($ns)) {
            $classes = $this->classFinder->findClasses($ns);
            $this->handleClasses($classes, $dest);

            return 0;
        }

        $classes = [$ns];

        $this->handleClasses($classes, $dest);

        return 0;
    }

    protected function handleClasses(iterable $classes, FileObject $dest): void
    {
        $f = $this->io->getOption('force');
        $noIndex = $this->io->getOption('no-index');

        foreach ($classes as $class) {
            $ref = new \ReflectionEnum($class);

            if (!$ref->isBacked()) {
                $this->io->style()->warning("Enum: {$ref->getShortName()} is not backed.");
                continue;
            }

            $cases = $ref->getCases();

            $type = $ref->getBackingType();

            if (!$type) {
                $this->io->style()->warning("Enum: {$ref->getShortName()} has no type.");
                continue;
            }

            $typeName = $type->getName();
            $caseCodes = [];

            foreach ($cases as $case) {
                $backingValue = $case->getBackingValue();

                if ($typeName === 'string') {
                    $backingValue = "'$backingValue'";
                }

                $caseCodes[] = "{$case->getName()} = {$backingValue}";
            }

            $caseCode = implode(",\n  ", $caseCodes);

            $enumCode = <<<TS
export enum {$ref->getShortName()} {
  $caseCode
}
TS;

            $destFile = $dest->appendPath('/' . $ref->getShortName() . '.ts');

            if (!$destFile->isFile()) {
                $destFile->write($enumCode);

                $this->io->writeln("[<info>CREATE</info>]: {$destFile->getPathname()}");
            } elseif ($f) {
                $destFile->write($enumCode);

                $this->io->writeln("[<fg=cyan>OVERRIDE</>]: {$destFile->getPathname()}");
            } else {
                $this->io->writeln("[<comment>EXISTS</comment>]: {$destFile->getPathname()}");
            }

            // Write index.ts
            if (!$noIndex) {
                $this->writeIndexFile($dest, $ref);
            }
        }
    }

    protected function writeIndexFile(FileObject $dest, \ReflectionEnum $ref): void
    {
        $indexFile = $dest->appendPath('/index.ts');
        $indexStream = $indexFile->getStream(READ_WRITE_CREATE_FROM_BEGIN);

        $indexContent = (string) $indexStream;

        if (!str_contains($indexContent, "'./{$ref->getShortName()}")) {
            $indexStream->seek($indexStream->getSize());

            $indexStream->write("export * from './{$ref->getShortName()}';\n");
        }

        $indexStream->close();
    }

    public function completeOptionValues($optionName, CompletionContext $context)
    {
    }

    public function completeArgumentValues($argumentName, CompletionContext $context)
    {
        $input = CommandWrapper::getInputForCompletion($this, $context);

        $prefix = 'App\\Enum\\';

        if ($p = $input->getOption('prefix')) {
            $prefix = Str::ensureRight(str_replace('/', '\\', $p), '\\');
        }

        if ($argumentName === 'ns') {
            $classes = iterator_to_array($this->classFinder->findClasses($prefix));

            return collect($classes)
                ->map(fn(string $className) => Str::removeLeft($className, $prefix))
                ->dump();
        }

        return null;
    }
}
