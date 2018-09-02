#!/bin/sh
wget -O installer.tgz https://github.com/simplerisk/installer/blob/master/simplerisk-installer-20180830-001.tgz?raw=true
tar -xvzf installer.tgz
cd install
mysql  --host=${DB_HOST} --user=root --password=${MYSQL_ROOT_PASSWORD} -e "CREATE DATABASE $MYSQL_DATABASE"
mysql  --host=${DB_HOST} --user=root --password=${MYSQL_ROOT_PASSWORD} -e "CREATE USER $MYSQL_USER IDENTIFIED BY '$MYSQL_PASSWORD'"
mysql  --host=${DB_HOST} --user=root --password=${MYSQL_ROOT_PASSWORD} -e "GRANT ALL PRIVILEGES ON $MYSQL_DATABASE.* TO '$MYSQL_USER'@'%' WITH GRANT OPTION";

for filename in db/*.sql; do
    mysql --host=${DB_HOST} --user=root --password=${MYSQL_ROOT_PASSWORD} --database=${MYSQL_DATABASE} < "$filename" 
done