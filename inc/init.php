<?php

chdir(__DIR__ . "/..");

$DIRECTORIES = [
    3 => 'eset_upd',
    4 => 'eset_upd/v4',
    5 => 'eset_upd/v5',
    6 => 'eset_upd/v6',
    7 => 'eset_upd/v7',
    8 => 'eset_upd/v8',
    9 => 'eset_upd/v9',
    10 => 'eset_upd/v10',
    11 => 'eset_upd/v11',
];

$SELFUPDATE_POSTFIX = [
    "changelog.rus",
    "changelog.eng",
];

$CONSTANTS = [
    'DS' => DIRECTORY_SEPARATOR,
    'VERSION' => '20180301 [Freedom for Ukraine by harmless]',
    'SELF' => dirname(__DIR__) . $CONSTANTS['DS'],
    'INC' => $CONSTANTS['SELF'] . "inc" . $CONSTANTS['DS'],
    'CLASSES' => $CONSTANTS['INC'] . "classes" . $CONSTANTS['DS'],
    'PATTERN' => $CONSTANTS['SELF'] . "patterns" . $CONSTANTS['DS'],
    'CONF_FILE' => $CONSTANTS['SELF'] . "nod32ms.conf",
    'LANGPACKS_DIR' => 'langpacks',
    'DEBUG_DIR' => 'debug',
    'KEY_FILE_VALID' => 'nod_keys.valid',
    'KEY_FILE_INVALID' => 'nod_keys.invalid',
    'LOG_FILE' => 'nod32ms.log',
    'SUCCESSFUL_TIMESTAMP' => 'nod_lastupdate',
    'LINKTEST' => 'nod_linktest',
    'DATABASES_SIZE' => 'nod_databases_size',
    'TMP_PATH' => 'tmp',
    'SELFUPDATE_SERVER' => "eset.contra.net.ua",
    'SELFUPDATE_PORT' => "2221",
    'SELFUPDATE_DIR' => "nod32ms",
    'SELFUPDATE_FILE' => "files.md5",
    'SELFUPDATE_NEW_VERSION' => "version.txt",
    "CONNECTTIMEOUT" => 5
];

$autoload = function ($class)
{
    global $CONSTANTS;
    @include_once $CONSTANTS['CLASSES'] . "$class.class.php";
};

spl_autoload_register($autoload);

$try_self_update = function () {
    global $CONSTANTS;
    if (($err = Config::init($CONSTANTS['CONF_FILE'])) || ($err = Language::init(Config::get('default_language'))) || ($err = Language::t(Config::check_config()))) {
        Log::write_log(Language::t($err), 0);
        exit;
    }

    @ini_set('memory_limit', Config::get('memory_limit'));

    if ($level = Config::get('self_update') > 0) {
        if (Tools::ping($CONSTANTS['SELFUPDATE_SERVER'], $CONSTANTS['SELFUPDATE_PORT']) === true) {
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

