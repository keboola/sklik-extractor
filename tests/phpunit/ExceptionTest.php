<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor\Tests;

use Keboola\SklikExtractor\Exception;
use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    public function testException(): void
    {
        $e = Exception::apiError('message', 'method', ['a' => 1], 400, ['b' => 2]);
        $message = json_decode($e->getMessage(), true);
        $this->assertIsArray($message);
        $this->assertNotFalse($message);
        $this->assertArrayHasKey('error', $message);
        $this->assertArrayHasKey('method', $message);
        $this->assertArrayHasKey('args', $message);
        $this->assertArrayHasKey('response', $message);
        $this->assertEquals('message', $message['error']);
        $this->assertEquals('method', $message['method']);
        $this->assertArrayHasKey('a', $message['args']);
        $this->assertEquals(1, $message['args']['a']);
        $this->assertArrayHasKey('b', $message['response']);
        $this->assertEquals(2, $message['response']['b']);
    }

    /**
     * @dataProvider filterParamsForLogProvider
     * @param array<string|int, int|array<string, string>> $args
     * @param array<string|int, int|array<string, string>> $expected
     */
    public function testFilterParamsForLog(array $args, string $method, array $expected): void
    {
        $this->assertEquals($expected, Exception::filterParamsForLog($args, $method));
    }

    /** @return array<string, array<int, array<int|string, array<string, string>|int|string>|string>> */
    public function filterParamsForLogProvider(): array
    {
        return [
            'login method' => [
                ['a' => 1],
                'client.loginByToken',
                ['--omitted--'],
            ],
            'session argument' => [
                [['session' => 'session']],
                'method.test',
                [['session' => '--omitted--']],
            ],
            'normal argument' => [
                ['a' => 1],
                'method.test',
                ['a' => 1],
            ],
        ];
    }
}
