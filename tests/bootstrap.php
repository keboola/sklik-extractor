<?php
/**
 * @package ex-sklik
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

defined('EX_SK_API_URL') || define('EX_SK_API_URL', getenv('EX_SK_API_URL')
    ? getenv('EX_SK_API_URL') : 'https://api.sklik.cz/sandbox/cipisek/RPC2');

defined('EX_SK_USERNAME') || define('EX_SK_USERNAME', getenv('EX_SK_USERNAME')
    ? getenv('EX_SK_USERNAME') : 'username');

defined('EX_SK_PASSWORD') || define('EX_SK_PASSWORD', getenv('EX_SK_PASSWORD')
    ? getenv('EX_SK_PASSWORD') : 'pass');

defined('EX_SK_USER_ID') || define('EX_SK_USER_ID', getenv('EX_SK_USER_ID')
    ? getenv('EX_SK_USER_ID') : 'userId');

require_once __DIR__ . '/../vendor/autoload.php';
