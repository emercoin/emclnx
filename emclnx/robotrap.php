<?php
//--------------------------------------------------------------
// EMCLNX - EmerCoin Link Exchange system
// Distributed under BSD license
// https://en.wikipedia.org/wiki/BSD_licenses
// Designed by maxihatop, EmerCoin group
// WEB: http://www.emercoin.com
// Contact: team@emercoin.com


require_once('emclnx.php');


//------------------------------------------------------------------------------
// Ignore Chrome, since it uses pre-cache
// if(strpos($_SERVER['HTTP_USER_AGENT'], "Safari/") !== false)
//   exit(0);

//------------------------------------------------------------------------------
$lnx  = new emcLNX();
$conf = $lnx->GetCoinfig();
$dbh  = $lnx->GetDbh();
//------------------------------------------------------------------------------
// MAIN here
// Request from NVS list of contracts
 
$ip = $lnx->GetIP();

$rmd = unpack('S1', md5($ip . $conf['wallet']['url'], true))[1];
$iptresh = $conf['IPTreshold'] * 4; // Ban for 2 weeks

$dbh->exec("Replace into routemp(route_no, last_event, temperature) values($rmd, NOW(), $iptresh)");

$lnx->Log("\trobotrap locked($ip) route=$rmd");
header("Location: http://natribu.org/en/"); /* Redirect browser */

//------------------------------------------------------------------------------

?>
