<?php
namespace Core\App;

use Core\Integration;

/**
 * Integration stub; extend this class to call third-party systems.
 *
 * Example:
 * class MyIntegration extends Integration {
 *     public function fetchData() {
 *         $response = $this->sendRequest('https://api.example.com', 'GET', []);
 *         return $this->handleResponse($response);
 *     }
 * }
 */
class SomeIntegration extends Integration {
    /**
     * Fetch data from a third-party application via Integration base.
     *
     * @return mixed Parsed response from the integration target
     */
    public function fetchData() {
        $url = "..."; // URL for the third-party application's API
        $method = "GET";
        $data = [];

        $response = $this->sendRequest($url, $method, $data);

        return $this->handleResponse($response);
    }

    // More methods for interacting with the third-party application...
}
