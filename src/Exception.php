<?php
declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Keboola\Component\UserException;

class Exception extends UserException
{
    public static function apiError($message, $method, $args = [], $statusCode, $response = null)
    {
        return new static(json_encode([
            'error' => $message,
            'method' => $method,
            'args' => ($method == 'client.login') ? ['--omitted--'] : self::filterParamsForLog($args),
            'statusCode' => $statusCode,
            'response' => $response
        ]));
    }

    public static function filterParamsForLog($args)
    {
        if (isset($args[0]['session'])) {
            $args[0]['session'] = '--omitted--';
        }
        return $args;
    }
}
