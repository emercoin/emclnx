<head>
	<meta charset="UTF-8">
</head>

<body>
<h2>Demo page for emcLNX system</h2>
<p>
Reload this page for ads rotation.
</p>

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
require_once('emclnx.php');
$lnx = new emcLNX();

echo $lnx->GetRand_href(120, 'Test'); // Max length of ads text string and optional - ref_id (can be empty or omitted)

//------------------------------------------------------------------------------

?>
</body>

