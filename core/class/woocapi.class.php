<?php
class WoocApi
{
    protected $baseUrl;
    protected $consumerKey;
    protected $consumerSecret;

    public function __construct($baseUrl, $consumerKey, $consumerSecret)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
    }

    protected function request($method, $path, $query = array(), $body = null)
    {
        $url = $this->baseUrl . '/wp-json/wc/v3' . $path;
        $query['consumer_key'] = $this->consumerKey;
        $query['consumer_secret'] = $this->consumerSecret;
        if (!empty($query)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        }

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Curl error: ' . $err);
        }

        $decoded = json_decode($response, true);
        if ($httpcode >= 400) {
            $msg = is_array($decoded) && isset($decoded['message']) ? $decoded['message'] : $response;
            throw new Exception('API error: HTTP ' . $httpcode . ' - ' . $msg);
        }

        return $decoded;
    }

    public function listOrders($status = 'completed', $per_page = 100, $page = 1, $after = null)
    {
        $query = array('status' => $status, 'per_page' => $per_page, 'page' => $page);
        if ($after) $query['after'] = $after;
        return $this->request('GET', '/orders', $query);
    }

    public function getOrder($id)
    {
        return $this->request('GET', '/orders/' . intval($id));
    }
}
