#!/bin/sh
/usr/bin/mysql -u emclnx -p <<EOF
  Use emclnx;
  Alter table hoster_shares ADD cpa_addr char(35) NOT NULL after ref_id;

EOF
