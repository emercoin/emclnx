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

$stmt = $dbh->prepare("Delete from hoster_hosts where host not in (select host from hoster_contracts)");
$stmt->execute(array());

//------------------------------------------------------------------------------

?>
