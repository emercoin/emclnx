<?php
return [
//--------------------------------------------------------------
// EMCLNX - EmerCoin Link Exchange system version=0.0.6
// Distributed under BSD license
// https://en.wikipedia.org/wiki/BSD_licenses
// Designed by maxihatop, EmerCoin group
// WEB: http://www.emercoin.com
// Contact: team@emercoin.com

//--------------------------------------------------------------
// Ordinary config values
// Change according your needs
//

  // as specified in your emercoin.conf configuration file
  'wallet' => [
    'url'	=> "http://user:password@localhost:6662",
    'account'	=> "emcLNX"
  ],

  // Wallet SSL connection params, if used https above
  'ssl'     => [
    // 'cafile'		=> ""
    'verify_peer'	=> false,
    'verify_peer_name'	=> false
  ],

  // MySQL db connection params
  'db'     => [
    'db_host'	=> "mysql:host=localhost",
    'db_user'	=> "emclnx",
    'db_pass'	=> "12345",
    'db_name'	=> "emclnx"
  ],

  // Contract priority adjustment - keywords
  // If used non-ASCII charset, encode this file into UTF-8
  'keywords' => [
    'mining'	=>	+10,
    'coins' 	=>	+5,
    'sex'	=>	-100
  ],

  // Contract priority adjustment - languages
  // weight of the LANG as same as from keywords
  'langs' => [
    'EN'	=>	40,
    'RU'	=>	10,
    'JP' 	=>	-10,
    'default'	=>	-50
  ],

  // Accepted country filter. By default, allow any
  // If you would like activate country filter, add country code here
  'country' => [
//    'EN'	=>	1,
//    'RU'	=>	1
  ],

  // Minimal contract value (CPC)
  'min_cpc'	=> 0.02,

  // Referer URL location
  'ref_url'	=> "/emclnx/lnx_ref.php?",

  // Destination site after payer
  // This is your site, fere emc_pay will refer user
  'dest_url'	=> "http://www.emercoin.com/",

  // User-specific quality treshold
  'min_quality' => -1,

  // Log file name
  'log_fname'   => '/var/log/emclnx.log', 

  // If no keywords, use TXT, splitted by SPACE
  'use_txt_as_keywords' => 1,

  // Zero invoice if direct link entered. Temperature, etc is updated
  'zero_if_direct'	=> 1,

//--------------------------------------------------------------
// Change these values, if you understamding, what are you doing
//

  // K1 - constant for Sigmoid for rate lnxKEYWORDS
  'K1'		=> 100.0,

  // K2 - constant for mix contract value CPC into rating
  'K2'		=> 100.0,

  // Offset for match rand search [0..1]
  'rand_offset' => 0.25,

  // Maximal attempts number for extract rand_href
  'max_attempts' => 50,

  // Max credit allowance for advertisers, EMC ($100)
  'max_credit'	=> 500.0,

  // Sigmoid for allowance constant
  // Credit reach 1/2 of max_credit, if received this value
  'credit_sigma' => 5000.0,

  // Extra allowance to pay - for possible desynch rating
  'cpc_extra'	=> 0.02,

  // Connection temperature theshold
  'max_temp'	=> 1000,

  // Referer temperature theshold
  'max_ref_temp'	=> 500,

//--------------------------------------------------------------
// DON'T CHANGE these values, otherwise you'll lost 
// compatibility with peers
//

  // Sigmoid for rating update; will be 1/2 after N far events
  'K4RatingSIGMA' => 20,

  // Temperature tau decay = 1 week
  'RatingTAU'	=> 7 * 24 * 3600,

  // IP barrier for fraud protection
  // 40% c2h5oh in a vodka :)
  'IPTreshold' => 40 // ~5 clicks per week

]; // return config

?>
