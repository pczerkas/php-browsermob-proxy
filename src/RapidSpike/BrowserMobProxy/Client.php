<?php

namespace RapidSpike\BrowserMobProxy;

/**
 * BrowserMob Proxy Client class for interacting with the BrowserMobProxy API.
 * Completely assumes that the servlet software is installed and running as
 * there's not really a very reliable, nice way of checking and managing this.
 *
 * @author James Tyler <james.tyler@rapidspike.com>
 */
class Client
{

    /**
     * @var string
     */
    protected $browsermob_url;

    /**
     * @var string
     */
    protected $proxy;

    /**
     * @var string
     */
    protected $instance_port;

    /**
     * @var string
     */
    protected $hostname;

    /**
     * Class constructor. Sets some basics to the class.
     *
     * @param string $url URL for BrowserMobProxy instance
     * @param string $proxy BrowserMobProxy traffic routed through this Http proxy outbound eg 11.22.33.44:80
     * @param string $instance_port Define custom Proxy port created by BrowserMobProxy eg 9999
     */
    public function __construct(string $url, string $proxy = null, string $instance_port = null)
    {
        $this->browsermob_url = $url;
        $this->proxy = $proxy;
        $this->instance_port = $instance_port;
    }

    /**
     * Open connection to the proxy
     *
     * @param string  $query
     *
     * @return \Requests
     */
    public function open(string $query = null): \Requests
    {
        $parts = parse_url($this->browsermob_url);
        $this->hostname = $parts["host"];

        $data = '';
        if (!empty($this->instance_port)) {
            $data = 'port=' . $this->instance_port;
        }

        $query .= (!empty($this->proxy) ? "&httpProxy={$this->proxy}" : '');
        $response = \Requests::post("http://{$this->browsermob_url}/proxy?{$query}", [], $data);

        $decoded = json_decode($response->body, true);
        $this->port = ($decoded ? $decoded["port"] : $this->instance_port);
        $this->url = $this->hostname . ":" . $this->port;

        return $response;
    }

    /**
     * Close connection to the proxy
     *
     * @return \Requests
     */
    public function close(): \Requests
    {
        return \Requests::delete("http://{$this->browsermob_url}/{$this->port}");
    }

    /**
     * Private method for encoding an array of arguments, used internally
     *
     * @param array $args array of arguments to URLencode
     *
     * @return string
     */
    private function _encodeArray(array $args): string
    {
        if (!is_array($args)) {
            return false;
        }

        $c = 0;
        $out = '';

        foreach ($args as $name => $value) {
            if ($c++ != 0) {
                $out .= '&';
            }
            $out .= urlencode("$name") . '=';
            if (is_array($value)) {
                $out .= urlencode(serialize($value));
            } else {
                $out .= urlencode("$value");
            }
        }

        return $out;
    }

    /**
     * Magic method for handling the returnig of properties
     *
     * @param string $property class property
     *
     * @return mixed
     */
    public function __get(string $property)
    {
        switch ($property) {
            case "har":
                $proxy_handle = curl_init();
                $har_url = "http://{$this->browsermob_url}/proxy/{$this->port}/har";
                curl_setopt($proxy_handle, CURLOPT_URL, $har_url);
                curl_setopt($proxy_handle, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($proxy_handle);
                $decoded = json_decode($result, true);
                curl_close($proxy_handle);
                return $decoded;
            default:
                return $this->$property;
        }
    }

    /**
     * Method for creating a new HAR file
     *
     * @param string $label
     * @param bool $captureHeaders
     *
     * @return \Requests
     */
    public function newHar(string $label = '', bool $captureHeaders = false): \Requests
    {
        $data = ["initialPageRef" => $label, 'captureHeaders' => ($captureHeaders ? "true" : "false")];
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/har";
        return \Requests::put($url, [], $data);
    }

    /**
     * Method for returning HAR info about one page
     *
     * @param string $label
     *
     * @return \Requests
     */
    public function newPage(string $label = ''): \Requests
    {
        $data = "pageRef=" . $label;
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/har/pageRef";
        return \Requests::put($url, [], $data);
    }

    /**
     * Add regex pattern to the proxy blacklist
     *
     * @param string $regexp
     * @param int $status_code
     *
     * @return \Requests
     */
    public function blacklist(string $regexp, int $status_code): \Requests
    {
        $data = $this->_encodeArray(["regex" => $regexp, "status" => $status_code]);
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/blacklist";
        return \Requests::put($url, [], $data);
    }

    /**
     * Add regex pattern to the proxy whitelist
     *
     * @param string $regexp
     * @param int $status_code
     *
     * @return \Requests
     */
    public function whitelist(string $regexp, int $status_code): \Requests
    {
        $data = $this->_encodeArray(["regex" => $regexp, "status" => $status_code]);
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/whitelist";
        return \Requests::put($url, [], $data);
    }

    /**
     * Handle basic authentication using the proxy
     *
     * @param string $domain
     * @param array $options
     *
     * @return \Requests
     */
    public function basicAuth(string $domain, array $options): \Requests
    {
        $data = json_encode($options);
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/auth/basic/{$domain}";
        return \Requests::post($url, ['Content-Type' => 'application/json'], $data);
    }

    /**
     * Send header information to the proxy
     *
     * @param array $options
     *
     * @return \Requests
     */
    public function headers(array $options): \Requests
    {
        $data = json_encode($options);
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/headers";
        return \Requests::post($url, ['Content-Type' => 'application/json'], $data);
    }

    /**
     * Send JavaScript to the response interceptor
     *
     * @param string $js
     *
     * @return \Requests
     */
    public function responseInterceptor(string $js): \Requests
    {
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/interceptor/response";
        return \Requests::post($url, ['Content-Type' => 'x-www-form-urlencoded'], $js);
    }

    /**
     * Send JavaScript to the request interceptor
     *
     * @param string $js
     *
     * @return \Requests
     */
    public function requestInterceptor(string $js): \Requests
    {
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/interceptor/request";
        return \Requests::post($url, ['Content-Type' => 'x-www-form-urlencoded'], $js);
    }

    /**
     * Set limits for the HTTP connection
     *
     * @param array $options options needed for limiting connections
     *
     * @return string
     */
    public function limits(array $options): \Requests
    {
        $keys = array(
            "downstreamKbps" => "downstreamKbps",
            "upstreamKbps" => "upstreamKbps",
            "latency" => "latency"
        );

        foreach (array_keys($options) as $option_name) {
            if (!array_key_exists($option_name, $keys)) {
                throw new \Exception($option_name . " is not a valid 'limits' option");
            }
        }

        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/limit";
        return \Requests::put($url, [], $this->_encodeArray($options));
    }

    /**
     * Set proxy timeouts
     *
     * @param array $options
     *
     * @return \Requests
     *
     * @throws \Exception
     */
    public function timeouts(array $options): \Requests
    {
        $keys = array(
            "request" => "requestTimeout",
            "read" => "readTimeout",
            "connection" => "connectionTimeout",
            "dns" => "dnsCacheTimeout"
        );

        foreach (array_keys($options) as $option_name) {
            if (!array_key_exists($option_name, $keys)) {
                throw new \Exception($option_name . " is not a valid 'timeouts' option");
            }
        }

        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/timeout";
        return \Requests::put($url, [], $this->_encodeArray($options));
    }

    /**
     * Method for remapping host names to IP addresses for local use
     *
     * @param string $address
     * @param string $ip_address
     *
     * @return \Requests
     */
    public function remapHosts(string $address, string $ip_address): \Requests
    {
        $data = json_encode([$address => $ip_address]);
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/hosts";
        return \Requests::post($url, ['Content-Type' => 'application/json'], $data);
    }

    /**
     * Method for setting how long proxy should wait for traffic to stop
     *
     * @param int $quiet_period
     * @param int $timeout
     *
     * @return \Requests
     */
    function waitForTrafficToStop(int $quiet_period, int $timeout): \Requests
    {
        $data = $this->_encodeArray([
            'quietPeriodInMs' => (string) ($quiet_period * 1000),
            'timeoutInMs' => (string) ($timeout * 1000)
        ]);

        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/wait";
        return \Requests::put($url, [], $data);
    }

    /**
     * Method for clearing the DNS cache set with remapHosts
     *
     * @return \Requests
     */
    public function clearDnsCache(): \Requests
    {
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/dns/cache";
        return \Requests::delete($url);
    }

    /**
     * Method for rewriting URL's sent to the proxy
     *
     * @param string $match
     * @param string $replace
     *
     * @return \Requests
     */
    public function rewriteUrl(string $match, string $replace): \Requests
    {
        $data = $this->_encodeArray(['matchRegex' => $match, 'replace' => $replace]);
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/rewrite";
        return \Requests::put($url, [], $data);
    }

    /**
     * Method to set how many times a request should be retried
     *
     * @param int $retry_count
     *
     * @return \Requests
     */
    public function retry(int $retry_count): \Requests
    {
        $data = $this->_encodeArray(['retrycount' => $retry_count]);
        $url = "http://{$this->browsermob_url}/proxy/{$this->port}/retry";
        return \Requests::put($url, [], $data);
    }

}
