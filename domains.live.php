<?php
require_once '/usr/local/cpanel/php/cpanel.php';
$cpanel = new CPANEL();

header('Content-Type: application/json; charset=utf-8');
//header("Access-Control-Allow-Origin: *");
//header("Access-Control-Allow-Methods: GET, HEAD, OPTIONS, PUT");
//header("Access-Control-Allow-Headers: authorization, accept, content-type, per-page, total");

switch ($_SERVER['REQUEST_METHOD']) {
  case 'PUT':

    /**
     *
     * `?id={id}` Some identifier. For this example the domain name and ID are the same.
     *
     * Response from ComputeStacks:
     *
     *    {
     *      'remote_domain': {
     *          'name': 'domain name. This is only use for display purposes in ComputeStacks',
     *          'ip_addr': 'IP Address of the Load Balancer.',
     *          'ip6_addr': 'IPv6 Address'
     *      }
     *    }
     *
     *
     */
    $domainId = $_GET['id'];

    // Perform a lookup for this domain here and handle `NotFound` errors.

    // Parse the input
    $query = json_decode(file_get_contents('php://input'), true);
    $remoteDomain = $query['remote_domain'];

    $ipv4 = $remoteDomain['ip_addr'];
    $ipv6 = $remoteDomain['ip6_addr'];

    $zone = null;
    $sub = null;
    $errors = [];

    // Perform input validation. Here is an example of not including the IP Address.
    if ($ipv4 == NULL) {
      array_push($errors, 'Missing IP Address, DNS update failed.');
    } else if (!filter_var($remoteDomain['name'], FILTER_VALIDATE_DOMAIN)) {
      array_push($errors, 'Invalid domain name.');
    } else {

      $lookupDomain = $cpanel->uapi('DomainInfo', 'single_domain_data', ['domain' => $remoteDomain['name']])['cpanelresult']['result']['data'];

      if (count($lookupDomain) > 0) {
        if ($lookupDomain['type'] == 'sub_domain') { // For subdomains, we need to find the parent zone file.
          $domainSegments = explode('.', $lookupDomain['domain']);
          if (count($domainSegments) < 2) {
            array_push($errors, "Not a FQDN.");
          } else {
            $zone = null;

            // Check for standard single `.` domain.
            $zoneName = implode('.', array_slice($domainSegments, -2, 2));

            $lookupZoneCheck = $cpanel->uapi('DomainInfo', 'single_domain_data', ['domain' => $zoneName])['cpanelresult']['result']['data'];
            if (count($domainSegments) > 2 && count($lookupZoneCheck) < 1) {
              // Failed to find this domain, lets try one more level.
              $zoneName = implode('.', array_slice($domainSegments, -3, 3));
              $lookupZoneCheck = $cpanel->uapi('DomainInfo', 'single_domain_data', ['domain' => $zoneName])['cpanelresult']['result']['data'];
              if (count($lookupZoneCheck) < 1) {
                array_push($errors, "Failed to find parent zone for " . $remoteDomain['name']);
              } else {
                $zone = $zoneName;
                $sub = str_replace('.'.$zone,"",$remoteDomain['name']);
              }
            } else if (count($lookupZoneCheck) < 1) {
              array_push($errors, "Failed to find parent zone for " . $remoteDomain['name']);
            } else {
              $zone = $zoneName;
              if ($zone == $remoteDomain['name']) {
                $sub = $zone . '.';
              } else {
                $sub = str_replace('.'.$zone,"",$remoteDomain['name']); // example: returns 'www' if full domain is 'www.usr.cloud' and zone = 'usr.cloud'.
              }
            }
          }
        } else {
          $zone = $lookupDomain['domain'];
          $sub = $zone . '.';
        }

      } else {
        $domainSegments = explode('.', $remoteDomain['name']);
        if (count($domainSegments) > 1 && $domainSegments[0] == 'www') { // www won't appear in a normal lookup.
          $zoneName = implode('.', array_slice($domainSegments, -2, 2));
          $lookupZoneCheck = $cpanel->uapi('DomainInfo', 'single_domain_data', ['domain' => $zoneName])['cpanelresult']['result']['data'];
          if (count($lookupZoneCheck) > 1) { // Only accepting top-level www's; not performing an additional level check.
            $zone = $zoneName;
            $sub = 'www';
          }
        }
        if ($zone == null) {
          array_push($errors, "Unknown Domain " . $remoteDomain['name']);
        }
      }

      if (count($errors) < 1) { // only proceed if there are no errors.

        $currentRecords = $cpanel->api2('ZoneEdit', 'fetchzone_records', [
            'domain' => $zone,
            'name' => $remoteDomain['name'] . '.',
            'type' => 'A,AAAA,CNAME'
        ]);
        $createA = true;
        if ($ipv6) {
          $createAAAA = true;
        } else {
          $createAAAA = false;
        }
        if (count($currentRecords) > 0) {

          foreach($currentRecords['cpanelresult']['data'] as $item) {
            switch($item['type']) {
              case 'A':
                if ($ipv4 != $item['address']) {
                  $editAResult = $cpanel->api2('ZoneEdit', 'edit_zone_record',
                      [
                          'Line' => $item['line'],
                          'domain' => $zone,
                          'name' => $sub,
                          'type' => 'A',
                          'address' => $ipv4,
                          'ttl' => '14400',
                          'class' => 'IN',
                      ]
                  );
                  if ($editAResult['cpanelresult']['data'][0]['result']['status'] == 0) {
                    foreach ($editAResult['cpanelresult']['data'] as $er) {
                      array_push($errors, $er['statusmsg']);
                    }
                  }
                }
                $createA = false;
                break;
              case 'AAAA':
                if ($ipv6) {
                  if ($ipv6 != $item['address']) {
                    $editAAAAResult = $cpanel->api2('ZoneEdit', 'edit_zone_record',
                        [
                            'Line' => $item['line'],
                            'domain' => $zone,
                            'name' => $sub,
                            'type' => 'AAAA',
                            'address' => $ipv6,
                            'ttl' => '14400',
                            'class' => 'IN',
                        ]
                    );
                    if ($editAAAAResult['cpanelresult']['data'][0]['result']['status'] == 0) {
                      foreach ($editAAAAResult['cpanelresult']['data'] as $er) {
                        array_push($errors, $er['statusmsg']);
                      }
                    }
                  }
                  $createAAAA = false;
                } else {
                  // Not supplied, so deleting existing record.
                  $cpanel->api2('ZoneEdit', 'remove_zone_record', [
                      'domain' => $zone,
                      'line' => $item['line']
                  ]);
                }
                break;
              case 'CNAME':
                $cpanel->api2('ZoneEdit', 'remove_zone_record', [
                    'domain' => $zone,
                    'line' => $item['line']
                ]);
                break;
            }
          }
        }
      }

      if ($createA) {
        $createAResult = $cpanel->api2('ZoneEdit', 'add_zone_record',
            [
                'domain' => $zone,
                'name' => $sub,
                'type' => 'A',
                'address' => $ipv4,
                'ttl' => '14400',
                'class' => 'IN',
            ]
        );
        if ($createAResult['cpanelresult']['data'][0]['result']['status'] == 0) {
          foreach ($createAResult['cpanelresult']['data'] as $er) {
            array_push($errors, $er['statusmsg']);
          }
        }
      }

      if ($createAAAA) {
        $createAAAAResult = $cpanel->api2('ZoneEdit', 'add_zone_record',
            [
                'domain' => $zone,
                'name' => $sub,
                'type' => 'AAAA',
                'address' => $ipv6,
                'ttl' => '14400',
                'class' => 'IN',
            ]
        );
        if ($createAAAAResult['cpanelresult']['data'][0]['result']['status'] == 0) {
          foreach ($createAAAAResult['cpanelresult']['data'] as $er) {
            array_push($errors, $er['statusmsg']);
          }
        }
      }
    }

    if (count($errors) > 0) {
      $data = [
          'errors' => $errors
      ];
      http_response_code(422);
    } else {
      $data = [
          'remote_domain' => [ // Notice the singular `remote_domain`.
              'id' => $domainId,
              'name' => $remoteDomain['name'],
              'ip_addr' => $remoteDomain['ip_addr'],
          ]
      ];
    }

    // DEBUG LOG
    ob_start();
    var_dump($query);
    print("Received an update request for " . $remoteDomain['name'] . " to point to " . $remoteDomain['ip_addr'] . ".");
    error_log(ob_get_clean(), 4);

    break;
  case 'GET':

    /**
     * Your Domain Service should return all records when `id` or `name` params are missing from the query URL param.
     * Otherwise, supply just a single result.
     *
     */

    if ($_GET['id'] || $_GET['name']) {
      if ($_GET['id']) {
        $reqDomain = $_GET['id'];
      } else {
        $reqDomain = $_GET['name'];
      }
      $domainData = $cpanel->uapi('DomainInfo', 'single_domain_data', ['domain' => $reqDomain])['cpanelresult']['result']['data'];
      if (count($domainData) > 0) {
        $data = [
          'remote_domain' => [
              'id' => $domainData['domain'],
              'name' => $domainData['domain']
          ]
        ];
      } else {
        $data = [];
      }
    } else {
      $raw = $cpanel->uapi('DomainInfo', 'list_domains')['cpanelresult']['result']['data'];
      $domains = [];
      array_push($domains, $raw['main_domain']);
      foreach($raw['addon_domains'] as $item) {
        array_push($domains, $item);
      }
      foreach($raw['sub_domains'] as $item) {
        array_push($domains, $item);
      }
      foreach($raw['parked_domains'] as $item) {
        array_push($domains, $item);
      }
      $database = [];
      foreach($domains as $d) {
        array_push($database, [
            'id' => $d,
            'name' => $d
        ]);
      }
      $data = [
          'remote_domains' => $database  // Notice the plural `remote_domains`.
      ];
    }

    break;
  default:
    $data = [];
}
echo json_encode( $data );
