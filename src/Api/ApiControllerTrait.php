<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Api;

use Psr\Container\ContainerExceptionInterface;
use Windwalker\Attributes\AttributesAccessor;
use Windwalker\Core\Application\AppContext;
use Windwalker\Core\Router\Exception\RouteNotFoundException;

trait ApiControllerTrait
{
    /**
     * @throws ContainerExceptionInterface
     * @throws \ReflectionException
     */
    public function index(AppContext $app): mixed
    {
        $task = $app->input('task') ?? 'handle';

        if (!method_exists($this, $task)) {
            throw new RouteNotFoundException('Action not found.');
        }

        $callable = $this->$task(...);

        $ajaxAttr = AttributesAccessor::getFirstAttribute($callable, ApiEntry::class);

        if (!$ajaxAttr) {
            throw new RouteNotFoundException(
                WINDWALKER_DEBUG
                    ? "$task() is not an API entry, try add #[ApiEntry] to method"
                    : 'Action not found.'
            );
        }

        return $app->call($callable);
    }
}
