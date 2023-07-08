<?php

/**
 * Part of earth project.
 *
 * @copyright  Copyright (C) 2023 __ORGANIZATION__.
 * @license    __LICENSE__
 */

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Middleware;

use Lyrasoft\Luna\User\Password;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Windwalker\Core\Asset\AssetService;
use Windwalker\Stream\Stream;
use Windwalker\Utilities\Str;

use const Windwalker\Stream\READ_WRITE_FROM_BEGIN;

/**
 * The CspNonceMiddleware class.
 */
class CspNonceMiddleware implements MiddlewareInterface
{
    protected string $nonce = '';

    public const FRAME_SRC = 1 << 0;

    public const IMG_SRC = 1 << 1;

    public const MEDIA_SRC = 1 << 2;

    public const OBJECT_SRC = 1 << 3;

    public const SCRIPT_SRC = 1 << 4;

    public const STYLE_SRC = 1 << 5;

    public function __construct(
        protected AssetService $asset,
        protected bool $enabled = true,
        protected int $flags = self::SCRIPT_SRC | self::STYLE_SRC | self::IMG_SRC | self::FRAME_SRC
    ) {
        //
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->nonce = $nonce = Password::genRandomPassword();

        $response = $handler->handle($request);

        if ($this->enabled) {
            $newBody = (string) $response->getBody();

            $tags = [];
            $csp = [];

            if ($this->flags & static::FRAME_SRC) {
                $tags[] = 'iframe';
                $csp[] = "frame-src 'nonce-$nonce'";
            }

            if ($this->flags & static::IMG_SRC) {
                $tags[] = 'img';
                $csp[] = "img-src 'nonce-$nonce' * data:";
            }

            if ($this->flags & static::MEDIA_SRC) {
                $tags[] = 'video';
                $tags[] = 'audio';
                $tags[] = 'track';
                $csp[] = "media-src 'nonce-$nonce'";
            }

            if ($this->flags & static::OBJECT_SRC) {
                $tags[] = 'object';
                $tags[] = 'embed';
                $csp[] = "iframe-src 'nonce-$nonce'";
            }

            if ($this->flags & static::SCRIPT_SRC) {
                $tags[] = 'script';
                $csp[] = "script-src-elem 'nonce-$nonce' 'strict-dynamic' 'unsafe-inline'";
                $csp[] = "script-src-attr 'unsafe-inline'";

                $newBody = self::addNonceToTagWithEvents($newBody, $nonce);
            }

            if ($this->flags & static::STYLE_SRC) {
                $tags[] = 'style';
                $tags[] = 'link';
                $csp[] = "style-src-elem 'nonce-$nonce' 'strict-dynamic' 'unsafe-inline'";
                $csp[] = "style-src-attr 'unsafe-inline'";

                $newBody = self::addNonceToTagWithStyle($newBody, $nonce);
            }

            $newBody = static::addNonceToTags($newBody, $nonce, $tags);

            $stream = new Stream('php://memory', READ_WRITE_FROM_BEGIN);
            $stream->write($newBody);

            $response = $response->withBody($stream);

            if ($csp !== []) {
                $response = $response->withHeader('Content-Security-Policy', $csp);
            }
        }

        return $response;
    }

    public static function addNonceToTags(string $body, string $nonce, array $tags): string
    {
        foreach ($tags as $tag) {
            $body = preg_replace_callback(
                "#(<{$tag}[^>]*)(>.*?</{$tag}>|>)#",
                static function ($matches) use ($nonce) {
                    [$all, $start, $end] = $matches;

                    if (str_ends_with($start, '/')) {
                        $start = Str::removeRight($start, '/');
                        $end = ' /' . $end;
                    }

                    if (!str_contains($start, 'nonce="')) {
                        return $start . ' nonce="' . $nonce . '"' . $end;
                    }

                    return $all;
                },
                $body
            );
        }

        return $body;
    }

    public static function addNonceToTagWithEvents(string $body, string $nonce): string
    {
        $body = preg_replace_callback(
            '/(<\w+\s[^>]*\s+on\w+="[^"]*"[^>]*)>/i',
            static function ($matches) use ($nonce) {
                [$all, $start] = $matches;
                $end = '>';

                if (str_ends_with($start, '/')) {
                    $start = Str::removeRight($start, '/');
                    $end = ' /' . $end;
                }

                if (!str_contains($start, 'nonce="')) {
                    return $start . ' nonce="' . $nonce . '"' . $end;
                }

                return $all;
            },
            $body
        );

        return $body;
    }

    public static function addNonceToTagWithStyle(string $body, string $nonce): string
    {
        $body = preg_replace_callback(
            '/(<\w+\s[^>]*\s+style="[^"]*"[^>]*)>/i',
            static function ($matches) use ($nonce) {
                [$all, $start] = $matches;
                $end = '>';

                if (str_ends_with($start, '/')) {
                    $start = Str::removeRight($start, '/');
                    $end = ' /' . $end;
                }

                if (!str_contains($start, 'nonce="')) {
                    return $start . ' nonce="' . $nonce . '"' . $end;
                }

                return $all;
            },
            $body
        );

        return $body;
    }
}
