#!/bin/sh
MYSQL_PWD=12345 /usr/bin/mysql -u emclnx <<EOF
  Use emclnx;


  Create table payer_contracts (
    nvs_key	varchar(128) NOT NULL,
    dest_url	varchar(128) NOT NULL,
    cpc		float NOT NULL default 0,
    visit_cnt	int NOT NULL default 0,
    primary key	(nvs_key)
  );


  Create table payer_hosts (
    pay_addr	char(35) NOT NULL,
    pay_balance	float NOT NULL default 0,
    pay_sent	float NOT NULL default 0,
    pay_ignored	float NOT NULL default 0,
    rating	float NOT NULL default 0,
    temperature	float NOT NULL default 0,
    last_event	timestamp NOT NULL default '2000-01-01 00:00:00',
    visit_cnt	int NOT NULL default 0,
    quality	float NOT NULL default 0,
    primary key (pay_addr)
  );


  Create table  routemp (
    route_no	smallint unsigned NOT NULL default 0,
    last_event  timestamp NOT NULL default '2000-01-01 00:00:00',
    temperature float NOT NULL default 0,
    primary key (route_no)
  );



  Create table hoster_hosts (
    host	varchar(255) NOT NULL,
    req_addr	char(35) NOT NULL,
    req_sent	float NOT NULL default 0,
    rating	float NOT NULL default 0,
    temperature	float NOT NULL default 0,
    last_event	timestamp NOT NULL default '2000-01-01 00:00:00',
    show_cnt	int NOT NULL default 0,
    click_cnt	int NOT NULL default 0,
    primary key (host)
  );

  Create table hoster_contracts (
    contractID	int NOT NULL AUTO_INCREMENT,
    priority	float NOT NULL default 0,
    nvs_key	varchar(128) NOT NULL,
    host	varchar(255) NOT NULL,
    cpc		float NOT NULL default 0,
    primary key (contractID),
    unique	(nvs_key),
    index using hash(nvs_key, contractID)
  );

  Create table hoster_shares (
    ref_id	char(31),
    cpa_addr    char(35) NOT NULL,
    req_sent    float NOT NULL default 0,
    temperature	float NOT NULL default 0,
    last_event	timestamp NOT NULL default '2000-01-01 00:00:00',
    paid	float NOT NULL default 0,
    created	timestamp NOT NULL default '2000-01-01 00:00:00',
    status	int NOT NULL default 0,
    primary key (ref_id)
  );


  Create table hoster_keywords (
    keyword	varchar(128) NOT NULL,
    nvs_key     varchar(128) NOT NULL,
    primary key (keyword, nvs_key)
  );

EOF

