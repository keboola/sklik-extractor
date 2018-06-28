<?php

namespace Keboola\SklikExtractor\Tests;

use Keboola\SklikExtractor\Exception;
use Keboola\SklikExtractor\SklikApi;
use PHPUnit\Framework\TestCase;

class SklikApiReportLimitTest extends TestCase
{
    public function testApiGetReportLimitOk()
    {
        $this->assertEquals(100, SklikApi::getReportLimit('2018-01-01', '2018-02-01', 100));
    }

    /**
     * 30 days and limit 100 should give report limit 3
     */
    public function testApiGetReportLimitDailyOk()
    {
        $this->assertEquals(3, SklikApi::getReportLimit('2018-01-01', '2018-01-31', 100, 'daily'));
    }

    /**
     * 30 days is more than 10 in the limit, it should fail
     */
    public function testApiGetReportLimitDailyExceeded()
    {
        $this->expectException(Exception::class);
        SklikApi::getReportLimit('2018-01-01', '2018-01-31', 10, 'daily');
    }

    /**
     * 30 days by week are 4,3 units and limit 100 has 23 such units
     */
    public function testApiGetReportLimitWeeklyOk()
    {
        $this->assertEquals(23, SklikApi::getReportLimit('2018-01-01', '2018-01-31', 100, 'weekly'));
    }

    /**
     * 30 days by month (standardized as 28 days) are 1,07 units and limit 100 has 93 such units
     */
    public function testApiGetReportLimitMonthlyOk()
    {
        $this->assertEquals(93, SklikApi::getReportLimit('2018-01-01', '2018-01-31', 100, 'monthly'));
    }

    /**
     * 30 days by quarters (standardized as 84 days) are 0,35 units which is less than 1, so it should return the original limit 100
     */
    public function testApiGetReportLimitQuarterlyOk()
    {
        $this->assertEquals(100, SklikApi::getReportLimit('2018-01-01', '2018-01-31', 100, 'quarterly'));
    }
}
