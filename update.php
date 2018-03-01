<?php

require __DIR__ . "/inc/init.php";

if (try_self_update()) {
    spl_autoload_unregister('autoload');
    require __DIR__ . "/inc/init.php";
}

$nod32ms = new Nod32ms();
