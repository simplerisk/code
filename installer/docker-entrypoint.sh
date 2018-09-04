#!/bin/sh

file="simplerisk-en.sql"
if [ -d "$file" ]
then
	echo "$file found, exit"
else
    #wait for mysql
    i=0
    while ! nc $DB_HOST $DB_PORT >/dev/null 2>&1 < /dev/null; do
    i=`expr $i + 1`
    if [ $i -ge $WAIT_LOOPS ]; then
        echo "$(date) - ${DB_HOST}:${DB_PORT} still not reachable, giving up"
        exit 1
    fi
    echo "$(date) - waiting for ${DB_HOST}:${DB_PORT}"
    sleep $WAIT_SLEEP
    done

    echo "database is available"
	echo "$file not found, executing script"
    wget -O simplerisk-en.sql https://raw.githubusercontent.com/simplerisk/database/master/simplerisk-en-20180830-001.sql
    #wget -O installer.tgz https://github.com/simplerisk/installer/blob/master/simplerisk-installer-20180830-001.tgz?raw=true
    #tar -xvzf installer.tgz
    #cd install
    mysql --host=${DB_HOST} --user=root --password=${MYSQL_ROOT_PASSWORD} -e "CREATE DATABASE $MYSQL_DATABASE"
    mysql --host=${DB_HOST} --user=root --password=${MYSQL_ROOT_PASSWORD} -e "CREATE USER $MYSQL_USER IDENTIFIED BY '$MYSQL_PASSWORD'"
    mysql --host=${DB_HOST} --user=root --password=${MYSQL_ROOT_PASSWORD} -e "GRANT ALL PRIVILEGES ON $MYSQL_DATABASE.* TO '$MYSQL_USER'@'%' WITH GRANT OPTION";
    mysql --host=${DB_HOST} --user=root --password=${MYSQL_ROOT_PASSWORD} --database=${MYSQL_DATABASE} < simplerisk-en.sql 
    #for filename in db/*.sql; do
    #    mysql --host=${DB_HOST} --user=root --password=${MYSQL_ROOT_PASSWORD} --database=${MYSQL_DATABASE} < "$filename" 
    #done
    echo "done, exit"
fi
