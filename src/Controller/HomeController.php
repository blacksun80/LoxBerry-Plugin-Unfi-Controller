<?php

namespace LoxBerryPoppinsPlugin\Controller;

use LoxBerryPoppins\Frontend\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class DemoController.
 */
class HomeController extends AbstractController
{
    /**
     * @return Response
     */
    public function indexPage()
    {
        return $this->render('pages/home.html.twig');
    }

    /**
     * @return Response
     */
    public function logsPage()
    {
        return $this->render('pages/logs.html.twig');
    }
}
