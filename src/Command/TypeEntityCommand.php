<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Command;

use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Windwalker\Console\CommandInterface;
use Windwalker\Console\CommandWrapper;
use Windwalker\Console\IOInterface;
use Windwalker\Core\Command\CommandPackageResolveTrait;
use Windwalker\Core\Console\ConsoleApplication;
use Windwalker\Core\Database\Command\CommandDatabaseTrait;
use Windwalker\Core\Generator\Builder\EntityMemberBuilder;
use Windwalker\Core\Utilities\ClassFinder;
use Windwalker\Data\Collection;
use Windwalker\Database\Schema\Ddl\Column;
use Windwalker\DI\Attributes\Autowire;
use Windwalker\Filesystem\FileObject;
use Windwalker\Filesystem\Filesystem;
use Windwalker\Filesystem\Path;
use Windwalker\ORM\ORM;
use Windwalker\Utilities\Str;
use Windwalker\Utilities\StrNormalize;

use function Windwalker\collect;
use function Windwalker\fs;

use const Windwalker\Stream\READ_WRITE_CREATE_FROM_BEGIN;

#[CommandWrapper(
    description: 'Generate entity d.ts file.'
)]
class TypeEntityCommand implements CommandInterface, CompletionAwareInterface
{
    use CommandPackageResolveTrait;
    use CommandDatabaseTrait;

    protected IOInterface $io;

    public function __construct(
        #[Autowire] protected ClassFinder $classFinder,
        protected ConsoleApplication $app,
        protected ORM $orm,
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
            'no-def',
            null,
            InputOption::VALUE_NONE,
            'Do not add export line to index.d.ts file.'
        );

        $this->configureDatabaseOptions($command);
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
        $dest = $io->getArgument('dest') ?: WINDWALKER_RESOURCES . '/assets/src/types/entity';
        $dest = fs(Path::realpath($dest));

        if ($ns === '*') {
            $ns = 'App\\Entity\\*';
        }

        if (str_contains($ns, '*')) {
            $ns = Str::removeRight($ns, '\\*');
            $ns = StrNormalize::toClassNamespace($ns);
            $classes = $this->classFinder->findClasses($ns);
            $this->handleClasses($classes, $dest);

            return 0;
        }

        if (!class_exists($ns)) {
            $baseNs = $this->getPackageNamespace($io, 'Entity') ?? 'App\\Entity\\';
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
        $connection = $this->io->getOption('connection');

        $db = $this->databaseManager->get($connection);

        $f = $this->io->getOption('force');
        $noDef = $this->io->getOption('no-def');

        foreach ($classes as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $ref = new \ReflectionClass($class);

            $table = $db->getTable($class);
            $columns = $table->getColumns();

            // $this->io->newLine();
            // $this->io->writeln("Handling: <info>$class</info>");

            $props = [];

            foreach ($columns as $column) {
                $propMeta = $this->createPropItem($column);
                $type = implode(' | ', $propMeta['types']);
                $props[] = "{$propMeta['name']}: {$type}";
            }

            $propsCode = implode(";\n  ", $props) . ';';
            $interface = <<<TS
export interface {$ref->getShortName()} {
  {$propsCode}
  [prop: string]: any;
}

TS;

            $destFile = $dest->appendPath('/' . $ref->getShortName() . '.d.ts');

            if (!$destFile->isFile()) {
                $destFile->write($interface);

                $this->io->writeln("[<info>CREATE</info>]: {$destFile->getPathname()}");
            } elseif ($f) {
                $destFile->write($interface);

                $this->io->writeln("[<fg=cyan>OVERRIDE</>]: {$destFile->getPathname()}");
            } else {
                $this->io->writeln("[<comment>EXISTS</comment>]: {$destFile->getPathname()}");
            }

            // Write index.d.ts
            if (!$noDef) {
                $this->writeDefinitionFile($dest, $ref);
            }
        }
    }

    public function writeDefinitionFile(FileObject $dest, \ReflectionClass $ref): void
    {
        $indexFile = $dest->appendPath('/index.d.ts');
        $indexStream = $indexFile->getStream(READ_WRITE_CREATE_FROM_BEGIN);

        $indexContent = (string) $indexStream;

        if (!str_contains($indexContent, "'./{$ref->getShortName()}")) {
            $indexStream->seek($indexStream->getSize());

            $indexStream->write("\nexport * from './{$ref->getShortName()}';\n");
        }

        $indexStream->close();
    }

    protected function createPropItem(Column $column): array
    {
        $name = StrNormalize::toCamelCase($column->columnName);
        $types = [];

        $dataType = $column->getDataType();
        $hasAny = false;

        if ($dataType === 'int' || $dataType === 'integer') {
            $types = ['number'];
        }

        if ($dataType === 'decimal' || $dataType === 'float') {
            $types = ['number'];
        }

        if (
            $dataType === 'varchar'
            || $dataType === 'char'
            || $dataType === 'text'
            || $dataType === 'binary'
            || $dataType === 'longtext'
        ) {
            $types = ['string'];
        }

        if ($dataType === 'datetime') {
            $types = ['string'];
        }

        if ($dataType === 'tinyint' && (int) ($column->getErratas()['custom_length'] ?? null) === 1) {
            $types = ['boolean'];
        }

        if ($dataType === 'json') {
            $types = ['any'];
            $hasAny = true;
        }

        if ($dataType === 'longtext' && $column->getErratas()['is_json'] ?? false) {
            $types = ['any'];
            $hasAny = true;
        }

        if (!$hasAny && $column->getIsNullable()) {
            $types[] = 'null';
        }

        if ($types === []) {
            $types = ['any'];
        }

        return compact('name', 'types');
    }

    public function completeOptionValues($optionName, CompletionContext $context)
    {
    }

    public function completeArgumentValues($argumentName, CompletionContext $context)
    {
        if ($argumentName === 'ns') {
            $classes = iterator_to_array($this->classFinder->findClasses('App\\Entity\\'));

            return collect($classes)
                ->map(fn(string $className) => (string) Collection::explode('\\', $className)->pop())
                ->dump();
        }

        return null;
    }
}
