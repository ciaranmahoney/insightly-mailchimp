<?php
require('insightly.php');
require_once 'inc/config.inc.php'; //contains apikey

function run_tests($apikeyIN){
  $insightly = new Insightly($apikeyIN);
  $insightly->test();
}

run_tests($argv[1]);
?>