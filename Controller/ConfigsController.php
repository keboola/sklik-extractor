<?php

namespace Keboola\SklikExtractorBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;

class ConfigsController extends \Keboola\ExtractorBundle\Controller\ConfigsController
{
	protected $appName = "ex-sklik";
	protected $columns = array('id');
}