<?php
/**
 * @package wr-tableau-server
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\SklikExtractor;

class ExtractorTest extends AbstractTest
{

    public function testExtraction()
    {
        $this->createCampaign(uniqid());

        $e = new Extractor(EX_SK_USERNAME, EX_SK_PASSWORD, sys_get_temp_dir(), 'out.c-main', EX_SK_API_URL);
        $e->run(date('Y-m-d'), date('Y-m-d'));

        $this->assertFileExists(sys_get_temp_dir().'/out.c-main.accounts.csv');
        $fp = file(sys_get_temp_dir().'/out.c-main.accounts.csv');
        $this->assertGreaterThan(1, count($fp));

        $this->assertFileExists(sys_get_temp_dir().'/out.c-main.campaigns.csv');
        $fp = file(sys_get_temp_dir().'/out.c-main.campaigns.csv');
        $this->assertGreaterThan(1, count($fp));

        $this->assertFileExists(sys_get_temp_dir().'/out.c-main.stats.csv');
        $fp = file(sys_get_temp_dir().'/out.c-main.stats.csv');
        $this->assertGreaterThan(1, count($fp));
    }
}
