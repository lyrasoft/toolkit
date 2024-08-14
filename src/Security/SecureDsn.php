<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Security;

use Windwalker\Crypt\Symmetric\CipherInterface;
use Windwalker\Crypt\Symmetric\SodiumCipher;

class SecureDsn
{
    public static CipherInterface $cipher;

    public static function handle(
        string $envName = 'DATABASE_DSN',
        string $encDsnEnvName = 'DATABASE_DSN_ENC',
        string $encKeyEnvName = 'DATABASE_ENC_KEY',
    ): void {
        $_SERVER[$envName] = static::decryptFromEnv(
            $encDsnEnvName,
            $encKeyEnvName
        );
    }

    public static function decryptFromEnv(
        string $encDsnEnvName = 'DATABASE_DSN_ENC',
        string $encKeyEnvName = 'DATABASE_ENC_KEY',
    ): string {
        $encDsn = env($encDsnEnvName);
        $key = env($encKeyEnvName);

        if (!$encDsn || !$key) {
            return '';
        }

        return static::getCipher()->decrypt($encDsn, $key)->get();
    }

    public static function encryptFromEnv(
        string $str,
        string $encKeyEnvName = 'DATABASE_ENC_KEY'
    ): string {
        $key = env($encKeyEnvName);

        return static::encrypt($str, $key);
    }

    public static function encrypt(string $str, #[\SensitiveParameter] string $key): string
    {
        return static::getCipher()->encrypt($str, $key);
    }

    public static function getCipher(): CipherInterface
    {
        return static::$cipher ??= new SodiumCipher();
    }
}
