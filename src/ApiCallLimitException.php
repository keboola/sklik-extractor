<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Exception;

class ApiCallLimitException extends Exception
{
    public function getWaitingTimeInSeconds(): int
    {
        $regex = '/Too many requests\. Has to wait (\d+)\[(s|m|h)\]\./';

        if (preg_match($regex, $this->getMessage(), $matches)) {
            $count = (int) $matches[1];
            $unit = $matches[2];

            return $this->convertToSeconds($count, $unit);
        }

        // Defensive fallback: some rate-limit responses report a negative wait time
        // (e.g. "Has to wait -2[s].") which the strict pattern above rejects, previously
        // crashing the whole job. A negative wait means the throttling window has
        // already elapsed, so floor it to zero and let the caller retry immediately -
        // the same wait-then-retry path a normal positive wait time already takes.
        // Zero and positive wait times are unaffected: they match the strict pattern above.
        $signedRegex = '/Too many requests\. Has to wait (-?\d+)\[(s|m|h)\]\./';
        if (preg_match($signedRegex, $this->getMessage(), $matches)) {
            return max(0, $this->convertToSeconds((int) $matches[1], $matches[2]));
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
