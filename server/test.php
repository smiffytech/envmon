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

$em->getbydaterange('2014-09-09', '2014-11-11');

var_dump( $em->retrieved );

//$result = $em->newdoc();

//echo $result . "\n";

//$em->insert();

//var_dump($em->thisdoc);

?>
