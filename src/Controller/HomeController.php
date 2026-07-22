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
            "unifi_data" => $unifi_data,
            "versions" => $versions,
            "host_architecture" => $this->dockerHubService->getHostArchitecture(),
            "versions_unavailable_for_architecture" => $this->dockerHubService->hasVersionsUnavailableForArchitecture()
        ));
    }

    /**
     * Changes the container version to the given new version
     * and restarts the service
     */
    public function upgrade(): Response
    {
        $request = $this->getRequest();
        $version = trim((string) $request->request->get("version"));
        if ($version === "") {
            return $this->redirect($this->urlBuilder->getAdminUrl('home'));
        }

        $this->sysService->setContainerVersion($version);
        $this->sysService->restartService(self::SERVICE_NAME);
        return $this->redirect($this->urlBuilder->getAdminUrl('home'));
    }

    /**
     * Force-removes both containers (data volumes are untouched) and restarts the
     * service so docker compose recreates them cleanly. Use when the service is
     * stuck (e.g. "Created" but never started, or referencing a network that no
     * longer exists).
     */
    public function reset(): Response
    {
        $this->sysService->resetContainers();
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
            $response = $client->request('GET', "$unifiUrl/status",['timeout' => 3]);
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

    /**
     * Shows the live docker container logs (useful during the "starting" phase).
     *
     * @return Response
     */
    public function containerLogsPage(): Response
    {
        return $this->render('pages/containerlogs.html.twig', array(
            "logs" => $this->sysService->getContainerLogs(400),
            "serverlog" => $this->sysService->tailFile("REPLACELBPLOGDIR/server.log", 300)
        ));
    }

    /**
     * Lightweight status fragment for the home page to poll via AJAX, so the
     * status/version fields update without a full page reload (which would
     * re-query the Docker Hub version list).
     *
     * @return Response
     */
    public function statusFragment(): Response
    {
        $unifi_url = "https://" . $this->getRequest()->getHost() . ":8443";
        $unifi_data = new UnifiControllerStatus(
            $this->getUnifiVersion($unifi_url),
            $this->getContainerVersion(),
            $this->sysService->getServiceStatus(self::SERVICE_NAME)
        );
        return $this->render('pages/status.html.twig', array("unifi_data" => $unifi_data));
    }

    /**
     * Aggregated diagnostics (systemd status, container states, disk space, both
     * containers' logs, server.log) in one copy-paste-friendly page - for cases
     * where the container logs alone do not explain why something failed to start.
     *
     * @return Response
     */
    public function diagnosticsPage(): Response
    {
        return $this->render('pages/diagnostics.html.twig', array(
            "diagnostics" => $this->sysService->getDiagnostics()
        ));
    }
}
