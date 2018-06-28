<?php

declare(strict_types=1);

defined('SKLIK_API_URL') || define('SKLIK_API_URL', getenv('SKLIK_API_URL')
    ? getenv('SKLIK_API_URL') : 'https://api.sklik.cz/jsonApi/drak/');

defined('SKLIK_API_TOKEN') || define('SKLIK_API_TOKEN', getenv('SKLIK_API_TOKEN')
    ? getenv('SKLIK_API_TOKEN') : 'token');

defined('SKLIK_DATE_FROM') || define('SKLIK_DATE_FROM', getenv('SKLIK_DATE_FROM')
    ? getenv('SKLIK_DATE_FROM') : '2018-01-01');

defined('SKLIK_DATE_TO') || define('SKLIK_DATE_TO', getenv('SKLIK_DATE_TO')
    ? getenv('SKLIK_DATE_TO') : '2018-06-01');

require __DIR__ . '/../../vendor/autoload.php';
