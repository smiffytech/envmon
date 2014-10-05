#!/usr/bin/env php
<?php

/**
 * Test code to prove local system.
 */

date_default_timezone_set('Australia/Adelaide');

require "./envmon.php";

$em = new ENVMON;

$init_result = $em->init();

if ($init_result < 0) {
  echo "Could not initialise, got code " . $init_result . "\n";
  exit;
}

$result = $em->newdoc();

echo $result . "\n";

$em->insert();

var_dump($em->thisdoc);

?>
