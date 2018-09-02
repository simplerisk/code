FROM php:5-apache

ENV DB_HOSTNAME localhost
ENV DB_PORT 3306
ENV DB_USERNAME simplerisk
ENV DB_PASSWORD simplerisk
ENV DB_DATABASE simplerisk
ENV USE_DATABASE_FOR_SESSIONS true
ADD simplerisk /var/www/html
ADD docker-entrypoint.sh /docker-entrypoint.sh
COPY config.env.php /var/www/html/includes/config.php
# Replace config
RUN chmod 755 /*.sh
RUN chmod -R 755 /var/www/html

CMD ["/docker-entrypoint.sh"]