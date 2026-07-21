<?PHP
namespace LoxBerryUnifiPlugin\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpClient\HttpClient;

class DockerHubService
{
    const SEARCH_URL="https://hub.docker.com/v2/repositories/linuxserver/unifi-controller/tags/?page_size=100&page=1&name=version-&ordering=last_updated";

    /** @var array|null cached, sorted (newest first) multi-arch tag results from Docker Hub */
    private $cachedResults = null;

    /**
     * Fetches the available versions that are compatible with this host's architecture
     */
    public function getVersions() {
        $items = array();
        foreach ($this->getMultiArchResults() as $result) {
            if (in_array($this->getHostArchitecture(), $this->getArchitectures($result), true)) {
                $items[] = $result['name'];
            }
        }
        return $items;
    }

    /**
     * True if there are newer versions on Docker Hub that are not built for this host's architecture,
     * i.e. the newest available version is not part of getVersions().
     */
    public function hasVersionsUnavailableForArchitecture(): bool
    {
        $results = $this->getMultiArchResults();
        if (empty($results)) {
            return false;
        }
        return !in_array($this->getHostArchitecture(), $this->getArchitectures($results[0]), true);
    }

    /**
     * The normalized architecture identifier (docker-style: amd64, arm64, arm) of this host
     */
    public function getHostArchitecture(): string
    {
        switch (php_uname('m')) {
            case 'x86_64':
            case 'amd64':
                return 'amd64';
            case 'aarch64':
            case 'arm64':
                return 'arm64';
            case 'armv6l':
            case 'armv7l':
            case 'arm':
                return 'arm';
            default:
                return php_uname('m');
        }
    }

    /**
     * Fetches and caches the Docker Hub tag results, limited to merged multi-arch tags
     * (i.e. "version-x.y.z", not the single-arch "amd64-version-x.y.z" variants)
     */
    private function getMultiArchResults(): array
    {
        if ($this->cachedResults !== null) {
            return $this->cachedResults;
        }

        $client = HttpClient::create(['verify_peer'=>false,'verify_host'=>false]);

        $response = $client->request('GET', self::SEARCH_URL);
        $content = $response->toArray();

        $results = array();
        foreach ($content['results'] as $value) {
            if (count($value['images']) > 1) {
                $results[] = $value;
            }
        }

        return $this->cachedResults = $results;
    }

    /**
     * @return string[] the distinct architectures a tag was built for
     */
    private function getArchitectures(array $result): array
    {
        return array_unique(array_column($result['images'], 'architecture'));
    }
}
