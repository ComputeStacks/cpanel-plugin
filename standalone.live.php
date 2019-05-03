<?php
require_once '/usr/local/cpanel/php/cpanel.php';
require_once 'vendor/autoload.php';
require_once 'computestacks.live.php';

try {
  $cpanel = new CPANEL();
} catch (Exception $e) {
  echo 'Caught exception: ',  $e->getMessage(), "\n";
  die("Failed.");
}
// The version channel to use.
$csVersion = 'beta';

/**
 * See `domains.php` for what CS expects from this.
 *
 * If you don't want domain integration, you can completely disable this by setting this variable to: '';
 **/
$domainsEndpoint="domains.live.php";

try {
  $cs = new CSApi($cpanel);
} catch (Exception $e) {
  echo 'Caught exception: ',  $e->getMessage(), "\n";
  die("Failed.");
}


// Load current application based on update channel.
$csAssets = json_decode(file_get_contents("https://assets.computestacks.net/ui/versions.json"), true)[$csVersion];

$scripts = '<script src="' . $csAssets['js']['vendor']['src'] . '" integrity="' . $csAssets['js']['vendor']['check'] . '" crossorigin="anonymous"></script><script src="' . $csAssets['js']['app']['src'] . '" integrity="' . $csAssets['js']['app']['check'] . '" crossorigin="anonymous"></script>';

$stylesheets = '<link integrity="" rel="stylesheet" href="' . $csAssets['css']['vendor'] . '"><link integrity="" rel="stylesheet" href="' . $csAssets['css']['app'] . '">';

/**
 * Generate Configuration
 *
 * Your integration should supply the user details.
 *
 * setupUser(remote_id, first_name, last_name, email);
 *
 * remote_id: This is YOUR unique identifier for this user.
 *
 **/
$cpanelUserName = $cpanel->cpanelprint('$user');
$cpanelEmail = $cpanel->api2('CustInfo', 'contactemails')['cpanelresult']['data'][0]['value'];
// cPanel doesn't store first & last name, which is required by ComputeStacks, so we will use the cpanel username for both here.
$cs_auth = $cs->setupUser($cpanelUserName, $cpanelUserName, $cpanelUserName, $cpanelEmail);

$metaConfig = '<meta name="compute-stacks/initializers/options/endpoint" content="' . $cs_auth['endpoint'] . '" />
    <meta name="compute-stacks/initializers/options/currency" content="' . $cs_auth['currency_symbol'] . '" />
    <meta name="compute-stacks/initializers/options/authToken" content="' . $cs_auth['auth_token'] . '" />
    <meta name="compute-stacks/initializers/options/remoteDomain" content="' . $domainsEndpoint . '">
    <meta name="compute-stacks/config/environment" content="' . $csAssets['env'] . '" />';

$csHeader = $metaConfig . $stylesheets;

// $cpanelHeader = str_replace('</head>', $csHeader . '</head>', $cpanel->header(''));
?>
<!DOCTYPE html>
<html lang="en">
<header><?php echo $csHeader; ?></header>
<body>
<?php if ($cs_auth['errors'] != '') { ?>
  <div id="computestacks-app-errors">
    <p><?php print $cs_auth['errors'] ?></p>
  </div>
<?php } ?>
<div id="computestacks-app"></div>
<div id="ember-bootstrap-wormhole"></div>
<div id="ember-basic-dropdown-wormhole"></div>
<?php echo $scripts; ?>
</body>
<?php $cpanel->end(); ?>
</html>
