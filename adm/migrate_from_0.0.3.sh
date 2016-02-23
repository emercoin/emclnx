#!/bin/sh
/usr/bin/mysql -u emclnx -p <<EOF
  Use emclnx;

  Create table hoster_shares (
    ref_id	char(31),
    req_sent    float NOT NULL default 0,
    temperature	float NOT NULL default 0,
    last_event	timestamp NOT NULL default '2000-01-01 00:00:00',
    paid	float NOT NULL default 0,
    created	timestamp NOT NULL default '2000-01-01 00:00:00',
    status	int NOT NULL default 0,
    primary key (ref_id)
  );

EOF

