<?php

require __DIR__ . "/inc/init.php";

if ($try_self_update()) {
    spl_autoload_unregister($autoload);
    unset($autoload, $try_self_update);
    require __DIR__ . "/inc/init.php";
}

if (($err = Config::init()) || ($err = Language::init()) || ($err = Language::t(Config::check_config()))) {
    Log::write_log(Language::t($err), 0);
    exit;
}

@ini_set('memory_limit', Config::get('SCRIPT')['memory_limit']);

$nod32ms = new Nod32ms();
