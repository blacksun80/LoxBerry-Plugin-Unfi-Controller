<?PHP

namespace LoxBerryUnifiPlugin\Service;

use LoxBerry\System\Plugin\PluginPathProvider;
use LoxBerry\System\Paths;

class SystemService
{
    /** Name of the docker container (see container_name in docker-compose.yml) */
    const CONTAINER_NAME = "unifi-controller";

    /** @var PathProvider */
    private $pathProvider;

    /**
     * SystemService constructor.
     *
     * @param PathProvider $pathProvider
     * @param $pluginName
     */
    public function __construct(PluginPathProvider $pathProvider, $pluginName)
    {
        $this->pathProvider = $pathProvider;
        $this->pathProvider->setPluginName($pluginName);
    }

    /***
     * Gets the status for the given service name
     */
    public function getServiceStatus($serviceName)
    {
        return shell_exec("systemctl show --value $serviceName --property ActiveState");
    }

    public function restartService($serviceName)
    {
        return shell_exec("sudo systemctl restart $serviceName &");
    }

    /**
     * Returns the last $lines lines of the docker container's log output
     * (stdout/stderr), including the early startup phase that the UniFi
     * server.log does not yet cover.
     */
    public function getContainerLogs($lines = 400)
    {
        $lines = (int) $lines;
        $output = shell_exec("docker logs --tail $lines --timestamps " . self::CONTAINER_NAME . " 2>&1");
        return $output === null ? "" : $output;
    }

    public function getContainerVersionFromEnv()
    {
        $filename = $this->getConfigFolder() . "/env";
        $contents =  file_get_contents($filename);
        return trim(explode('=', $contents)[1]);
    }

    public function setContainerVersion($version)
    {
        $filename = $this->getConfigFolder() . "/env";
        // Trailing newline is required: docker compose v2 ignores the last line
        // of a .env file if it is not newline-terminated, leaving VERSION unset.
        file_put_contents($filename, "VERSION=$version\n");
    }

    /**
     * @return string
     */
    private function getConfigFolder(): string
    {
        return rtrim($this->pathProvider->getPath(Paths::PATH_PLUGIN_CONFIG), '/');
    }
}
