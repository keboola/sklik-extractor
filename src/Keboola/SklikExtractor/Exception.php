<?php
/**
 * @package ex-sklik
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\SklikExtractor;

class Exception extends \Exception
{
    public static function apiError($message, $method, $args = [], $result = null)
    {
        return new static(json_encode([
            'error' => $message,
            'method' => $method,
            'args' => $args,
            'result' => $result
        ]));
    }
}
