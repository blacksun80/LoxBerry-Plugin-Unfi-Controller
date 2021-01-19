<?PHP
namespace LoxBerryUnifiPlugin\Services;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpClient\HttpClient;

class DockerHubService 
{
    const SEARCH_URL="https://hub.docker.com/v2/repositories/linuxserver/unifi-controller/tags/?page_size=100&page=1&name=version-&ordering=last_updated";
    /**
     * Fetches the available versions
     */
    public function getVersions() {
        $items = array();

        $client = HttpClient::create(['verify_peer'=>false,'verify_host'=>false]);
        
        $response = $client->request('GET', self::SEARCH_URL);
        $content = $response->toArray();

        foreach($content['results'] as $key => $value){
            if(count($value['images']) > 1){
                $items[]=$value['name'];
            }
        }
        return $items;
    }
}