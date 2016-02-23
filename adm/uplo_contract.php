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

if($argc < 3) {
  echo "Usage: $argv[0] CONTRACT_NAME CPC [dest_URL]\n";
  exit();
}
$dest_url = empty($argv[3])? "" : $argv[3];
$stmt = $dbh->prepare("Replace into payer_contracts Set nvs_key=?, cpc=?, dest_url=?");
$stmt->execute(array($argv[1], $argv[2], $dest_url));


//------------------------------------------------------------------------------

?>
