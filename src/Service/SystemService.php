<?PHP

namespace LoxBerryUnifiPlugin\Service;

use LoxBerry\System\Plugin\PluginPathProvider;
use LoxBerry\System\Paths;

class SystemService
{
    /** Name of the docker container (see container_name in docker-compose.yml) */
    const CONTAINER_NAME = "unifi-network-application";

    /** Name of the systemd service that runs docker compose for the container */
    const SERVICE_NAME = "unifi";

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
        if ($output === null) {
            $output = "";
        }
        // During a version change the container is stopped, removed and recreated
        // (with an image pull), so `docker logs` reports "No such container".
        // Fall back to the service's systemd journal, which captures the compose
        // pull/create progress, so the view keeps showing what is happening.
        if (strpos($output, 'No such container') !== false) {
            $journal = shell_exec("journalctl -u " . self::SERVICE_NAME . " -n $lines --no-pager 2>/dev/null");
            if ($journal !== null && trim($journal) !== "") {
                return $journal;
            }
            return "Container is being recreated / the image is downloading - please wait ...";
        }
        return $output;
    }

    /**
     * Returns the last $lines lines of a log file (e.g. the UniFi server.log),
     * or an empty string if the file does not exist yet / is not readable.
     */
    public function tailFile($filename, $lines = 300)
    {
        if (!is_readable($filename)) {
            return "";
        }
        $lines = (int) $lines;
        $content = shell_exec("tail -n $lines " . escapeshellarg($filename) . " 2>/dev/null");
        return $content === null ? "" : $content;
    }

    public function getContainerVersionFromEnv()
    {
        $filename = $this->getConfigFolder() . "/env";
        // The env file holds several lines (VERSION, MONGO_PASS, ...); read the
        // VERSION line specifically instead of splitting the whole file.
        if (is_readable($filename)) {
            foreach (file($filename, FILE_IGNORE_NEW_LINES) as $line) {
                if (strpos($line, 'VERSION=') === 0) {
                    return trim(substr($line, strlen('VERSION=')));
                }
            }
        }
        return "";
    }

    public function setContainerVersion($version)
    {
        $filename = $this->getConfigFolder() . "/env";
        // Update only the VERSION line and keep every other line (e.g. MONGO_PASS).
        // A trailing newline is required: docker compose v2 ignores the last line
        // of a .env file if it is not newline-terminated, leaving the value unset.
        $lines = is_readable($filename) ? file($filename, FILE_IGNORE_NEW_LINES) : array();
        $out = array();
        $found = false;
        foreach ($lines as $line) {
            if (strpos($line, 'VERSION=') === 0) {
                $out[] = "VERSION=$version";
                $found = true;
            } elseif (trim($line) !== '') {
                $out[] = $line;
            }
        }
        if (!$found) {
            array_unshift($out, "VERSION=$version");
        }
        file_put_contents($filename, implode("\n", $out) . "\n");
    }

    /**
     * @return string
     */
    private function getConfigFolder(): string
    {
        return rtrim($this->pathProvider->getPath(Paths::PATH_PLUGIN_CONFIG), '/');
    }
}
