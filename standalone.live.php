<?php
require_once '/usr/local/cpanel/php/cpanel.php';
require_once 'vendor/autoload.php';
require_once 'computestacks.live.php';

try {
	$cpanel = new CPANEL();
} catch (Exception $e) {
	echo 'Caught exception: ', $e->getMessage(), "\n";
	die("Failed.");
}

try {
	$csConfig = parse_ini_file("computestacks.ini");
} catch (Exception $e) {
	echo "Failed to load configuration file: ", $e->getMessage(), "\n";
	die("Fatal Error");
}

// We need to include this in our oauth redirect
$cpanelSecurityToken = preg_replace('{/}', '', $_ENV['cp_security_token']);

////
// Configure JS/CSS Assets
$csAutoLogin = $csConfig['autoLogin']; // false = standard oauth2 authorization (pop up). true = auto-generating user and autologin.
$csCDNBase = 'https://cdn.computestacks.net/ui/';
$csResource = $csCDNBase . $csConfig['version'] . '/assets/';

// cPanel Domains Integration
$domainsEndpoint = "domains.live.php";

////
// Get current URL for OAuth redirect
// You must register this when generating your Application ID.
$serverRequestUri = str_replace("/standalone.live.php", "", $_SERVER[REQUEST_URI]);

// Ensure we have the scheme and port.
$currentURL = "https://" . $_SERVER[HTTP_HOST] . ":2083" . $serverRequestUri;

// remove the security token from the URI. Will be added later.
$currentURL = str_replace($_ENV['cp_security_token'], '', $currentURL);


$scripts = '<script src="' . $csResource . 'vendor.js" crossorigin="anonymous"></script><script src="' . $csResource . 'compute-stacks.js" crossorigin="anonymous"></script>';
$stylesheets = '<link rel="stylesheet" href="' . $csResource . 'vendor.css"><link rel="stylesheet" href="' . $csResource . 'compute-stacks.css">';


$metaConfig = '<meta name="compute-stacks/initializers/options/endpoint" content="' . $csConfig['endpoint'] . '" />
    <meta name="compute-stacks/initializers/options/remoteDomain" content="' . $domainsEndpoint . '">
    <meta name="compute-stacks/initializers/options/baseUri" content="' . $currentURL . '">
    <meta name="compute-stacks/initializers/options/clientId" content="' . $csConfig['appID'] . '" />
    <meta name="compute-stacks/initializers/options/redirectToken" content="' . $cpanelSecurityToken . '" />';

/**
 * For AutoLogin, we will:
 *
 *    1) Attempt to load the user's apikey/secret from the local store (NVData) and inject that into meta tags for our app
 *    2) If those credentials do not exist, we will generate a new user account automatically.
 */
if ($csAutoLogin) {
	if ($csAutoLogin) {
		try {
			$cs = new CSApi($cpanel, $csConfig);
		} catch (Exception $e) {
			echo 'ComputeStacks Fatal Exception: ', $e->getMessage(), "\n";
			die("Failed to setup ComputeStacks.");
		}

		if ($cs->generateAuth($_SERVER[HTTP_HOST])) {
			$metaConfig = $metaConfig . $cs->authMetaTags();
		} else {
			echo "Failed to generate authentication credentials.";
			die("Fatal error");
		}

	}
}

$csHeader = $metaConfig . $stylesheets;
?>
<!DOCTYPE html>
<html lang="en">
<header><?php echo $csHeader; ?></header>
<body>
<div id="computestacks-app"></div>
<div id="ember-bootstrap-wormhole"></div>
<div id="ember-basic-dropdown-wormhole"></div>
<?php echo $scripts; ?>
</body>
<?php $cpanel->end(); ?>
</html>
