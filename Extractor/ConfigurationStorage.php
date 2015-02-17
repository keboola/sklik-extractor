<?php
/**
 * @package sklik-extractor
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\SklikExtractor\Extractor;

class ConfigurationStorage extends \Keboola\SklikExtractor\Service\ConfigurationStorage
{

    public function getRequiredBucketAttributes()
    {
        return ['username', 'password'];
    }

    public function getRequiredTableColumns()
    {
        return [];
    }
}
