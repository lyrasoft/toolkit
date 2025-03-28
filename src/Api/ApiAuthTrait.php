<?php

declare(strict_types=1);

namespace Lyrasoft\Toolkit\Api;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use JetBrains\PhpStorm\ArrayShape;
use Lyrasoft\Luna\Entity\User;
use Psr\Http\Message\ServerRequestInterface;
use Windwalker\Core\Security\Exception\UnauthorizedException;
use Windwalker\Crypt\SecretToolkit;

use function Windwalker\chronos;
use function Windwalker\uid;

trait ApiAuthTrait
{
    abstract public static function getIssuer(): string;

    public static function getJWTAlg(): string
    {
        return 'HS512';
    }

    public static function getBearerTokenFromRequest(ServerRequestInterface $request): ?string
    {
        $auth = (string) $request->getHeaderLine('authorization');

        return static::extractBearerTokenFromHeader($auth);
    }

    public static function extractBearerTokenFromHeader(string $headerLine): ?string
    {
        sscanf($headerLine, 'Bearer %s', $token);

        return $token;
    }

    #[ArrayShape(['string', 'string'])]
    public static function extractBasicAuth(string $headerLine, bool $allowEmptyPassword = false): array
    {
        sscanf($headerLine, 'Basic %s', $auth);

        $auth = base64_decode($auth);
        $auth = explode(':', $auth, 2) + ['', ''];

        if ((!isset($auth[1]) || $auth[1] === '') && !$allowEmptyPassword) {
            throw new UnauthorizedException('No password', 400);
        }

        return $auth;
    }

    /**
     * @param  string                     $iss
     * @param  ApiUserSecretInterface     $user
     * @param  \DateTimeInterface|string  $expires
     * @param  \Closure                   $handler
     *
     * @return  string
     *
     * @throws \DateMalformedStringException
     */
    public function createAuthJWT(
        ApiUserSecretInterface $user,
        \DateTimeInterface|string $expires,
        \Closure $handler
    ): string {
        $time = chronos();
        $exp = $time->modify($expires);

        $data = [
            'iat' => $time->toUnix(),
            'jti' => uid(),
            'iss' => static::getIssuer(),
            'nbf' => $time->toUnix(),
            'exp' => $exp->toUnix(),
            'data' => null,
        ];

        $data = $handler($data, $user);

        return JWT::encode(
            $data,
            SecretToolkit::decodeIfHasPrefix($user->getRawSecret()),
            static::getJWTAlg(),
        );
    }

    public function decodeAuthJWT(string $token, ?User &$user, \Closure $userReader): ?object
    {
        $payload = static::extractJWT($token);

        /** @var ApiUserSecretInterface $user */
        $user = $userReader($payload);

        if (!$user) {
            return null;
        }

        $payload = JWT::decode(
            $token,
            new Key(
                SecretToolkit::decodeIfHasPrefix($user->getRawSecret()),
                static::getJWTAlg(),
            )
        );

        if ($payload->iss !== static::getIssuer()) {
            throw new UnauthorizedException('Invalid issuer', 400);
        }

        return $payload;
    }

    public static function extractJWT(string $token): object
    {
        $parts = explode('.', $token);

        if (!isset($parts[1])) {
            throw new \InvalidArgumentException('JWT format wrong.', 400);
        }

        return JWT::jsonDecode(JWT::urlsafeB64Decode($parts[1]));
    }
}
