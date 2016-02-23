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

if(empty($argv[1])) {
  echo "Amnesty for non-paid host.\n";
  echo "Usage:\n";
  echo "\t$argv[0] <host_list> or \"-\" for all\n";
  exit(0);
}

if($argv[1] == '-') {
  $stmt = $dbh->prepare("Select host from hoster_hosts");
  $stmt->execute(array());
  $host_list = array();
  foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    array_push($host_list, $row['host']);
  }
} else {
  $host_list = $argv;
  array_shift($host_list);
}

foreach($host_list as $host) {
  $stmt = $dbh->prepare("Select req_sent, req_addr from hoster_hosts where host=?");
  $stmt->execute(array($host));
  $row = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];
  $addr     = $row['req_addr'];
  $req_sent = $row['req_sent'];
  if($req_sent == 0)
    continue;
  // echo "host=$host; A=$addr; S=$req_sent;\n";
  $paid = $lnx->emcLNX__req('getreceivedbyaddress', array($addr, 0));
  $bal  = $paid - $req_sent;
  $credit = $bal + $lnx->emcLNX__compute_allowance($paid);
  // echo "H= $host $addr $req_sent $bal $credit\n";
  if($credit < 0) {
    $credit = intval(-$credit * 100.0 + 1.0) / 100.0;
    $stmt = $dbh->prepare("Update hoster_hosts Set req_sent=req_sent-? where host=?");
    $stmt->execute(array($credit, $host));
    echo "Host $host amnested by $credit EMC\n";
  }
}

//------------------------------------------------------------------------------

?>
