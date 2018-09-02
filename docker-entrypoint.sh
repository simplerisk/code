#!/bin/bash
sed -i "s/__DB_HOSTNAME__/$DB_HOSTNAME/g" /var/www/html/includes/config.php
sed -i "s/__DB_PORT__/$DB_PORT/g" /var/www/html/includes/config.php
sed -i "s/__DB_USERNAME__/$DB_USERNAME/g" /var/www/html/includes/config.php
sed -i "s/__DB_PASSWORD__/$DB_PASSWORD/g" /var/www/html/includes/config.php
sed -i "s/__USE_DATABASE_FOR_SESSIONS__/$USE_DATABASE_FOR_SESSIONS/g" /var/www/html/includes/config.php

source /etc/apache2/envvars
tail -F /var/log/apache2/* &
exec apache2 -D FOREGROUND