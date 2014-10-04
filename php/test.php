#!/usr/bin/env php
<?php

/**
 * Test code to prove local system.
 */

require "./envmon.php";

$em = new ENVMON;

$em->init();

$em->newdoc();

$em->insert();

var_dump($em->thisdoc);

?>
