# INSTALATION

## Pre-requisites

Before you begin be sure you have access to:

1. A webserver capable of running PHP
2. PHP version must be at leasr 5.5
3. You must have read write access to the place where you plan to storage your files
4. Git installed on your computer. And possibly a GUI to make your life easier


## Download the software

1. Use the software of your choice to clone de repository. The url is: https://github.com/ffquintella/lessrisk.git


## Setup the database

1. Create a database called lessrisk (or what ever you choose to name it)
2. Run the script located into the db folder (en for english version bp for Brazilian Portuguease)
3. Create a user (defaul is lessrisk) and give it a password
4. Give the user full read write update delete rights on that database
5. Edit the file /webapp/includes/database.php and place your configuration in there (for advanced configuration reade the dbAddappter instructions)

## Setup the environment

1. Use composer to install the required software: go to webapp folder; run php ../composer.phar install
2. Use composer to create the autoload: In the same folder; run php ../composer.phar dump-autoload

## Deploy

1. Copy all the files on the webapp to the root of your file server