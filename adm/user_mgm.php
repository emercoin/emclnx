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
$conf = $lnx->GetCoinfig();
$dbh = $lnx->GetDbh();


//------------------------------------------------------------------------------
// MAIN here

if(empty($argv[1])) {
  echo "USER MANAGEMENT \n";
  echo "Usage:\n";
  echo "\t$argv[0] add username\n";
  echo "\t$argv[0] del username\n";
  echo "\t$argv[0] list\n";
  exit(0);
}

if($argv[1] == 'add') {
  	echo "adding: " .$argv[2];
	$stmt = $dbh->prepare("insert into hoster_shares (ref_id) values (?)");
	$stmt->execute(array($argv[2]));
	echo "done";
	exit(0);
} 

if($argv[1] == 'del') {
	echo "delete: " .$argv[2];
	$stmt = $dbh->prepare("delete from hoster_shares where ref_id=?");
	$stmt->execute(array($argv[2]));
	exit(0);
} 




// select sum(req_sent) from hoster_shares
// select sum(paid) from hoster_shares

// echo "username\t req_sent \t paid \n";
  printf("%-24s : %8.8s  %8.8s %8.8s\n", 
         "username", "req_sent",  "paid", "temp" );
$stmt = $dbh->prepare("select ref_id, req_sent, paid, temperature from hoster_shares");
$stmt->execute(array());

$sum1=0;
$sum2=0;
$count=0;
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $ref_id     = $row['ref_id'];
  $req_sent = $row['req_sent'];
  $paid     = $row['paid'];
  $temp     = $row['temperature'];
  $sum1=$sum1+$req_sent;
  $sum2=$sum2+$paid;
  $count++;
//  if(empty($addr))    continue;
  // Extract paid, and check balance < allowed
//  $paid = $lnx->emcLNX__req('getreceivedbyaddress', array($addr, 0));
//  $bal  = $paid - $req_sent;
//  $credit = $bal + $lnx->emcLNX__compute_allowance($paid);

  printf("%-24s : %8.2f  %8.2f %8.2f\n", 
         $ref_id, $req_sent,  $paid, $temp);

  // print_r($row);
}

//------------------------------------------------------------------------------
echo "---------------------------- \n";
  printf("%-24s : %8.2f  %8.2f \n", 
         $count, $sum1,  $sum2 );



// echo $lnx-> emcLNX__req("emercoind", "getinfo") ;
$getinfo=  $lnx->emcLNX__req('getinfo', null);
echo " total in wallet: ".$getinfo["balance"]." emc";
//$payment_tx = $lnx->emcLNX__req('sendtoaddress', array($pay_addr, floatval($actual_pay), "emcLNX $nvs_key"));
//echo $lnx->GetRand_href(120, 'getinfo'); // Max length of ads text string and optional - ref_id (can be empty or omitted)

//$tranz=  $lnx->emcLNX__req('listtransactions', null);
// print_r ($tranz);
echo "\n";                     
