#!/usr/bin/env bash

# Taken from phan's internal/make_phar. See history on github for attribution.
# File distributed under the MIT license

set -xeu

if [[ ! -d "src" ]]; then
	echo "Run this script from the root"
	exit 1
fi

composer install --classmap-authoritative --prefer-dist --no-dev
rm -rf build
mkdir build
php -d phar.readonly=0 internal/make_phar.php
chmod a+x build/taint-check.phar
php build/taint-check.phar --version
