#!/bin/sh
/usr/bin/mysql -u emclnx -p <<EOF
  Use emclnx;

  Alter table hoster_contracts ADD cpc float NOT NULL default 0 after host;

  Create table hoster_keywords (
    keyword	varchar(128) NOT NULL,
    nvs_key     varchar(128) NOT NULL,
    primary key (keyword, nvs_key)
  );

EOF
