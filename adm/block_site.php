#!/usr/bin/php
<?php
//--------------------------------------------------------------
// EMCLNX - EmerCoin Link Exchange system
// Distributed under BSD license
// https://en.wikipedia.org/wiki/BSD_licenses
// Designed by maxihatop, EmerCoin group
// WEB: http://www.emercoin.com
// Contact: team@emercoin.com


require_once('../emclnx/emclnx.php');

$lnx = new emcLNX();
$dbh = $lnx->GetDbh();

//------------------------------------------------------------------------------
// MAIN here

if($argc < 2) {
  echo "Usage: $argv[0] Site_Name\n";
  exit();
}

$stmt = $dbh->prepare("Update hoster_hosts Set req_sent=9999.99 where host=?");
$stmt->execute(array($argv[1]));


//------------------------------------------------------------------------------

?>
