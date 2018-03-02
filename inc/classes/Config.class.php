<?php

/**
 * Class Config
 */
class Config
{
    /**
     * @var
     */
    static private $CONF;

    /**
     * @var array
     */
    static private $LCID = array(
        'bgr' => 1026,
        'chs' => 2052,
        'cht' => 1028,
        'csy' => 1029,
        'dan' => 1030,
        'deu' => 1031,
        'enu' => 1033,
        'esl' => 13322,
        'esn' => 3082,
        'eti' => 1061,
        'fin' => 1035,
        'fra' => 1036,
        'frc' => 3084,
        'hrv' => 1050,
        'hun' => 1038,
        'ita' => 1040,
        'kor' => 1042,
        'lth' => 1063,
        'nld' => 1043,
        'nor' => 1044,
        'plk' => 1045,
        'ptb' => 1046,
        'rom' => 1048,
        'rus' => 1049,
        'sky' => 1051,
        'slv' => 1060,
        'sve' => 1053,
        'tha' => 1054,
        'trk' => 1055,
        'ukr' => 1058
    );

    /**
     * @param $filename
     * @return null|string
     */
    static public function init($filename)
    {
        if (!file_exists($filename)) return "Config file does not exist!";

        if (!is_readable($filename)) return "Can't read config file! Check the file and its permissions!";

        static::$CONF = parse_ini_file($filename, true);
        
        var_dump(static::$CONF);

        // Parse mirrors
        if (empty(static::$CONF['mirror'])) static::$CONF['mirror'] = 'update.eset.com';

        static::$CONF['mirror'] = array_map("trim", (explode(",", static::$CONF['mirror'])));

        // Convert string languages in array LCID
        $lang = explode(",", strtoupper(static::$CONF['update_version_lang']));
        sort($lang);
        static::$CONF['present_languages'] = implode(", ", array_map("trim", ($lang)));
        $languages = array();
        $langs = array_map("trim", (explode(",", strtolower(static::$CONF['update_version_lang']))));

        foreach ($langs as $key) $languages[] = static::$LCID[$key];

        static::$CONF['update_version_lang'] = $languages;

        // Convert update_version_filter string to pcre
        static::$CONF['update_version_filter'] = implode('|', array_map("trim", (explode(",", static::$CONF['update_version_filter']))));
        return null;
    }

    /**
     * @param $nm
     * @return mixed|null
     */
    static function get($nm)
    {
        return isset(static::$CONF[$nm]) ? static::$CONF[$nm] : null;
    }

    /**
     * @param $i
     * @return int
     */
    static public function upd_version_is_set($i)
    {
        return (isset(static::$CONF['update_version' . strval($i)]) ? static::$CONF['update_version' . strval($i)] : 0);
    }

    /**
     * @return string|null
     */
    static public function check_config()
    {
        if (array_search(PHP_OS, array("Darwin", "Linux", "FreeBSD", "OpenBSD", "WINNT")) === false)
            return "This script doesn't support your OS. Please, contact developer!";

        if (function_exists("date_default_timezone_set") and function_exists("date_default_timezone_get")) {
            if (empty(static::$CONF['SCRIPT']['timezone'])) {
                date_default_timezone_set(@date_default_timezone_get());
            } else {
                if (@date_default_timezone_set(static::$CONF['SCRIPT']['timezone']) === false) {
                    static::$CONF['LOG']['rotate'] = 0;
                    return "Error in timezone settings! Please, check your config file!";
                }
            }
        }

        if (static::$CONF['LOG']['rotate'] == 1) {
            if (preg_match_all("/([0-9]+)([BKMG])/i", static::$CONF['LOG']['rotate_size'], $result, PREG_PATTERN_ORDER)) {
                static::$CONF['LOG']['rotate_size'] = intval(trim($result[1][0]));

                if (count($result) != 3 || static::$CONF['LOG']['rotate_size'] < 1 || empty($result[1][0]) || empty($result[2][0]))
                    return "Please, check set up of rotate_size in your config file!";

                switch (trim($result[2][0])) {
                    case "g":
                    case "G":
                        static::$CONF['LOG']['rotate_size'] = static::$CONF['LOG']['rotate_size'] << 30;
                        break;
                    case "m":
                    case "M":
                        static::$CONF['log_rotate_size'] = static::$CONF['LOG']['rotate_size'] << 20;
                        break;
                    case "k":
                    case "K":
                        static::$CONF['LOG']['rotate_size'] = static::$CONF['LOG']['rotate_size'] << 10;
                        break;
                }
            }

            if (intval(static::$CONF['LOG']['rotate_qty']) < 1) {
                return "Please, check set up of rotate_qty in your config file!";
            } else {
                static::$CONF['LOG']['rotate_qty'] = intval(static::$CONF['LOG']['rotate_qty']);
            }

            if (intval(static::$CONF['LOG']['type']) < 0 || intval(static::$CONF['LOG']['type']) > 3)
                return "Please, check set up of type in your config file!";
        }

        if (empty(static::$CONF['SCRIPT']['web_dir']))
            return "Please, check set up of WWW directory in your config file!";

        while (substr(static::$CONF['SCRIPT']['web_dir'], -1) == DS)
            static::$CONF['SCRIPT']['web_dir'] = substr(static::$CONF['SCRIPT']['web_dir'], 0, -1);

        while (substr(static::$CONF['LOG']['dir'], -1) == DS)
            static::$CONF['LOG']['dir'] = substr(static::$CONF['LOG']['dir'], 0, -1);

        @mkdir(PATTERN, 0755, true);
        @mkdir(static::$CONF['LOG']['dir'], 0755, true);
        @mkdir(static::$CONF['SCRIPT']['web_dir'], 0755, true);
        @mkdir(Tools::ds(static::$CONF['SCRIPT']['web_dir'], TMP_PATH, 0755, true));

        if (static::$CONF['SCRIPT']['debug_html'] == 1)
            @mkdir(Tools::ds(static::$CONF['LOG']['dir'], DEBUG_DIR, 0755, true));

        if (static::$CONF['MAILER']['enable'] == 1) {
            if (empty(static::$CONF['MAILER']['sender']) ||
                strpos(static::$CONF['MAILER']['sender'], "@") === FALSE ||
                empty(static::$CONF['MAILER']['recipient']) ||
                strpos(static::$CONF['MAILER']['recipient'], "@") === FALSE
            )
                return "You didn't set up email address of sender/recipient or it is wrong.Please, check your config file.";

            if (static::$CONF['MAILER']['smtp'] == 1) {
                if (empty(static::$CONF['MAILER']['host']) ||
                    empty(static::$CONF['MAILER']['port'])
                )
                    return "Please, check SMTP host/port for using SMTP server in your config file.Or disable SMTP server if you don't wanna use it.";

                if (static::$CONF['MAILER']['auth'] == 1) {
                    if (empty(static::$CONF['MAILER']['login']) ||
                        empty(static::$CONF['MAILER']['password'])
                    )
                        return "Please, check login/password for using SMTP authorization.";
                }
            }
        }

        if (intval(static::$CONF['default_errors_quantity']) <= 0) static::$CONF['default_errors_quantity'] = 1;

        if (!is_readable(PATTERN))
            return "Pattern directory is not readable. Check your permissions!";

        if (!is_writable(static::$CONF['SCRIPT']['log_dir']))
            return "Log directory is not writable. Check your permissions!";

        if (!is_writable(static::$CONF['SCRIPT']['web_dir']))
            return "Web directory is not writable. Check your permissions!";

        // Link test
        $linktestfile = Tools::ds(static::$CONF['LOG']['dir'], LINKTEST);
        $test = false;
        $status = false;
        if (file_exists($linktestfile)) {
            $status = file_get_contents($linktestfile);

            if (preg_match("/link|fsutil|false/", $status)) $test = true;
        }
        if ($test == false) {
            file_put_contents(Tools::ds(static::$CONF['SCRIPT']['web_dir'], 'linktest'), '');

            if (
                function_exists('link') &&
                link(
                    Tools::ds(static::$CONF['SCRIPT']['web_dir'], 'linktest'),
                    Tools::ds(static::$CONF['SCRIPT']['web_dir'], 'linktest2')
                )
            ) {
                $status = 'link';
            } elseif (
                preg_match("/^win/i", PHP_OS) &&
                shell_exec(
                    sprintf(
                        "fsutil hardlink create %s %s",
                        Tools::ds(static::$CONF['SCRIPT']['web_dir'], 'linktest'),
                        Tools::ds(static::$CONF['SCRIPT']['web_dir'], 'linktest2'))
                ) != 0
            ) {
                $status = 'fsutil';
            } else $status = 'false';

            if ($status) unlink(Tools::ds(static::$CONF['SCRIPT']['web_dir'], 'linktest2'));

            unlink(Tools::ds(static::$CONF['SCRIPT']['web_dir'], 'linktest'));
            @file_put_contents($linktestfile, $status);
        }
        static::$CONF['create_hard_links'] = ($status != 'false' ? $status : false);

        return null;
    }
}
