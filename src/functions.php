<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit {

    use Time\Duration;
    use Time\TimeException;
    use Windwalker\Core\DateTime\Chronos;

    if (!function_exists('\Lyrasoft\Toolkit\wait_until_truthy')) {
        /**
         * Wait until the callback returns a truthy value.
         *
         * @template T of mixed
         *
         * @param  \Closure(int, Duration): T      $callback
         * @param  Duration|string|int|float       $sleep    Sleep time between tries in seconds (supports decimals,
         *                                                   default 1.0 s)
         * @param  Duration|string|int|float|null  $timeout  Maximum total wait time in seconds. If null,
         *                                                   will wait indefinitely.
         *
         * @return T
         * @throws TimeException
         */
        function wait_until_truthy(
            \Closure $callback,
            Duration|string|int|float $sleep = 1.0,
            Duration|string|int|float|null $timeout = null
        ): mixed {
            $toDuration = static function (string|int|float $value): Duration {
                if (is_string($value)) {
                    return Chronos::wrapDuration($value);
                }

                return Duration::fromMicroseconds((int) ($value * 1_000_000));
            };

            if (!$sleep instanceof Duration) {
                $sleep = $toDuration($sleep);
            }

            if ($timeout !== null && !$timeout instanceof Duration) {
                $timeout = $toDuration($timeout);
            }

            $start = microtime(true);
            $loop = 1;

            while (true) {
                $elapsed = Duration::fromMicroseconds((int) ((microtime(true) - $start) * 1_000_000));
                $value = $callback($loop++, $elapsed);

                if ($value) {
                    return $value;
                }

                // If timeout provided, check elapsed time
                if ($timeout !== null) {
                    if (Duration::compare($elapsed, $timeout) >= 0) {
                        return null;
                    }
                }

                $sleepMicroSeconds = ($sleep->seconds * 1_000_000) + ($sleep->nanoseconds / 1_000);

                // usleep expects microseconds; $seconds may be fractional
                usleep((int) $sleepMicroSeconds);
            }
        }
    }
}
