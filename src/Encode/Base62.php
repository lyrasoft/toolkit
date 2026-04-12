<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Encode;

class Base62
{
    public function __construct(
        public string $chars = BaseConvert::BASE62 {
            set {
                static::validateChars($value);
                $this->chars = $value;
            }
        }
    ) {
        //
    }

    public static function encode(string $str, string $chars = BaseConvert::BASE62): string
    {
        return new static($chars)->toBase62($str);
    }

    public static function decode(string $str, string $chars = BaseConvert::BASE62): string
    {
        return new static($chars)->fromBase62($str);
    }

    public function toBase62(string $str): string
    {
        return BaseConvert::encodeString($str, $this->chars);
    }

    public function fromBase62(string $str): string
    {
        return BaseConvert::decodeString($str, $this->chars);
    }

    protected static function validateChars(string $chars): void
    {
        if (strlen($chars) !== 62) {
            throw new \InvalidArgumentException(
                'Base62 alphabet must contain exactly 62 characters.'
            );
        }

        $unique = array_unique(str_split($chars));

        if (count($unique) !== 62) {
            throw new \InvalidArgumentException(
                'Base62 alphabet must not contain duplicate characters.'
            );
        }

        foreach (str_split($chars) as $char) {
            if (ord($char) < 33 || ord($char) > 126) {
                throw new \InvalidArgumentException(
                    'Base62 alphabet must contain only printable ASCII characters.'
                );
            }
        }
    }
}
