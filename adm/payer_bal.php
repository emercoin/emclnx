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

$stmt = $dbh->prepare("Select pay_addr, pay_balance, pay_sent, pay_ignored, visit_cnt from payer_hosts");
$stmt->execute(array());

//   EK3Sf2C2q6Vv3iWvGswCqwLPwis4oybA2x   0.00   0.40   0.00      4
echo "Pay_address                          Bal    Sent   Ignored   Visits\n";

foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  printf("%s %6.2f %6.2f %6.2f %6d\n", 
         $row['pay_addr'],  $row['pay_balance'], $row['pay_sent'], $row['pay_ignored'], $row['visit_cnt']);

  // print_r($row);
}

//------------------------------------------------------------------------------

?>
