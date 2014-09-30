<?php

namespace Keboola\SklikExtractorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('KeboolaSklikExtractorBundle:Default:index.html.twig', array('name' => $name));
    }

}
