<?PHP

namespace LoxBerryUnifiPlugin\Service;

use LoxBerry\System\Plugin\PluginPathProvider;
use LoxBerry\System\Paths;

class SystemService
{
    /** Name of the docker container (see container_name in docker-compose.yml) */
    const CONTAINER_NAME = "unifi-network-application";

    /** Name of the MongoDB container (see container_name in docker-compose.yml) */
    const MONGO_CONTAINER_NAME = "unifi-db";

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
     * Force-removes both containers (app + database) without touching their data
     * volumes. Use when they are stuck (e.g. "Created" but never started, or
     * referencing a network that no longer exists) - the next service start then
     * lets docker compose recreate everything cleanly.
     */
    public function resetContainers()
    {
        shell_exec("docker rm -f " . self::CONTAINER_NAME . " " . self::MONGO_CONTAINER_NAME . " 2>&1");
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
     * Collects everything relevant for troubleshooting a failed/stuck service into
     * one text block: the systemd status (with the actual failure reason - e.g.
     * "address already in use" or "network ... not found" - which the container
     * logs alone do NOT show when a container exists but never started), the
     * current state of both containers, free disk space, both containers' own
     * logs, and the UniFi server.log. Meant to be copy-pasted when asking for help.
     */
    public function getDiagnostics(): string
    {
        $sections = array();
        $sections[] = "=== systemctl status " . self::SERVICE_NAME . " ===\n"
            . $this->getServiceStatusDetails(self::SERVICE_NAME, 40);
        $sections[] = "=== docker ps -a (unifi containers) ===\n"
            . $this->getContainerStates();
        $sections[] = "=== disk space ===\n"
            . $this->getDiskFree();
        $sections[] = "=== docker logs " . self::CONTAINER_NAME . " (app, last 150) ===\n"
            . $this->getRawContainerLogs(self::CONTAINER_NAME, 150);
        $sections[] = "=== docker logs " . self::MONGO_CONTAINER_NAME . " (database, last 150) ===\n"
            . $this->getRawContainerLogs(self::MONGO_CONTAINER_NAME, 150);
        $sections[] = "=== UniFi server.log (last 150) ===\n"
            . $this->tailFile("REPLACELBPLOGDIR/server.log", 150);
        return implode("\n\n", $sections);
    }

    /**
     * The full human-readable systemd status (includes the tail of the unit's
     * journal, which is where the actual failure reason from docker/compose ends
     * up, e.g. port conflicts or stale network references).
     */
    private function getServiceStatusDetails($serviceName, $lines = 40): string
    {
        $output = shell_exec("systemctl status " . escapeshellarg($serviceName) . " --no-pager -l 2>&1");
        if ($output === null) {
            return "";
        }
        $allLines = explode("\n", rtrim($output, "\n"));
        return implode("\n", array_slice($allLines, -$lines));
    }

    /**
     * Shows whether our containers are Up, stuck in Created, Exited (with which
     * code), or missing entirely - the fastest way to see something is stuck.
     */
    private function getContainerStates(): string
    {
        $output = shell_exec("docker ps -a --filter name=unifi-network-application --filter name=unifi-db 2>&1");
        return $output === null ? "" : $output;
    }

    private function getDiskFree(): string
    {
        $output = shell_exec("df -h / 2>&1");
        return $output === null ? "" : $output;
    }

    private function getRawContainerLogs($containerName, $lines): string
    {
        $lines = (int) $lines;
        $output = shell_exec("docker logs --tail $lines --timestamps " . escapeshellarg($containerName) . " 2>&1");
        return $output === null ? "" : $output;
    }

    /**
     * @return string
     */
    private function getConfigFolder(): string
    {
        return rtrim($this->pathProvider->getPath(Paths::PATH_PLUGIN_CONFIG), '/');
    }
}
