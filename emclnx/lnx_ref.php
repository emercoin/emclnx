<?php
//--------------------------------------------------------------
// EMCLNX - EmerCoin Link Exchange system
// Distributed under BSD license
// https://en.wikipedia.org/wiki/BSD_licenses
// Designed by maxihatop, EmerCoin group
// WEB: http://www.emercoin.com
// Contact: team@emercoin.com


require_once('emclnx.php');
$lnx  = new emcLNX();
$conf = $lnx->GetCoinfig();
$dbh  = $lnx->GetDbh();

function emcLNX__updRating($host, $cpc, $routemp)  {
  global $lnx, $conf, $dbh;
  try {
    $dbh->beginTransaction();
    $q = "Select rating, temperature, TIME_TO_SEC(TIMEDIFF(NOW(), last_event)) as dt, req_addr, req_sent from hoster_hosts" .
         " where host=? for update";
    $stmt = $dbh->prepare($q);
    $stmt->execute(array($host));
    $hoster_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Update credentials by formulas
    $dt       = $hoster_rows[0]['dt'];
    $temp     = $hoster_rows[0]['temperature'] * exp(-$dt / $conf['RatingTAU']);
    $rating   = $hoster_rows[0]['rating'];
    $req_this = 0;
    $rolbacktemp = $temp;

    if(!$lnx->IsRobot()) {
      // this is real user, so invoice is needed
      $temp   += 1 + log(1 + $cpc);
      $rating += 1 / $temp;
      // Set Actual CPC - adjust with Rating and routemp
      $req_this = $cpc * $rating / ($rating + $conf['K4RatingSIGMA']); 
    }

    $routemp  = $routemp <= 1.0? 1.0 : sqrt($routemp); 
    $req_this = sprintf("%.2f", $req_this / $routemp); 
    $req_sent = $hoster_rows[0]['req_sent'] + $req_this; 

    // Generate new address, if not exist yet.
    $req_addr = $hoster_rows[0]['req_addr'];
    if(empty($req_addr)) 
      $req_addr = $lnx->emcLNX__req('getnewaddress', array($conf['wallet']['account']));
    $q = "Update hoster_hosts Set rating=?, temperature=?, req_addr=?, req_sent=?," .
         " last_event=Now(), click_cnt=click_cnt + 1" .
         " where host=?";
    $stmt = $dbh->prepare($q);
    $stmt->execute(array($rating, $temp, $req_addr, $req_sent, $host));
    // RETURN HERE
    return array($req_addr, $req_this, $req_sent, $rolbacktemp);
  } catch(Exception $ex) {
    echo "emcLNX__updRating error: ". $ex->getMessage() . "\n";
    $dbh->rollBack();
    return 0;
  }
} // emcLNX__updRating

//------------------------------------------------------------------------------
// Return 0 if error, or colon-separated array like:
// https://www.hashcoins.ru/HashCoins:EQ6DKLnBAbHxUvGTyjuAhQwVruZ7SvXHbR:0.91:7.6:-6.61
// There is:
// URL : ContractName : PayTo address : Pay for this Click : Balance : Credit(allowance)
function emcLNX_lnx_ref($conref, $ip) {
  global $lnx, $conf, $dbh;

  $routemp = $lnx->emcLNX__IPTemperature($ip, $lnx->IsRobot()? 0 : 1);
  if($routemp > $conf['IPTreshold'] * 0.8)
    return 0; // Seems like fraudster activity

  // Preserve JS/SQL injection over params and exttract $nvs_key, $ref_id
  list($nvs_key, $ref_id) = preg_split('/:/', preg_replace('/[^:\w\.]/', '', $conref), 2);
  if(empty($nvs_key)) 
    return 0;

  $data = 0; // No rollback in update transaction

  // Fetch fresh contract from NVS; Delete from DB, if not exist or expired
  try {
    $fresh_contract = $lnx->emcLNX__req('name_show', array('lnx:' . $nvs_key));
    if($fresh_contract['expires_in'] <= 0)
        throw new Exception("contract expired: $nvs_key");

    // Extract contractID if exist in DB; Otherwise, set -1 for insert new record without filters
    try {
      $stmt = $dbh->prepare("Select contractID from hoster_contracts where nvs_key=?");
      $stmt->execute(array($nvs_key));
      $hoster_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $contractID  = sizeof($hoster_rows)? $hoster_rows[0]['contractID'] : -1; 
    } catch(Exception $ex) {
      $contractID = -1; // not exist, will be inserted
    }

    // Update DB with fresh contract data and adjust score, if needed
    $row_contract = $lnx->emcLNX__process_contract($fresh_contract, $contractID);
    if(!$row_contract)
      throw new Exception("wrong contract: $nvs_key");
    // Check CPC in a new contract - maybe, it changed down
    $cpc = $row_contract['CPC'][0];
    if($cpc < 0)
      throw new Exception("negative CPC for: $nvs_key");

    // Check host presence and signature
    $host = $lnx->emcLNX__getHost($row_contract);
    if(!$host)
      throw new Exception("missing host for: $nvs_key");
 
    // DATA struct: array($req_addr, $req_this, $req_sent, $rolback_temp);
    //                      0            1         2           3
    // There is started SQL transaction
    $data = emcLNX__updRating($host, $cpc, $routemp);
    if($data == 0)
      throw new Exception("cannot update rating: $nvs_key");

    // Extract paid, and compute credit
    $paid = $lnx->emcLNX__req('getreceivedbyaddress', array($data[0], 0));
    $credit = $paid + $lnx->emcLNX__compute_allowance($paid) - $data[2];
    $cpa_addr = ""; // No CPA address by default

    if(!empty($ref_id)) {
      $stmt = $dbh->prepare("Select temperature, TIME_TO_SEC(TIMEDIFF(NOW(), last_event)) as dt, cpa_addr from hoster_shares where ref_id=? for update");
      $stmt->execute(array($ref_id));
      $ref_row = $stmt->fetchAll(PDO::FETCH_ASSOC);
      if(sizeof($ref_row)) {
        $dt   = $ref_row[0]['dt'];
        $temp = $ref_row[0]['temperature'] * exp(-$dt / $conf['RatingTAU']) + (($data[1] > 0)? 1 : 0);

	if($temp > $conf['max_ref_temp'] && $data[1] > 0) {
	  // Set zero-reuqest, if temp too high; Rolback req_sent and temperature for this host
          $stmt = $dbh->prepare("Update hoster_hosts Set req_sent=req_sent-?,temperature=? where host=?");
          $stmt->execute(array($data[1], $data[3], $host));
	  $data[2] -= $data[1]; $data[1] = 0;
	}

        $pay_req = ($credit < -$data[1] - 0.01)? 0 : $data[1];
        $cpa_addr = $ref_row[0]['cpa_addr'];
        if(empty($cpa_addr)) {
          $cpa_addr = $lnx->emcLNX__req('getnewaddress', array($conf['wallet']['account']));
          $stmt = $dbh->prepare("Update hoster_shares Set req_sent=req_sent+?, temperature=?, last_event=Now(), cpa_addr=? where ref_id=?");
          $stmt->execute(array($pay_req, $temp, $cpa_addr, $ref_id));
	} else {
          $stmt = $dbh->prepare("Update hoster_shares Set req_sent=req_sent+?, temperature=?, last_event=Now() where ref_id=?");
          $stmt->execute(array($pay_req, $temp, $ref_id));
	}
      }
    } // if(!empty($ref_id)) 

    $data[2] -= $paid; // Net balance for this advertiser: (req_sent - payment_received)

    if($ip)
      $dbh->commit();
    else {
      $dbh->rollBack();
      // echo "Debug mode, DB is not changed\n";
    }
    // - for testing ---  $dbh->rollBack();
    // format: Pay_addr:this_amount:balance:allowance_credit [:CPA_ADDRESS]
    $data[3] = sprintf(":%.2f", $credit); // rolback_temp->credit
    $rc = $row_contract['URL'][0] . $nvs_key . ':'. join(':', $data);
    if(!empty($cpa_addr)) 
      $rc .= ':' . $cpa_addr;
    return $rc;
  } catch(Exception $ex) {
    echo "emcLNX_lnx_ref error: ". $ex->getMessage() . "\n";
    $lnx->Log("\tlnx_ref ERR:\t" . $ex->getMessage());
    if($data) { // transaction was started in emcLNX__updRating successfully
      $dbh->rollBack();
      $stmt = $dbh->prepare("Delete from hoster_contracts where nvs_key=?");
      $stmt->execute(array($nvs_key));
    }
    return 0;
  }
} // function rand_href

//------------------------------------------------------------------------------
// MAIN here
// Request from NVS list of contracts
$conref = $lnx->GetQS();
$ip = $lnx->GetIP();
$result = emcLNX_lnx_ref($conref, $ip);
$lnx->Log("\tlnx_ref($conref) OUT:\t" . $result);
if($result) { 
  if(!$ip) echo "Res: $result\n";
  else header("Location: $result"); /* Redirect browser */
}
else echo "Cannot generate location link\n";

//------------------------------------------------------------------------------

?>
