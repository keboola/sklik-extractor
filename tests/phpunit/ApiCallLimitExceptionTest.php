<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor\Tests;

use Keboola\SklikExtractor\ApiCallLimitException;
use PHPUnit\Framework\TestCase;

class ApiCallLimitExceptionTest extends TestCase
{
    public function testWaitingTimeInSeconds(): void
    {
        $exception = new ApiCallLimitException('Too many requests. Has to wait 5[s]. Limit exceeded.');
        $this->assertEquals(5, $exception->getWaitingTimeInSeconds());

        $exception = new ApiCallLimitException('Too many requests. Has to wait 5[m].');
        $this->assertEquals(300, $exception->getWaitingTimeInSeconds());

        $exception = new ApiCallLimitException('Too many requests. Has to wait 5[h].');
        $this->assertEquals(18000, $exception->getWaitingTimeInSeconds());
    }

    public function testInvalidMessage(): void
    {
        $this->expectExceptionMessage('Cannot parse waiting time from message: Invalid message');
        $exception = new ApiCallLimitException('Invalid message');
        $exception->getWaitingTimeInSeconds();
    }
}
