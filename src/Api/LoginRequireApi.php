<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Api;

use Lyrasoft\Luna\User\UserService;
use Windwalker\Core\Security\Exception\UnauthorizedException;
use Windwalker\DI\Attributes\AttributeHandler;
use Windwalker\DI\Attributes\ContainerAttributeInterface;

#[\Attribute(\Attribute::TARGET_METHOD)]
class LoginRequireApi implements ContainerAttributeInterface
{
    public static string|\Closure $loginRequireMessage = 'Please login first.';

    public static ?\Closure $loginRequireHandler = null;

    public function __construct(public array $options = [])
    {
    }

    public function __invoke(AttributeHandler $handler): callable
    {
        return function (...$args) use ($handler) {
            $container = $handler->container;
            $userService = $container->get(UserService::class);
            $user = $userService->getCurrentUser();

            if (!$user->isLogin()) {
                if (static::$loginRequireHandler) {
                    $container->call(static::$loginRequireHandler, ['attribute' => $this, 'options' => $this->options]);
                } else {
                    if (static::$loginRequireMessage instanceof \Closure) {
                        $message = $container->call(
                            static::$loginRequireMessage,
                            ['attribute' => $this, 'options' => $this->options]
                        );
                    } else {
                        $message = static::$loginRequireMessage;
                    }

                    throw new UnauthorizedException($message, 401);
                }
            }

            return $handler(...$args);
        };
    }
}
