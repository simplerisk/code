FROM php:5-apache

ENV DB_HOST db
ENV DB_PORT 3306
ENV MYSQL_USERNAME simplerisk
ENV MYSQL_PASSWORD simplerisk
ENV MYSQL_DATABASE simplerisk
ENV USE_DATABASE_FOR_SESSIONS true

RUN docker-php-ext-install mysqli pdo_mysql

ADD simplerisk /var/www/html
ADD docker-entrypoint.sh /docker-entrypoint.sh
COPY config.env.php /var/www/html/includes/config.php

RUN chmod 755 /*.sh
RUN chmod -R 755 /var/www/html

RUN a2enmod rewrite

CMD ["/docker-entrypoint.sh"]