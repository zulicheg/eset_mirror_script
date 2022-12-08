# Documentation
See [docs](/docs) folder

# ESET NOD32 Mirror Script
Script to create own eset mirror

# Requirements
- PHP
- nginx or other web-server

# Installations
- copy nod32ms.conf.%lang% -> nod32ms.conf
- edit lines in nod32ms.conf

# If you have valid login:password
- set them into log/nod_keys.valid in format login:password:version

# Run
- run php update.php

# Debuging
- set in nod32ms.conf log_level = 5
- run php debuging_run.php to see all messages at console

# PHP modules
- curl
- fileinfo
- iconv
- mbstring
- openssl
- pcre
- SimpleXML
- sockets
- zlib
