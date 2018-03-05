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

$VERSION = '20180302 [Freedom for Ukraine by harmless]';

@define('DS', DIRECTORY_SEPARATOR);
@define('SELF', dirname(__DIR__) . DS);
@define('INC', SELF . "inc" . DS);
@define('CLASSES', INC . "classes" . DS);
@define('PATTERN', SELF . "patterns" . DS);
@define('CONF_FILE', SELF . "nod32ms.conf");
@define('LANGPACKS_DIR', SELF . 'langpacks' . DS);
@define('DEBUG_DIR', SELF . 'debug' . DS);
@define('KEY_FILE_VALID', 'nod_keys.valid');
@define('KEY_FILE_INVALID', 'nod_keys.invalid');
@define('LOG_FILE', 'nod32ms.log');
@define('SUCCESSFUL_TIMESTAMP', 'nod_lastupdate');
@define('LINKTEST', 'nod_linktest');
@define('DATABASES_SIZE', 'nod_databases_size');
@define('TMP_PATH', 'tmp' . DS);

$autoload = function($class){@include_once CLASSES . "$class.class.php";};
spl_autoload_register($autoload);

try {
    Tools::init();

    $try_self_update = function () {
        SelfUpdate::init();
        if (SelfUpdate::get('enable') > 0) {
            if (SelfUpdate::ping() === true) {
                if (SelfUpdate::is_need_to_update()) {
                    Log::informer(Language::t("New version is available on server [%s]!", SelfUpdate::get_version_on_server()), null, 0);

                    if (SelfUpdate::get('enable') > 1) {
                        SelfUpdate::start_to_update();
                        Log::informer(Language::t("Your script has been successfully updated to version %s!", SelfUpdate::get_version_on_server()), null, 0);
                        return 1;
                    }
                } else
                    Log::write_log(Language::t("You already have actual version of script! No need to update!"), 0);
            } else
                Log::write_log(Language::t("Update server is down!"), 0);
        }

        return 0;
    };
}

catch (ToolsException $e) {
    Log::write_log($e->getMessage(), 0);
}

catch (ConfigException $e) {
    Log::write_log($e->getMessage(), 0);
}

catch (SelfUpdateException $e) {
    Log::write_log($e->getMessage(), 0);
}

catch (phpmailerException $e) {
    Log::write_log($e->getMessage(), 0);
}
