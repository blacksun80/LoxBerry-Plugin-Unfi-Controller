<?php

namespace LoxBerryUnifiPlugin\Controller;

use LoxBerryPoppins\Storage\SettingsStorage;
use LoxBerryPoppins\Frontend\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpClient\HttpClient;
use LoxBerryUnifiPlugin\Model\UnifiControllerStatus;
use LoxBerryUnifiPlugin\Service\DockerHubService;
use LoxBerryUnifiPlugin\Service\SystemService;

/**
 * Class DemoController.
 */
class HomeController extends AbstractController
{
    const SERVICE_NAME = "unifi";

    /** @var SettingsStorage */
    private $settings;

    /** @var DockerHubService */
    private $dockerHubService;

    /**
     * @var SystemService
     */
    private $sysService;

    /**
     * SenseboxDataGetter constructor.
     * @param SettingsStorage $settings
     */
    public function __construct(SettingsStorage $settings, DockerHubService $dockerHubService, SystemService $sysService)
    {
        $this->settings = $settings;
        $this->dockerHubService = $dockerHubService;
        $this->sysService = $sysService;
    }

    /**
     * @return Response
     */
    public function indexPage()
    {
        $unifi_url = "https://" . $this->getRequest()->getHost() . ":8443/";
        $client = HttpClient::create(['verify_peer' => false, 'verify_host' => false]);


        $response = $client->request('GET', 'https://lb.int.lumetsnet.at:8443/status');
        $content = $response->toArray();
        $unifi_data = new UnifiControllerStatus($content['meta']['server_version'], $this->getContainerVersion(), $this->sysService->getServiceStatus(self::SERVICE_NAME));


        $versions = $this->dockerHubService->getVersions();
        return $this->render('pages/home.html.twig', array("unifi_url" => $unifi_url, "unifi_data" => $unifi_data, "versions" => $versions));
    }

    private function getContainerVersion()
    {
        return $this->sysService->getContainerVersionFromEnv();
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
