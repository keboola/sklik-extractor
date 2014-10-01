<?php

namespace Keboola\SklikExtractorBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;

class ApiController extends \Syrup\ComponentBundle\Controller\ApiController
{
	private $method;
	private $params;


    public function indexAction($name)
    {
        return $this->render('KeboolaSklikExtractorBundle:Default:index.html.twig', array('name' => $name));
    }

	/**
	 * Common things to do for each request
	 */
	public function preExecute(Request $request)
	{
		parent::preExecute($request);

		set_time_limit(3 * 60 * 60);

		// Get params
		$this->method = $request->getMethod();
		$this->params = in_array($this->method, array('POST', 'PUT'))? $this->getPostJson($request) : $request->query->all();
		array_walk_recursive($this->params, function(&$param) {
			$param = trim($param);
		});
	}

	/**
	 * List all configs
	 *
	 * @Route("/configs")
	 * @Method({"GET"})
	 */
	public function getWritersAction()
	{
		//$this->storageApi->

	}

}
