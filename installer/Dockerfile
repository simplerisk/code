FROM alpine

ENV DB_HOST db
ENV DB_PORT 3306
ENV MYSQL_USERNAME simplerisk
ENV MYSQL_PASSWORD simplerisk
ENV MYSQL_DATABASE simplerisk
ENV MYSQL_ROOT_PASSWORD simplerisk
ENV WAIT_LOOPS 25
ENV WAIT_SLEEP 3
RUN apk add --no-cache mysql-client
RUN apk add --no-cache wget
RUN apk add --no-cache tar

ADD docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod 755 /*.sh

CMD ["/docker-entrypoint.sh"]