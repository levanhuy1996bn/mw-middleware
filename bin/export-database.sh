#!/bin/sh

echo "The database dump may take some time.  If you would like to execute this script, comment the line below this message.";
exit 1;

CURRENT_SCRIPT_DIR=$(cd -P -- "$(dirname -- "$0")" && pwd -P);
cd $CURRENT_SCRIPT_DIR;
cd ../;

# Determine sizes of database tables:
# SELECT table_schema as `Database`, table_name AS `Table`, round(((data_length + index_length) / 1024 / 1024), 2) `Size in MB` FROM information_schema.TABLES ORDER BY (data_length + index_length) DESC;

# https://gist.github.com/joshisa/297b0bc1ec0dcdda0d1625029711fa24

dsn="$(cat ./.env.local | grep DATABASE_URL | sed -e's,DATABASE_URL=\(.*\),\1,g')";

proto="$(echo $dsn | grep :// | sed -e's,^\(.*://\).*,\1,g')";
# remove the protocol
url="$(echo ${dsn/$proto/})";
# extract the user (if any)
userpass="$(echo $url | grep @ | cut -d@ -f1)";
pass="$(echo $userpass | grep : | cut -d: -f2)";
if [ -n "$pass" ]; then
  user="$(echo $userpass | grep : | cut -d: -f1)";
else
  user=$userpass;
fi;

# extract the host
host="$(echo ${url/$userpass@/} | cut -d/ -f1)";
# by request - try to extract the port
port="$(echo $host | sed -e 's,^.*:,:,g' -e 's,.*:\([0-9]*\).*,\1,g' -e 's,[^0-9],,g')";
host="$(echo ${host/:$port/} | cut -d/ -f1)";
# extract the path (if any)
path="$(echo $url | grep / | cut -d/ -f2-)";

database="$(echo $path | grep ? | cut -d? -f1)";

#echo "url: $url";
#echo "  proto: $proto";
#echo "  user: $user";
#echo "  pass: $pass";
#echo "  host: $host";
#echo "  port: $port";
#echo "  path: $path";
#echo "  database: $database";

#mysql  -u $user -p$pass -h $host -P $port $database;

mysqldump \
  --ignore-table=$database.middleware_log \
  -u $user -p$pass -h $host -P $port $database > export.sql;
mysqldump --no-data -u $user -p$pass -h $host -P $port $database \
  middleware_log \
  >> export.sql;

now="$(date +"%Y%m%d_%H%M%S")"
filename="$(echo $database)_$now.sql";

mv export.sql $filename;
