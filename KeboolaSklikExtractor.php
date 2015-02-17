<?php

namespace Keboola\SklikExtractor;

use Keboola\SklikExtractor\DependencyInjection\Extension;

class KeboolaSklikExtractor extends \Symfony\Component\HttpKernel\Bundle\Bundle
{
    public function getContainerExtension()
    {
        return new Extension();
    }
}
