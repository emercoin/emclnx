#!/bin/sh
#MYSQL_PWD=12345 /usr/bin/mysql -u emclnx <<EOF
/usr/bin/mysql -u emclnx -p <<EOF
  Use emclnx;
  Alter table payer_contracts ADD dest_url varchar(128) NOT NULL after nvs_key;
EOF

