<?php

declare(strict_types=1);

defined('SKLIK_API_URL') || define('SKLIK_API_URL', getenv('SKLIK_API_URL')
    ? getenv('SKLIK_API_URL') : 'https://api.sklik.cz/jsonApi/drak/');

defined('SKLIK_API_TOKEN') || define('SKLIK_API_TOKEN', getenv('SKLIK_API_TOKEN')
    ? getenv('SKLIK_API_TOKEN') : 'token');

require __DIR__ . '/../../vendor/autoload.php';
