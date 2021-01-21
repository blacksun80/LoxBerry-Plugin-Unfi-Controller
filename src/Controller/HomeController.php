<?php

namespace LoxBerryUnifiPlugin\Controller;

use Exception;
use LoxBerryPoppins\Storage\SettingsStorage;
use LoxBerryPoppins\Frontend\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpClient\HttpClient;
use LoxBerryUnifiPlugin\Model\UnifiControllerStatus;
use LoxBerryUnifiPlugin\Service\DockerHubService;
use LoxBerryUnifiPlugin\Service\SystemService;
use LoxBerryPoppins\Frontend\Navigation\UrlBuilder;


/**
 * Class DemoController.
 */
class HomeController extends AbstractController
{
    const SERVICE_NAME = "unifi";

    /** @var DockerHubService */
    private $dockerHubService;

    /**
     * @var SystemService
     */
    private $sysService;

    /** @var UrlBuilder */
    private $urlBuilder;


    /**
     * HomeController constructor
     */
    public function __construct(
        DockerHubService $dockerHubService,
        SystemService $sysService,
        UrlBuilder $urlBuilder
    ) {
        $this->dockerHubService = $dockerHubService;
        $this->sysService = $sysService;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @return Response
     */
    public function indexPage(): Response
    {
        $unifi_url = "https://" . $this->getRequest()->getHost() . ":8443";

        $unifi_data = new UnifiControllerStatus(
            $this->getUnifiVersion($unifi_url),
            $this->getContainerVersion(),
            $this->sysService->getServiceStatus(self::SERVICE_NAME)
        );


        $versions = $this->dockerHubService->getVersions();
        return $this->render('pages/home.html.twig', array(
            "unifi_url" => $unifi_url,
            "unifi_data" => $unifi_data, "versions" => $versions
        ));
    }

    /**
     * Changes the container version to the given new version
     * and restarts the service
     */
    public function upgrade(): Response
    {
        $request = $this->getRequest();
        if (!$request->request->has("version")) {
            return $this->redirect($this->urlBuilder->getAdminUrl('home'));
        }

        $version = $request->request->get("version");
        $this->sysService->setContainerVersion($version);
        $this->sysService->restartService(self::SERVICE_NAME);
        return $this->redirect($this->urlBuilder->getAdminUrl('home'));
    }

    private function getContainerVersion(): string
    {
        return $this->sysService->getContainerVersionFromEnv();
    }

    private function getUnifiVersion($unifiUrl): string
    {
        try {
            $client = HttpClient::create(['verify_peer' => false, 'verify_host' => false]);
            $response = $client->request('GET', "$unifiUrl/status");
            $content = $response->toArray();
            return $content['meta']['server_version'];
        } catch (Exception $e) {
            return "starting";
        }
    }

    /**
     * @return Response
     */
    public function logsPage(): Response
    {
        $serverlog = urlencode('REPLACELBPLOGDIR/server.log');
        return $this->render('pages/logs.html.twig', array("serverlog" => $serverlog));
    }
}
