<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Api;

use Psr\Container\ContainerExceptionInterface;
use Windwalker\Core\Application\AppContext;
use Windwalker\Core\Router\Exception\RouteNotFoundException;
use Windwalker\Utilities\Attributes\AttributesAccessor;

trait ApiControllerTrait
{
    protected string $entryAttribute = ApiEntry::class;

    protected string $taskParam = 'task';

    /**
     * @throws ContainerExceptionInterface
     * @throws \ReflectionException
     */
    public function index(AppContext $app): mixed
    {
        $task = $app->input($this->taskParam) ?? 'handle';

        if (!method_exists($this, $task)) {
            throw new RouteNotFoundException('Action not found.');
        }

        $callable = $this->$task(...);

        $ajaxAttr = AttributesAccessor::getFirstAttribute($callable, $this->entryAttribute);

        if (!$ajaxAttr) {
            throw new RouteNotFoundException(
                $app->getVerbosity()->isVerbose()
                    ? "$task() is not an API entry, try add #[ApiEntry] to method"
                    : 'Action not found.'
            );
        }

        return $app->call($callable);
    }
}
