<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Keboola\Component\UserException;

class Exception extends UserException
{
    public static function apiError(
        string $message,
        string $method,
        array $args = [],
        ?int $statusCode = null,
        ?array $response = null
    ): Exception {
        return new static(json_encode([
            'error' => $message,
            'method' => $method,
            'args' => self::filterParamsForLog($args),
            'statusCode' => $statusCode,
            'response' => $response,
        ]));
    }

    public static function filterParamsForLog(array $args): array
    {
        if (isset($args[0]['session'])) {
            $args[0]['session'] = '--omitted--';
        }
        return $args;
    }
}
