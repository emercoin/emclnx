#!/usr/bin/php
<?php
//------------------------------------------------------------------------------
// EMCLNX - EmerCoin Link Exchange system
// Distributed under BSD license
// https://en.wikipedia.org/wiki/BSD_licenses
// Designed by maxihatop, EmerCoin group
// WEB: http://www.emercoin.com
// Contact: team@emercoin.com

require_once('../emclnx/emclnx.php');

$lnx = new emcLNX();
$dbh = $lnx->GetDbh();

// MAIN here
// Request from NVS list of contracts
$lnx_list = $lnx->emcLNX__req('name_filter', array('^lnx:', 1000000000));

# Cleanup keywords table
$dbh->exec("Delete from hoster_keywords");

foreach($lnx_list as $contract) {
  $lnx->emcLNX__process_contract($contract, 0); // new contract, contractID unassigned yet
} // foreach - contract

# Delete my own contracts - temporary disabled
$dbh->exec("Delete from hoster_contracts where nvs_key IN(Select nvs_key from payer_contracts)");

// Renumber contractID 1..N
$dbh->exec("ALTER TABLE hoster_contracts DROP contractID");
$dbh->exec("ALTER TABLE hoster_contracts ADD contractID INT NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (contractID), AUTO_INCREMENT=1");

//------------------------------------------------------------------------------

?>
