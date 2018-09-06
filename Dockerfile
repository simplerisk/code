FROM php:5-apache

ENV DB_HOST db
ENV DB_PORT 3306
ENV MYSQL_USERNAME simplerisk
ENV MYSQL_PASSWORD simplerisk
ENV MYSQL_DATABASE simplerisk
ENV USE_DATABASE_FOR_SESSIONS true
RUN apt-get update && \
	apt-cache search libzip && \ 
    apt-get install -y --no-install-recommends \
		libzip-dev libzip4 zip unzip git \
&& rm -rf /var/lib/apt/lists/*

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('SHA384', 'composer-setup.php') === '544e09ee996cdf60ece3804abc52599c22b1f40f4323403c44d44fdfdd586475ca9813a858088ffbc1f233e9b180f061') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php --install-dir=/bin --filename=composer && \
    php -r "unlink('composer-setup.php');"
RUN docker-php-ext-install mysqli pdo_mysql zip

ADD simplerisk /var/www/html
ADD docker-entrypoint.sh /docker-entrypoint.sh
COPY config.env.php /var/www/html/includes/config.php
WORKDIR /var/www/html
RUN composer install --prefer-dist
RUN chmod 755 /*.sh
RUN chmod -R 755 /var/www/html

RUN a2enmod rewrite

CMD ["/docker-entrypoint.sh"]