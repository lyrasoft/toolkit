<?php

/**
 * Part of toolstool project.
 *
 * @copyright  Copyright (C) 2022 __ORGANIZATION__.
 * @license    MIT
 */

declare(strict_types=1);

namespace Windwalker\Data;

use Windwalker\ORM\ORM;
use Windwalker\Utilities\Arr;
use Windwalker\Utilities\Contract\DumpableInterface;
use Windwalker\Utilities\TypeCast;

/**
 * The DataTransferObject class.
 *
 * @template T
 */
abstract class AbstractDTO implements \JsonSerializable, DumpableInterface
{
    protected object $data;

    protected array $keepFields = [];

    public static function wrap(object $data, ?array $keepFields = null): static
    {
        if ($data instanceof self) {
            return $data;
        }

        return new static($data, $keepFields);
    }

    /**
     * @param  object      $item
     * @param  array|null  $keepFields
     */
    public function __construct(object $item, ?array $keepFields = null)
    {
        $this->data = $item;

        if ($keepFields !== null) {
            $this->keepFields = $keepFields;
        }

        $this->configure($item);
    }

    abstract protected function configure(object $data): void;

    /**
     * @return object|T
     */
    public function getData(): object
    {
        return $this->data;
    }

    /**
     * @param  object  $data
     *
     * @return  static  Return self to support chaining.
     */
    public function setData(object $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function __call(string $name, array $arguments)
    {
        return $this->data->$name(...$arguments);
    }

    public function __get(string $name): mixed
    {
        return $this->data->$name;
    }

    public function __set(string $name, $value): void
    {
        $this->data->$name = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }

    public function getKeepFields(): array
    {
        return $this->keepFields;
    }

    public function addKeepFields(string ...$fields): static
    {
        $this->keepFields = array_merge(
            $this->keepFields,
            $fields
        );

        return $this;
    }

    /**
     * @param  array  $keepFields
     *
     * @return  static  Return self to support chaining.
     */
    public function setKeepFields(array $keepFields): static
    {
        $this->keepFields = $keepFields;

        return $this;
    }

    public function dump(bool $recursive = false, bool $onlyDumpable = false): array
    {
        return TypeCast::toArray($this->data, $recursive, $onlyDumpable);
    }

    public function jsonSerialize(): array
    {
        return Arr::only(
            $this->dump(),
            $this->getKeepFields()
        );
    }

    public function extract(ORM $orm): array
    {
        return $orm->extractEntity($this->data);
    }
}
