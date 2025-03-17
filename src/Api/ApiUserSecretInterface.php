<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Api;

interface ApiUserSecretInterface
{
    public function getRawSecret(): string;
}
