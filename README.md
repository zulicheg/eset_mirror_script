# News
We open a Telegram Channel [NOD32 Trial Keys](https://t.me/nod32trialKeys)

# ESET NOD32 Mirror Script
Script to create own eset mirror

# Requirements
- PHP 5.6+ or 7+
- unrar
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

# Cron simple job
@hourly **[path to php]** **[path to update.php]**


[]: https://t.me/nod32trialKeys
