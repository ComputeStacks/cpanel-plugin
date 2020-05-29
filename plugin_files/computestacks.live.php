<?php
/**
 * ComputeStacks cPanel Integration: User Authentication
 *
 * @see https://computestacks.com
 *
 * @copyright Copyright (c)2019 Compute Stacks, LLC.
 *
 */

use \GuzzleHttp\Client;


class CSApi
{

  public static $api_version = 52;
  public $endpoint;
  public $cpanel; // $cpanel variable
  public $username;
  public $locale;
  public $email;

  private $csAppID;
  private $csAppSecret;
  private $apikey;
  private $apisecret;

  function __construct($cpanel, $config)
  {
    $this->cpanel = $cpanel;
    $this->endpoint = $config['endpoint'];
    $this->csAppID = $config['setupAppID'];
    $this->csAppSecret = $config['setupAppSecret'];
    $this->loadCpanelData();
    $this->loadUserConfig();
  }

  public function generateAuth($server_url) {
    if ($this->apikey != '' && $this->apisecret != '') {
      return true;
    } else {
      return $this->createUser($server_url);
    }
  }

  public function authMetaTags() {
    return '<meta name="compute-stacks/initializers/options/username" content="' . $this->apikey . '" />
    <meta name="compute-stacks/initializers/options/password" content="' . $this->apisecret . '" />
    ';
  }

  private function createUser($server_url)
  {
    $data = [
      'user' => [
        'fname' => $this->username,
        'lname' => $this->username,
        'email' => $this->email,
        'locale' => $this->locale
      ],
      'provider_server' => $server_url,
      'provider_username' => $this->username
    ];
    $response = $this->connect('api/users', $data, 'POST');
    $result = json_decode($response->getBody());
    if ($response->getStatusCode() == 201) {
      $this->apikey = $result->user->api->username;
      $this->apisecret = $result->user->api->password;
      $this->saveUserConfig($result->user->api->username, $result->user->api->password);
      return true;
    } else {
      return false;
    }

  }

  private function connect($path, $body, $method = 'POST')
  {

    $data = array(
      'headers' => [
        'Accept' => 'application/json; api_version=' . self::$api_version,
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $this->authToken()
      ]
    );
    if ($body != null) {
      $data['json'] = $body;
    }
    $client = new Client();
    $full_path = $this->endpoint . '/' . $path;
    $request = $client->createRequest($method, $full_path, $data);
    return $client->send($request);
  }

  private function authToken() {
    try {

      $client = new Client();
      $full_path = $this->endpoint . '/api/oauth/token';
      $basicAuth = trim($this->csAppID) . ':' . trim($this->csAppSecret);
      $basicAuthEncoded = strtr( base64_encode( $basicAuth ), '+/', '-_');
      $data = [
        'headers' => [
          'Accept' => 'application/json; api_version=' . self::$api_version,
          'Content-Type' => 'application/json',
          'Authorization' => 'Basic ' . $basicAuthEncoded
        ],
        'json' => [
          'scope' => 'public register',
          'grant_type' => 'client_credentials'
        ]
      ];
      $request = $client->createRequest('POST', $full_path, $data);
      $response = $client->send($request);
      if ($response->getStatusCode() < 205) {
        $result = json_decode($response->getBody());
        return $result->access_token;
      } else {
        return '';
      }
    } catch (Exception $e) {
      echo "Error generating ComputeStacks Auth Token: " . $e->getMessage() . "<br>";
      die("fatal error");
    }
  }

  private function loadCpanelData() {
    $loadLocalUser = $this->cpanel->uapi('Variables', 'get_user_information',array('name' => 'user',));
    $this->username = $loadLocalUser['cpanelresult']['result']['data']['user'];
    $this->email = $this->cpanel->api2('CustInfo', 'contactemails')['cpanelresult']['data'][0]['value'];

    // Get locale
    $localeData = $this->cpanel->uapi('Locale', 'get_attributes');
    $this->locale = $localeData['cpanelresult']['result']['data']['locale'];
  }

  private function loadUserConfig()
  {
    try {
      $getCpanelConfig = $this->cpanel->uapi('NVData', 'get', ['names' => 'computestacks']);
      $userConfig = json_decode($getCpanelConfig['cpanelresult']['result']['data'][0]['value']);
      if ($userConfig == NULL) {
        $this->saveUserConfig('', '');
        $this->apikey = '';
        $this->apisecret = '';
      } else {
        $this->apikey = $userConfig->api_key;
        $this->apisecret = $userConfig->api_secret;
      }
    } catch (Exception $e) {
      $this->saveUserConfig('', '');
      $this->apikey = '';
      $this->apisecret = '';
    }
  }

  private function saveUserConfig($api_key, $api_secret)
  {
    $userData = [
      'api_key' => $api_key,
      'api_secret' => $api_secret
    ];
    $this->cpanel->uapi('NVData', 'set', [
      'names' => 'computestacks',
      'computestacks' => json_encode($userData)
    ]);
  }

}
