<?php
require_once '/usr/local/cpanel/php/cpanel.php';

try {
	$cpanel = new CPANEL();
} catch (Exception $e) {
	echo 'Caught exception: ',  $e->getMessage(), "\n";
	die("Failed.");
}
?>
<?php echo $cpanel->header(''); ?>
<style>
	.iframe-container {
		overflow: hidden;
		padding-top: 56.25%;
		position: relative;
	}
	.iframe-container iframe {
		border: 0;
		height: 100%;
		left: 0;
		position: absolute;
		top: 0;
		width: 100%;
	}
</style>
<div class="body-content iframe-container">
	<?php if ($_GET['image'] != '') { ?>
	<iframe src="standalone.live.php#/order"></iframe>
	<?php } else { ?>
	<iframe src="standalone.live.php"></iframe>
	<?php } ?>
</div>
<?php
echo $cpanel->footer();
$cpanel->end();
?>
