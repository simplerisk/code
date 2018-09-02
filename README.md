# Build

```
docker build -t simplerisk:test .
```

# Setup Database
```
docker run -p 3306:3306 --name simplerisk-mariadb -e MYSQL_ROOT_PASSWORD=my-secret-pw -d mariadb
```

# Start docker container for web client
```
docker run --link simplerisk-mariadb:db -p 80:80 --name simplerisk-php -e DB_HOSTNAME=db -e DB_USERNAME=root -e DB_PASSWORD=my-secret-pw simplerisk:test
```

Visit http://localhost/ to test
