<?php
declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Keboola\Component\UserException;

class Exception extends UserException
{
    public static function apiError($message, $method, $params = [], $statusCode, $response = null)
    {
        return new static(json_encode([
            'error' => $message,
            'method' => $method,
            'params' => ($method == 'client.login') ? ['--omitted--'] : self::filterParamsForLog($params),
            'statusCode' => $statusCode,
            'response' => $response
        ]));
    }

    public static function filterParamsForLog($params)
    {
        if (isset($params['user']['session'])) {
            $params['user']['session'] = '--omitted--';
        }
        return $params;
    }
}
