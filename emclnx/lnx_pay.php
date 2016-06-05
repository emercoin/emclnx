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

$payment_tx = "NONE";
$dest_url = $conf['dest_url']; // default destination URL

//------------------------------------------------------------------------------
// Invoice structure like: 
// HashCoins:EQ6DKLnBAbHxUvGTyjuAhQwVruZ7SvXHbR:0.91:7.6:-6.61
// There is:
// ContractName : PayTo address : Pay for this Click : Balance : Credit(allowance)
// returns 0 or error text
function emcLNX_lnx_pay($invoice, $ip, $quality) {
  global $lnx, $dbh, $conf, $payment_tx, $dest_url;

  $lnxRatingTAU = $conf['RatingTAU'];
  $lnxK4RatingSIGMA = $conf['K4RatingSIGMA'];
  $lnxCPCExtra = $conf['cpc_extra'];
  $lnxTempTreshold = $conf['max_temp'];
  $lnxQualityTreshold = $conf['min_quality'];
  $lnxIPTreshold = $conf['IPTreshold'];

  try {
    list($nvs_key, $pay_addr, $pay_this, $balance, $credit, $cpa_addr) = preg_split('/:/', $invoice, 6);

    if(!isset($credit))
      throw new Exception("Wrong invoice syntax\n");

    $iptemp = $lnx->emcLNX__IPTemperature($ip, ($pay_this > 0)? 1 : 0);

    $rc = 0;
    // Fetch CPC and increase visit_cnt
    $stmt = $dbh->prepare("Select cpc, dest_url from payer_contracts where nvs_key=?");
    $stmt->execute(array($nvs_key));
    $cpc_row = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if(empty($cpc_row))
      return "Contract $nvs_key is missing"; // We do not pay this contract
    $cpc = $cpc_row[0]['cpc'];
    if(!empty($cpc_row[0]['dest_url']))
      $dest_url = $cpc_row[0]['dest_url'];

    // Attach CPA address, if needed and exist
    $dest_url = preg_replace('/\$CPA_ADDR/', $cpa_addr, $dest_url);

    $stmt = $dbh->prepare("Update payer_contracts Set visit_cnt = visit_cnt + 1 where nvs_key=?");
    $stmt->execute(array($nvs_key));

    // Insert default record, if not exist
    $stmt = $dbh->prepare("Insert ignore into payer_hosts (pay_addr) values(?)");
    $stmt->execute(array($pay_addr));

    $dbh->beginTransaction();
    $q = "Select rating, temperature, TIME_TO_SEC(TIMEDIFF(NOW(), last_event)) as dt, " .
         " pay_balance, quality, pay_sent" .
         " from payer_hosts where pay_addr=? for update";
    $stmt = $dbh->prepare($q);
    $stmt->execute(array($pay_addr));
    $hoster_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Update credentials by formulas
    $dt       = $hoster_rows[0]['dt'];
    $temp     = $hoster_rows[0]['temperature'] * exp(-$dt / $conf['RatingTAU']);
    $rating   = $hoster_rows[0]['rating'];
    $actual_cpc = 0;
    
    if($pay_this > 0) {
      // this is real user, so invoice is proceed
      $temp   += 1 + log(1 + $cpc);
      $rating += 1 / $temp;
      // Set Actual CPC - adjust with Rating and routemp
      $actual_cpc = $cpc * $rating / ($rating + $conf['K4RatingSIGMA']);
    }

    $quality   += $hoster_rows[0]['quality'] * exp(-$dt / $lnxRatingTAU);
    $routemp    = $iptemp <= 1.0? 1.0 : sqrt($iptemp);
    $actual_cpc = sprintf("%.2f", $actual_cpc / $routemp); 

    // Set up initial payment/ignored values
    $actual_pay = $actual_ignore = 0.0;
    $db_balance = $hoster_rows[0]['pay_balance'];

    // Check restrictions
    do {

      if($pay_this > $actual_cpc + $lnxCPCExtra) {
	$quality -= $pay_this - $actual_cpc;
        $pay_this = $actual_cpc + $lnxCPCExtra;
      }
      
      $limbal = $db_balance + $lnxCPCExtra + $pay_this;
      if($balance > $limbal) {
//	$quality -= $balance - $limbal;
        $balance = $limbal;
      }

      if($quality < $lnxQualityTreshold) {
        $actual_ignore = $pay_this;
        $rc = "Too low quality=$quality < $lnxQualityTreshold";
        break; // Hoster asks quick and many
      }
 
      if($iptemp > $lnxIPTreshold) {
        $rc = "Too high IP temperature=$iptemp > $lnxIPTreshold";
        $actual_ignore = $pay_this;
        break; // Seems like fraudster activity
      }
      
      if($temp > $lnxTempTreshold) {
        $actual_ignore = $pay_this;
        $rc = "Too high hoster temperature=$temp > $lnxTempTreshold";
        break; // Hoster asks quick and many
      }
       
      // Minimal trusted balance - min(request_bal, $limbal); Can be negative!
      $balance = sprintf("%.2f", $balance); // round to cents

      if($credit < $actual_cpc * 3 && $balance > 0) {
        $actual_pay = sprintf("%.2f", max($pay_this, $balance));
      }

    } while(0);

    if($actual_pay > 0) {
      $overpay = sprintf("%.2f", $lnx->emcLNX__compute_allowance($hoster_rows[0]['pay_sent']));
      $actual_pay += $overpay;
      // echo "B=$balance O=$overpay a=$actual_pay\n";
      // Send payment to hoster
      $payment_tx = $lnx->emcLNX__req('sendtoaddress', array($pay_addr, floatval($actual_pay), "emcLNX $nvs_key"));
      $db_balance = -$overpay; // payoff balance
    } else // If no pay - just accept the new balance
      if(!$rc && $balance > $db_balance)
        $db_balance = $balance; // we agreed with this new balance only if no err, and skip fale low req_bal

    $q = "Update payer_hosts Set rating=?, temperature=?," .
         " last_event=Now(), visit_cnt=visit_cnt + 1, " .
         " pay_balance=?, pay_sent=pay_sent+?, pay_ignored=pay_ignored+?, quality=?" .
         " where pay_addr=?";
    $stmt = $dbh->prepare($q);
    $stmt->execute(array($rating, $temp, 
                         $db_balance, $actual_pay, $actual_ignore, $quality,
                         $pay_addr));

    $dbh->commit();
    // $dbh->rollBack();
    return $rc? "Contract $nvs_key hoster=$pay_addr: $rc" : 0; 
  } catch(Exception $ex) {
    return "emcLNX_lnx_pay error: ". $ex->getMessage();
  }
} // function rand_href

//------------------------------------------------------------------------------
// MAIN here
// Request from NVS list of contracts

$invoice = $lnx->GetQS();
$ip = $lnx->GetIP();

$rc = emcLNX_lnx_pay($invoice, $ip, 0);

$lnx->Log("\tlnx_pay($invoice) OUT:\t$rc; TX=$payment_tx" );

if($ip)
  header("Location: " . $dest_url); /* Redirect browser */
else
  echo "Res=$rc; TX=$payment_tx dest=$dest_url\n";

//------------------------------------------------------------------------------

?>
