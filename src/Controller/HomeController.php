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
        $unifi_url = "https://" . $this->getRequest()->getHost() . ":8443/";
        return $this->render('pages/home.html.twig', array("unifi_url" => $unifi_url));
    }

    /**
     * @return Response
     */
    public function logsPage()
    {
        return $this->render('pages/logs.html.twig');
    }
}
