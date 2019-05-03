<?php
/**
 * ComputeStacks cPanel Integration
 *
 * @see https://computestacks.com
 *
 * @copyright Copyright (c)2019 Compute Stacks, LLC.
 *
 */
use \Firebase\JWT\JWT;
use \GuzzleHttp\Client;


class CSApi
{

  public static $api_version = 51;
  public $endpoint;
  public $user_id;
  public $currency_symbol;
  public $cpanel;

  private $admin_api_key;
  private $admin_api_secret;
  private $auth_file;
  private $user_api_key;
  private $user_api_secret;

  function __construct($cpanel)
  {

    $this->cpanel = $cpanel;

    // TODO: Load from Admin credentials file.
    $init_endpoint = 'https://demo.computestacks.net';
    $this->admin_api_key = 'ns34EPU1uvyzMYbfAyzNT6A3';
    $this->admin_api_secret = 'oLDsacfJu0bTEL7FaPCWkQnF';

    $this->loadUserConfig($init_endpoint);
    // Catch issues with the endpoint
    if ($this->endpoint == NULL OR $this->endpoint == '') {
      $this->endpoint = $init_endpoint;
    }
  }


  /**
   * Perform user setup and authentication.
   *
   * For existing users with a valid auth, this will simply refresh the auth token.
   * Otherwise, it will either:
   *  a) Generate API credentials for an existing user.
   *  b) If the user does not exist, it will create them.
   *
   */
  public function setupUser($cpanel_id, $user_fname, $user_lname, $user_email)
  {
    try {
      if ($this->user_api_key == '' || $this->user_api_secret == '') {
        $auth_token = $this->authToken();
        $this->user_id = $this->findUserIdByEmail($user_email, $auth_token);
        if ($this->user_id == 0) {
          $this->user_id = $this->createUser($cpanel_id, $user_fname, $user_lname, $user_email, $auth_token);
          if ($this->user_id == 0) {
            exit;
            // TODO: Capture error message.
          }
        }
        if (!$this->resetApiCredentials($cpanel_id, $auth_token)) {
          exit;
        }
      }
      return array(
          'auth_token' => $this->userAuthToken(),
          'endpoint' => $this->endpoint,
          'currency_symbol' => $this->currency_symbol,
          'errors' => '',
      );
    } catch (Exception $e) {
      return array(
          'auth_token' => '',
          'endpoint' => $this->endpoint,
          'currency_symbol' => $this->currency_symbol,
          'errors' => 'Authentication Failure',
      );
    }

  }

  private function findUserIdByEmail($user_email, $auth_token)
  {
    try {
      $data = array(
          'user' => array(
              'username' => $user_email
          )
      );
      $response = $this->connect('api/admin/sso?noverify=true', $auth_token, $data, 'POST');
      if ($response->getStatusCode() == 200) {
        $result = json_decode($response->getBody());
        $this->currency_symbol = $result->user->currency_symbol;
        return $result->user->id;
      } else {
        return 0;
        // TODO: Handle errors.
        // $errorMsg = json_decode($result->getBody());      
        // return implode(" ", $errorMsg);
      }
    } catch (Exception $e) { // CS returns 401 if user does not exist, which will throw an error in Guzzle.
      return 0;
    }

  }

  private function resetApiCredentials($cpanel_id, $auth_token)
  {
    // TODO: Store cPanel_id with api_credentials / user in CS.
    $path = 'api/admin/users/' . $this->user_id . '/api';
    $response = $this->connect($path, $auth_token, null, 'POST');
    switch ($response->getStatusCode()) {
      case 201:
        $result = json_decode($response->getBody());
        if ($result == null) {
          return false;
          // TODO: Handle this fatal error.
        }
        $this->user_api_key = $result->user->api_key;
        $this->user_api_secret = $result->user->api_secret;
        $this->currency_symbol = $result->user->currency_symbol;

        // Write new credentials
        $this->saveUserConfig($this->endpoint, $this->user_id, $this->user_api_key, $this->user_api_secret, $this->currency_symbol);
        return true;
      case 404: // User does not exist on server. Wipe existing credentials.
        $this->saveUserConfig($this->endpoint, 0, '', '', $this->currency_symbol);
        return false;
      default:
        return false;
      // TODO: Capture error
    }
  }

  private function createUser($cpanel_id, $user_fname, $user_lname, $user_email, $auth_token)
  {
    $data = [
        'user' => [
            'fname' => $user_fname,
            'lname' => $user_lname,
            'email' => $user_email,
            'country' => 'USA',
            'skip_email_confirm' => true
        ]
    ];
    $response = $this->connect('api/admin/users', $auth_token, $data, 'POST');
    $result = json_decode($response->getBody());
    if ($response->getStatusCode() == 201) {
      return $result->user->id;
    } else {
      return $result;
    }

  }

  // Generate auth token to log into CS using apikey / secret.
  private function authToken()
  {
    $data = [
        'headers' => [
            'Accept' => 'application/json; api_version=' . self::$api_version,
            'Content-Type' => 'application/json'
        ],
        'json' => [
            'api_key' => $this->admin_api_key,
            'api_secret' => $this->admin_api_secret
        ]
    ];
    $client = new Client();
    $path = $this->endpoint . '/api/auth';
    $request = $client->createRequest('POST', $path, $data);
    $response = $client->send($request);
    $result = json_decode($response->getBody());
    return $result->token;
  }

  /**
   * Generate auth token to log into CS using apikey / secret.
   *
   * If CS returns a 401, we will gracefully fail by clearing the current API credentials and returning nil.
   * This should present a nice error to the user and prompt a refresh, which will re-generate the api credentials.
   */
  private function userAuthToken()
  {
    try {
      $data = [
          'headers' => [
              'Accept' => 'application/json; api_version=' . self::$api_version,
              'Content-Type' => 'application/json'
          ],
          'json' => [
              'api_key' => $this->user_api_key,
              'api_secret' => $this->user_api_secret
          ]
      ];
      $client = new Client();
      $path = $this->endpoint . '/api/auth';
      $request = $client->createRequest('POST', $path, $data);
      $response = $client->send($request);
      if ($response->getStatusCode() == 200) {
        $result = json_decode($response->getBody());
        return $result->token;
      } else {
        file_put_contents($this->auth_file, '{"endpoint": "' . $init_endpoint . '", "user_id": 0, "api_key": "", "api_secret": ""}');
        return null;
      }
    } catch (Exception $e) {
      file_put_contents($this->auth_file, '{"endpoint": "' . $this->endpoint . '", "user_id": ' . $this->user_id . ', "api_key": "", "api_secret": ""}');
      return null;
    }

  }

  // API Call to CS.
  private function connect($path, $token, $body, $method = 'POST')
  {
    if ($token == '') {
      $token = $this->authToken();
    }
    $data = array(
        'headers' => [
            'Accept' => 'application/json; api_version=' . self::$api_version,
            'Content-Type' => 'application/json',
            'Authorization' => $token
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

  public function loadUserConfig($default_endpoint)
  {
    $getCpanelConfig = $this->cpanel->uapi('NVData', 'get', ['names' => 'computestacks']);
    $userConfig = json_decode($getCpanelConfig['cpanelresult']['result']['data'][0]['value']);
    if ($userConfig == NULL) {
      $this->saveUserConfig($default_endpoint, 0, '', '', '$');
      $this->endpoint = $default_endpoint;
      $this->user_id = 0;
      $this->user_api_key = '';
      $this->user_api_secret = '';
      $this->currency_symbol = '$';
    } else {
      $this->endpoint = $userConfig->endpoint;
      $this->user_id = $userConfig->user_id;
      $this->user_api_key = $userConfig->api_key;
      $this->user_api_secret = $userConfig->api_secret;
      $this->currency_symbol = $userConfig->currency_symbol;
    }

  }

  private function saveUserConfig($endpoint, $user_id, $api_key, $api_secret, $currency)
  {
    $userData = [
        'endpoint' => $endpoint,
        'user_id' => $user_id,
        'api_key' => $api_key,
        'api_secret' => $api_secret,
        'currency_symbol' => $currency
    ];
    $this->cpanel->uapi('NVData', 'set', [
        'names' => 'computestacks',
        'computestacks' => json_encode($userData)
    ]);
  }

}