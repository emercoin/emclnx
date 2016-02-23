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

$stmt = $dbh->prepare("Select host, req_sent, req_addr, show_cnt, click_cnt from hoster_hosts");
$stmt->execute(array());

echo "Host                     :      Bal =     Paid -      Req;   Show  Click Addr                                 Credit\n";

foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $host     = $row['host'];
  $req_sent = $row['req_sent'];
  $show     = $row['show_cnt'];
  $click    = $row['click_cnt'];
  $addr     = $row['req_addr'];
  if(empty($addr))
    continue;
  // Extract paid, and check balance < allowed
  $paid = $lnx->emcLNX__req('getreceivedbyaddress', array($addr, 0));
  $bal  = $paid - $req_sent;

  $credit = $bal + $lnx->emcLNX__compute_allowance($paid);

  printf("%-24s : %8.2f = %8.2f - %8.2f; %6d %6d %s %8.2f\n", 
         $host, $bal,  $paid, $req_sent, $show, $click, $addr, $credit);

  // print_r($row);
}

//------------------------------------------------------------------------------

?>
