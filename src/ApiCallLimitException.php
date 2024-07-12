<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Exception;

class ApiCallLimitException extends \Exception
{
    public function getWaitingTimeInSeconds(): int
    {
        $regex = '/Too many requests\. Has to wait (\d+)\[(s|m|h)\]\./';

        if (preg_match($regex, $this->getMessage(), $matches)) {
            $count = (int) $matches[1];
            $unit = $matches[2];

            return $this->convertToSeconds($count, $unit);
        }

        throw new Exception('Cannot parse waiting time from message: ' . $this->getMessage());
    }

    private function convertToSeconds(int $count, string $unit): int
    {
        switch ($unit) {
            case 's':
                return $count;
            case 'm':
                return $count * 60;
            case 'h':
                return $count * 60 * 60;
            default:
                throw new Exception("Unknown unit: $unit");
        }
    }
}
