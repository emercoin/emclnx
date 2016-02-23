<?php
	//--------------------------------------------------------------
	// EMCLNX - EmerCoin Link Exchange system
	// Distributed under BSD license
	// https://en.wikipedia.org/wiki/BSD_licenses
	// Designed by maxihatop, EmerCoin group
	// WEB: http://www.emercoin.com
	// Contact: team@emercoin.com
	//------------------------------------------------------------------------------
	// MAIN here
	// Request from NVS list of contracts
	header('Access-Control-Allow-Origin: *');
	header('Content-Type: application/json');
	require_once('/home/www-data/dirs.info/emclnx/emclnx.php');
	$lnx = new emcLNX();
	$link= $lnx->GetRand_href(120); // Max length of ads text string
	if($link===0){
		echo "<a href='http://google.com'>empty</a>";
	} else {
		echo $link;
	}
	//------------------------------------------------------------------------------
	?>