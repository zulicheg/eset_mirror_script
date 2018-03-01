<?php

chdir(__DIR__ . "/..");

$DIRECTORIES = array(
    3 => 'eset_upd',
    4 => 'eset_upd/v4',
    5 => 'eset_upd/v5',
    6 => 'eset_upd/v6',
    7 => 'eset_upd/v7',
    8 => 'eset_upd/v8',
    9 => 'eset_upd/v9',
    10 => 'eset_upd/v10',
    11 => 'eset_upd/v11',
);

$SELFUPDATE_POSTFIX = array(
    "changelog.rus",
    "changelog.eng",
);

define('DS', DIRECTORY_SEPARATOR);
define('VERSION', '20180301 [Freedom for Ukraine by harmless]');
define('SELF', dirname(__DIR__) . DS);
define('INC', SELF . "inc" . DS);
define('CLASSES', INC . "classes" . DS);
define('PATTERN', SELF . "patterns" . DS);
define('CONF_FILE', SELF . "nod32ms.conf");
define('LANGPACKS_DIR', 'langpacks');
define('DEBUG_DIR', 'debug');
define('KEY_FILE_VALID', 'nod_keys.valid');
define('KEY_FILE_INVALID', 'nod_keys.invalid');
define('LOG_FILE', 'nod32ms.log');
define('SUCCESSFUL_TIMESTAMP', 'nod_lastupdate');
define('LINKTEST', 'nod_linktest');
define('DATABASES_SIZE', 'nod_databases_size');
define('TMP_PATH', 'tmp');
define('SELFUPDATE_SERVER', "eset.contra.net.ua");
define('SELFUPDATE_PORT', "2221");
define('SELFUPDATE_DIR', "nod32ms");
define('SELFUPDATE_FILE', "files.md5");
define('SELFUPDATE_NEW_VERSION', "version.txt");
define("CONNECTTIMEOUT", 5); # Seconds

$autoload = function ($class)
{
    @include_once CLASSES . "$class.class.php";
};

spl_autoload_register($autoload);

$try_self_update = function () {
    if (($err = Config::init(CONF_FILE)) || ($err = Language::init(Config::get('default_language'))) || ($err = Language::t(Config::check_config()))) {
        Log::write_log(Language::t($err), 0);
        exit;
    }

    @ini_set('memory_limit', Config::get('memory_limit'));

    if ($level = Config::get('self_update') > 0) {
        if (Tools::ping(SELFUPDATE_SERVER, SELFUPDATE_PORT) === true) {
            SelfUpdate::init();

            if (SelfUpdate::is_need_to_update()) {
                Log::informer(Language::t("New version is available on server [%s]!", SelfUpdate::get_version_on_server()), null, 0);

                if ($level > 1) {
                    SelfUpdate::start_to_update();
                    Log::informer(Language::t("Your script has been successfully updated to version %s!", SelfUpdate::get_version_on_server()), null, 0);
                    return 1;
                }
            } else
                Log::write_log(Language::t("You already have actual version of script! No need to update!"), 0);
        } else
            Log::write_log(Language::t("Update server is down!"), 0);
    }

    return 1;
};

