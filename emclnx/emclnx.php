<?php
//--------------------------------------------------------------
// EMCLNX - EmerCoin Link Exchange system
// Distributed under BSD license
// https://en.wikipedia.org/wiki/BSD_licenses
// Designed by maxihatop, EmerCoin group
// WEB: http://www.emercoin.com
// Contact: team@emercoin.com


// error_reporting(E_ALL);
error_reporting(E_ERROR | E_PARSE);
//------------------------------------------------------------------------------
class emcLNX {
  protected $config;
  protected $dbh;

  public function __construct() {
    // Return ERR 403 at pre-fetch attempt, do nothing
    if (
         (isset($_SERVER['HTTP_X_MOZ'])) && ($_SERVER['HTTP_X_MOZ'] == 'prefetch')
      || (isset($_SERVER['HTTP_X_PURPOSE'])) && ($_SERVER['HTTP_X_PURPOSE'] == 'preview')
    ) {
      // This is a prefetch request. Block it.
      header('HTTP/1.0 403 Forbidden');
      echo '403: Forbidden<br><br>Prefetching not allowed here.';
      die();
    }

    // Load config
    $this->config = require('emclnx-config.php');

    //Connecting to MySQL db
    $db_opt = array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION );

    $this->dbh = new PDO(
      $this->config['db']['db_host'] . ";dbname=" . $this->config['db']['db_name'], 
      $this->config['db']['db_user'], 
      $this->config['db']['db_pass'], 
      $db_opt);
    $this->dbh->query('SET NAMES "utf8"');
  } // constructor

  //  ++++ Internal/adm/utils methods ++++

  //------------------------------------------------------------------------------
  // Get functions for emcLNX adm-utils
  public function GetCoinfig() { return $this->config; }
  public function GetDbh()     { return $this->dbh;    }

  //------------------------------------------------------------------------------
  // Performs name_show NVS-request to EMC wallet
  public function emcLNX__req($cmd, $params) {
    // Prepares the request
    $request = json_encode(array(
      'method' => $cmd,
      'params' => $params,
      'id' => '1'
    ));
    // Prepare and performs the HTTP POST
    $opts = array ('http' => array (
      'method'  => 'POST',
      'header'  => 'Content-type: application/json',
      'content' => $request
    ));
    $fp = fopen($this->config['wallet']['url'], 'rb', false, stream_context_create($opts));
    if(!$fp)
      throw new Exception('emcLNX__req: Unable to connect to EMC-wallet');

    $rc = json_decode(stream_get_contents($fp), true);
    $er = $rc['error'];
    if(!is_null($er))
      throw new Exception('emcLNX__req: Wallet response error: ' . $er);

    return $rc['result'];
  } // emcLNX__req

  //------------------------------------------------------------------------------
  // Extract hostname from tokenized NVS and lowercase it
  public function emcLNX__getHost($tokens) {
    return strtolower(parse_url($tokens['URL'][0])['host']);
  }

  //------------------------------------------------------------------------------
  // Tokenize contract body
  protected function emcLNX__tokenize_contract_body($contract) {
    $tokens = array();
    foreach(explode(PHP_EOL, $contract['value']) as $val_line)
      if(preg_match('/^(\w+)\s*=\s*(.+)\s*/', $val_line, $tok)) {
        if(!array_key_exists($tok[1], $tokens))
          $tokens[$tok[1]] = array();
        array_push($tokens[$tok[1]], trim($tok[2]));
      }
    return $tokens;
  } // tokenize_contract_body

  //------------------------------------------------------------------------------
  // Process contract from NVS
  // $contractID:
  // 0 = Full load, from fetch_contracts
  // N = ContractID for update
  //
  // Returns:
  // =0 - wrong contract, need ignore
  // array of tokens - good contract
  public function emcLNX__process_contract($contract, $contractID) {
    if($contract['expires_in'] <= 0)
      return 0; // Skip expired contracts

    $contract_name = preg_replace('/^lnx:/', '', $contract['name']);

    $tokens = $this->emcLNX__tokenize_contract_body($contract);

    $lnxLANGS = $this->config['langs'];
    $lnxCOUNTRY = $this->config['country']; 

    if(!array_key_exists('CPC', $tokens)) {
      if($contractID == 0)
        echo "Ignored $contract_name: CPC is not specified\n";
      return 0; // Inclomplete contract
    }
    if(!array_key_exists('URL', $tokens)) {
      if($contractID == 0)
        echo "Ignored $contract_name: URL is not specified\n";
      return 0; // Inclomplete contract
    }

    $host = $this->emcLNX__getHost($tokens);
    if(empty($host)) {
      if($contractID == 0)
        echo "Ignored $contract_name: Host is not specified\n";
      return 0; // Invalid host name
    }
    // Verify domain signature
    $ver = 0;
    foreach(dns_get_record($host, DNS_TXT) as $dnstxt) 
      if(preg_match('/^emclnx=(\w+)/', $dnstxt['txt'], $dn_addr)) {
        $ver |= 1024;
        if(!array_key_exists('SIGNATURE', $tokens)) {
          if($contractID == 0)
            echo "Ignored $contract_name: Domain signature is missing when required " . $dn_addr[1] . "\n";
          return 0; // domain signature is missing when required;
        }
        try {
          $ver |= $this->emcLNX__req('verifymessage', array($dn_addr[1], $tokens['SIGNATURE'][0], $contract_name));
        } catch(Exception $ex) { 
          if($contractID == 0)
            echo "Ignored $contract_name: Cannot verifymessage " . $dn_addr[1] . " " . $tokens['SIGNATURE'][0] . " $contract_name\n";
          return 0; 
        };
      } // foreach + preg_match

    if($ver == 1024)
      return 0; // cannot verifymessage;

   // Extract CPC and compare to Treshold
   $cpc = $tokens['CPC'][0];

    // Filters only for bulk fetch new records
    if($contractID == 0) {
      if($cpc < $this->config['min_cpc']) {
        echo "Ignored $contract_name: CPC=$cpc < Treshold=". $this->config['min_cpc'] . "\n";
        return 0; // Too few CPC value - skip this contract
      }
      // Country process
      if(array_key_exists('COUNTRY', $tokens) && sizeof($lnxCOUNTRY)) {
        $countries = preg_split('/\s*,\s*/', $tokens['COUNTRY'][0], NULL, PREG_SPLIT_NO_EMPTY);
        foreach($countries as $country) 
          if(array_key_exists($country, $lnxCOUNTRY))
            goto country_OK;
       echo "Ignored $contract_name: No any country match\n";
       return 0; // No any country match
      }
      country_OK:
    } //  if($use_filter)

    // Initial Raw weight
    $rw = log($cpc / $this->config['min_cpc']) * $this->config['K2'];
    $lang = $tokens['LANG'][0];

    // Add weight from the language
    if(!empty($lnxLANGS)) {
      if(!array_key_exists($lang, $lnxLANGS))
      return 0; // We do not support this language
      $rw += $lnxLANGS[$lang];
    }

    // Adjust raw weight with keywords
    $txt_lines = sizeof($tokens['TXT']);
    foreach($tokens['TXT'] as $rawtxt) {
      $lctxt = strtolower($rawtxt);    
      foreach($this->config['keywords'] as $k => $w) { 
        if(strpos($lctxt, $k) !== FALSE) 
          $rw += $w / $txt_lines;
      }
    } // TXT from contract process

    // Sigmoid priority [-1..+1]
    $prio = $rw / ($this->config['K1'] + abs($rw));

    // Prepare array of keywords
    $keywords_sql = array();
    if($contractID == 0) {    
      if(empty($tokens['KEYWORDS']) && $this->config['use_txt_as_keywords']) {
        $sep = '/\s+/';
        $keywords_tokens = $tokens['TXT'];
      } else {
        $sep = '/\s*|\s*/';
        $keywords_tokens = $tokens['KEYWORDS'];
      }
      foreach($keywords_tokens as $rawtxt) {
        foreach(preg_split($sep, strtolower($rawtxt)) as $keyword) {
          $keywords_sql[] = '("' . mysql_real_escape_string($keyword)       . '", "' 
                                 . mysql_real_escape_string($contract_name) . '")';
        } // KEYWORD line process
      } // KEYWORD from contract process
    } // if($contractID == 0)

    try {
      $this->dbh->beginTransaction();
      // 1. Insert host (domain)
      // ReqADDR wll be inserted during lookup, by lazy algorithm
      $stmt = $this->dbh->prepare("INSERT IGNORE INTO hoster_hosts(`host`) VALUES(?)");
       $stmt->execute(array($host));
      // 2. Insert this contract into hoster_contracts
      if($contractID <= 0) {
        $stmt = $this->dbh->prepare("REPLACE INTO hoster_contracts SET nvs_key=?, host=?, priority=?, cpc=?");
        $stmt->execute(array($contract_name, $host, $prio, $cpc));
        if(!empty($keywords_sql))
          $this->dbh->exec('REPLACE INTO hoster_keywords (keyword, nvs_key) VALUES ' . implode(',', $keywords_sql));
      } else {
        $stmt = $this->dbh->prepare("REPLACE INTO hoster_contracts SET nvs_key=?, host=?, priority=?, cpc=?, contractID=?");
        $stmt->execute(array($contract_name, $host, $prio, $cpc, $contractID));
      }
      $this->dbh->commit();
      if($contractID == 0)
        echo "Load $contract_name: Host=$host; Pri=$prio; lines=$txt_lines\n";
      return $tokens;
    } catch(PDOException $ex) {
      //Something went wrong rollback!
      $this->dbh->rollBack();
      echo "emcLNX__process_contract error: ". $ex->getMessage() . "\n";
      return 0;
    }
  } // process_contract


//------------------------------------------------------------------------------
// Copmute Credit allowance for this advertiser
  public function emcLNX__compute_allowance($received) {
    return $this->config['max_credit'] * $received / ($this->config['credit_sigma'] + $received);
  } // compute_allowance

//------------------------------------------------------------------------------
// Compute and update temperature from client IP
  public function emcLNX__IPTemperature($ip, $inc) {
    if(!$ip) 
      return 0; // debug mode
    try {
      $rmd = unpack('S1', md5($ip . $this->config['wallet']['url'], true))[1];
      $this->dbh->exec("Insert ignore into routemp(`route_no`) values($rmd)");

      $this->dbh->beginTransaction();
      $stmt = $this->dbh->prepare("Select temperature, TIME_TO_SEC(TIMEDIFF(NOW(), last_event)) as dt"
                       ." from routemp where route_no=? for update");
      $stmt->execute(array($rmd));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $dt   = $rows[0]['dt'];
      $temp = $rows[0]['temperature'] * exp(-$dt / $this->config['RatingTAU']) + $inc;
  
      // Can pass temp/rmd unsafe, since fota are my own number vars 
      $this->dbh->exec("Update routemp Set temperature=$temp, last_event=Now() where route_no=$rmd");

      $this->dbh->commit();
      return $temp;
    } catch(PDOException $ex) {
      //Something went wrong rollback!
      $this->dbh->rollBack();
      echo "emcLNX__IPTemperature error: " . $ex->getMessage() . "\n";
      return 0;
    }
  } // emcLNX__IPTemperature

  // +++++ Public methods ++++

  //------------------------------------------------------------------------------
  // Param - maximal length of the ads text string. 128 by default
  public function GetRand_href($maxoutlen, $ref_id) {
    $q = "Select contractID, nvs_key, host from hoster_contracts" . 
         " where contractID = round(1 + rand() * ((Select max(contractID) from hoster_contracts) - 1))" .
         " and priority > rand() - " . $this->config['rand_offset'];
    if(!isset($maxoutlen))
      $maxoutlen = 128;
    // Counter for unsucessfull attempts during search apropriate contract
    // runs up to $lnx_max_attempts = 45
    $attempt = 0;
    for( ; ; ) {
      // used as random row contract and text of ADS; if 0 -> no contract found
      $row_contract = 0;
      // Extract random contract - candidate by probabilitic mechainsm
      do {
        if(++$attempt > $this->config['max_attempts'])
          return 0; // Cannot found anything
        try {
          foreach($this->dbh->query($q) as $row0) 
            $row_contract = $row0;
        } catch(PDOException $ex) {}
      } while(!$row_contract);

      // Fetch fresh contract - it can be changed since 1st insert, or gone
      $nvs_key = $row_contract['nvs_key'];
      $contractID = $row_contract['contractID'];
      try {
        $fresh_contract = $this->emcLNX__req('name_show', array('lnx:' . $nvs_key));
        if($fresh_contract['expires_in'] <= 0)
          throw new Exception("contract expired: $nvs_key");
      } catch(Exception $ex) {
        $this->dbh->exec("Delete from hoster_contracts where contractID=$contractID");    
        continue;
      }

      // Update DB with fresh contract data and adjust score, if needed
      $row_contract = $this->emcLNX__process_contract($fresh_contract, $contractID);
      if(!$row_contract)
        continue; // Wrong contract
      // Check CPC in a new contract - maybe, it changed down
      $cpc = $row_contract['CPC'][0];
      if($cpc < $this->config['min_cpc']) {
        continue; // Too few CPC value - skip this contract
      }

      // Check balance of advertiser - maybe, his credit limit is over
      $host = $this->emcLNX__getHost($row_contract);
      try {
        $stmt = $this->dbh->prepare("Select req_addr, req_sent from hoster_hosts where host=?");
        $stmt->execute(array($host));
        $hoster_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch(Exception $ex) {
        continue;
      }

      $req_sent = $hoster_rows[0]['req_sent'];
      $req_addr = $hoster_rows[0]['req_addr'];
      if($req_sent > 0) {
        // Extract paid, and check balance < allowed
        $paid = $this->emcLNX__req('getreceivedbyaddress', array($req_addr, 0));
        $allow= $this->emcLNX__compute_allowance($paid);
        $bal  = $req_sent - $paid - $allow;
        if($bal * getrandmax() > $allow * rand())
          continue; // underpaid balance, skip this ads
      }
      // Contract found
      shuffle($row_contract['TXT']);
      try {
        $stmt = $this->dbh->prepare("Update hoster_hosts Set show_cnt = 1 + show_cnt where host=?");
        $stmt->execute(array($host));
      } catch(Exception $ex) {}

      if(!empty($ref_id)) 
        $nvs_key .= ':' . preg_replace('/\W/', '', $ref_id);
      return "<a href='" . $this->config['ref_url'] . $nvs_key . "'>" 
                         . htmlspecialchars(substr(utf8_decode($row_contract['TXT'][0]), 0, $maxoutlen))
                         . "</a>";
    } // main for loop
  } // function rand_href

  //------------------------------------------------------------------------------
  public function GetQS() {
    global $argv;
    return isset($_SERVER['QUERY_STRING'])? urldecode($_SERVER['QUERY_STRING']) : $argv[1];
  }

  //------------------------------------------------------------------------------
  public function GetIP() {
    if(isset($_SERVER["HTTP_CF_CONNECTING_IP"])) return $_SERVER["HTTP_CF_CONNECTING_IP"];
    if(isset($_SERVER["REMOTE_ADDR"])) return $_SERVER["REMOTE_ADDR"];
    return 0; // used at cmdline/debug mode
  }

  //------------------------------------------------------------------------------
  public function IsRobot() {
    // Check for direct link
    $reflen = isset($_SERVER['HTTP_REFERER'])? strlen($_SERVER['HTTP_REFERER']) : 0;
    if($this->config['zero_if_direct'] && $reflen < 3)
      return 1;
    return 0; // Not robot
  }

  //------------------------------------------------------------------------------
  public function Log($txt) {
  if($this->config['log_fname'])
    file_put_contents($this->config['log_fname'], date('Y-m-d H:i:s') . "\t" .  $this->GetIP() . "\t$txt\n", FILE_APPEND);
  }
} // class emcLNX

?>
