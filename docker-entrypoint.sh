#!/bin/bash
sed -i "s/__DB_HOST__/$DB_HOST/g" /var/www/html/includes/config.php
sed -i "s/__DB_PORT__/$DB_PORT/g" /var/www/html/includes/config.php
sed -i "s/__MYSQL_DATABASE__/$MYSQL_DATABASE/g" /var/www/html/includes/config.php
sed -i "s/__MYSQL_USER__/$MYSQL_USER/g" /var/www/html/includes/config.php
sed -i "s/__MYSQL_PASSWORD__/$MYSQL_PASSWORD/g" /var/www/html/includes/config.php
sed -i "s/__USE_DATABASE_FOR_SESSIONS__/$USE_DATABASE_FOR_SESSIONS/g" /var/www/html/includes/config.php

source /etc/apache2/envvars
tail -F /var/log/apache2/* &
exec apache2 -D FOREGROUND