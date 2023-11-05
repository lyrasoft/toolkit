<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Encode;

use Brick\Math\BigInteger;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;

/**
 * The BaseConvert class.
 */
class BaseConvert
{
    public const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public const BASE32HEX = '0123456789ABCDEFGHIJKLMNOPQRSTUV';

    public const BASE36 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public const BASE58 = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public const BASE64SAFE = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_';

    public const BASE62 = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    protected BigInteger $offset;

    public function __construct(protected $seed = self::BASE36)
    {
        static::checkLib();

        $this->setOffset(0);
    }

    public static function encode(BigNumber|int|string $rawNumber, string $seed = self::BASE36): string
    {
        return (new static($seed))->from10($rawNumber);
    }

    public static function decode(string $number, string $seed = self::BASE36): BigInteger
    {
        return (new static($seed))->to10($number);
    }

    public function from10(BigNumber|int|string $rawNumber): string
    {
        $from = BigInteger::of($rawNumber);
        $from = $from->plus($this->getOffset());

        $length = strlen($this->seed);
        $r = $from->mod($length)->toInt();
        $res = $this->seed[$r];
        $q = $from->dividedBy($length, RoundingMode::FLOOR);

        while (!$q->isEqualTo(0)) {
            $r = $q->mod($length)->toInt();
            $q = $q->dividedBy($length, RoundingMode::FLOOR);
            $res = $this->seed[$r] . $res;
        }

        return $res;
    }

    public function to10(string $number): BigInteger
    {
        $limit = strlen($number);
        $length = strlen($this->seed);
        $res = BigInteger::of(strpos($this->seed, $number[0] ?? ''));

        for ($i = 1; $i < $limit; $i++) {
            $res = $res->multipliedBy($length)->plus(strpos($this->seed, $number[$i]));
        }

        return $res->minus($this->getOffset());
    }

    public function useBase32Hex(): static
    {
        return $this->setSeed(static::BASE32HEX);
    }

    public function useBase58(): static
    {
        return $this->setSeed(static::BASE58);
    }

    public function useBase64Safe(): static
    {
        return $this->setSeed(static::BASE64SAFE);
    }

    public function useBase62(): static
    {
        return $this->setSeed(static::BASE62);
    }

    public function useBase32(): static
    {
        return $this->setSeed(static::BASE32);
    }

    public function useBase36(): static
    {
        return $this->setSeed(static::BASE36);
    }

    /**
     * @return string
     */
    public function getSeed(): string
    {
        return $this->seed;
    }

    /**
     * @param  string  $seed
     *
     * @return  static  Return self to support chaining.
     */
    public function setSeed(string $seed): static
    {
        $this->seed = $seed;

        return $this;
    }

    protected static function checkLib(): void
    {
        if (!class_exists(BigNumber::class)) {
            throw new \DomainException(
                'Please install `brick/math` first.'
            );
        }
    }

    /**
     * @return BigInteger
     */
    public function getOffset(): BigInteger
    {
        return $this->offset;
    }

    /**
     * @param  BigInteger|string|int  $offset
     *
     * @return  static  Return self to support chaining.
     */
    public function setOffset(BigInteger|string|int $offset): static
    {
        $this->offset = BigInteger::of($offset);

        return $this;
    }
}
