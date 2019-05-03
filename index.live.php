<?php
require_once '/usr/local/cpanel/php/cpanel.php';

try {
  $cpanel = new CPANEL();
} catch (Exception $e) {
  echo 'Caught exception: ',  $e->getMessage(), "\n";
  die("Failed.");
}
//style="position: absolute; height: 100%; border: none"
//style="height: 100%; width: 100%;"
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
  <iframe src="standalone.live.php"></iframe>
</div>

<?php
echo $cpanel->footer();
$cpanel->end();
?>
