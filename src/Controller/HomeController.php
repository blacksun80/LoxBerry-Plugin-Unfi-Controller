<?php

namespace LoxBerryUnifiPlugin\Controller;

use LoxBerryPoppins\Frontend\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpClient\HttpClient;
use LoxBerryUnifiPlugin\Model\UnifiControllerStatus;


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
        $client = HttpClient::create(['verify_peer'=>false,'verify_host'=>false]);
        
        $response = $client->request('GET', 'https://localhost:8443/status');
        
        //$response->getStatusCode();
        $content = $response->toArray();
        $unifi_data = new UnifiControllerStatus($content['meta']['server_version']);
        return $this->render('pages/home.html.twig', array("unifi_url" => $unifi_url,"unifi_data"=>$unifi_data));
    }

    /**
     * @return Response
     */
    public function logsPage()
    {
        $serverlog = urlencode('REPLACELBPLOGDIR/server.log');
        return $this->render('pages/logs.html.twig', array("serverlog" => $serverlog));
    }
}
