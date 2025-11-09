<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit {
    if (function_exists('\Lyrasoft\Toolkit\wait_util_truthy')) {
        function wait_util_truthy(\Closure $callback, int|float $wait = 1000, ?int $maxTimes = null): mixed
        {
            $i = 0;

            while (true) {
                $value = $callback();

                if ($value) {
                    return $value;
                }

                $i++;

                if ($maxTimes !== null && $i >= $maxTimes) {
                    return null;
                }

                // usleep
                usleep((int) ($wait * 1000));
            }
        }
    }
}
