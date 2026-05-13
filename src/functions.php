<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit {
    if (!function_exists('\Lyrasoft\Toolkit\wait_until_truthy')) {
        /**
         * Wait until the callback returns a truthy value.
         *
         * @param \Closure        $callback
         * @param int|float       $seconds  Sleep time between tries in seconds (supports decimals, default 1.0 s)
         * @param int|float|null  $timeout  Maximum total wait time in milliseconds. If null, will wait indefinitely.
         *
         * @return mixed
         */
        function wait_until_truthy(\Closure $callback, int|float $seconds = 1.0, int|float|null $timeout = null): mixed
        {
            $start = microtime(true);

            while (true) {
                $value = $callback();

                if ($value) {
                    return $value;
                }

                // If timeout provided, check elapsed time (in milliseconds)
                if ($timeout !== null) {
                    $elapsed = (microtime(true) - $start) * 1000;
                    if ($elapsed >= $timeout) {
                        return null;
                    }
                }

                // usleep expects microseconds; $seconds may be fractional
                usleep((int) ($seconds * 1000000));
            }
        }
    }
}
