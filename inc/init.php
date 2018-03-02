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

@define('DS', DIRECTORY_SEPARATOR);
@define('SELF', dirname(__DIR__) . DS);
@define('INC', SELF . "inc" . DS);
@define('CLASSES', INC . "classes" . DS);
@define('PATTERN', SELF . "patterns" . DS);
@define('CONF_FILE', SELF . "nod32ms.conf");
@define('LANGPACKS_DIR', SELF . 'langpacks');

$autoload = function($class){@include_once CLASSES . "$class.class.php";};
spl_autoload_register($autoload);

$try_self_update = function () {
    if (($err = Config::init(CONF_FILE)) || ($err = Language::init(Config::get('default_language'))) || ($err = Language::t(Config::check_config()))) {
        Log::write_log(Language::t($err), 0);
        exit;
    }

    @ini_set('memory_limit', Config::get('memory_limit'));

    if (($level = Config::get('self_update')) > 0) {
        if (SelfUpdate::ping() === true) {
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

