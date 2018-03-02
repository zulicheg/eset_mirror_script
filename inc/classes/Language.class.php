<?php

/**
 * Class Language
 */
class Language
{
    /**
     * @var null
     */
    static private $language = null;
    /**
     * @var
     */
    static private $language_file = null;
    /**
     * @var
     */
    static private $language_pack = array();
    /**
     * @var
     */
    static private $default_language_pack = array();
    /**
     * @var
     */
    static private $default_language_file = null;

    /**
     * @return null|string
     */
    static public function init()
    {
        static::$language = Config::get('SCRIPT')['language'];
        static::$language_file = Tools::ds(LANGPACKS_DIR, static::$language . '.lng');
        static::$default_language_file = Tools::ds(LANGPACKS_DIR, 'en.lng');


        var_dump(static::$language);
        var_dump(static::$language_file);
        var_dump(static::$default_language_file);
        var_dump(static::$language_pack);

        if (static::$language != 'en') {
            if (!file_exists(static::$language_file))
                return sprintf("Language file [%s.lng] does not exist!", static::$language);
        } else return null;

        $tmp = file(static::$language_file);
        static::$default_language_pack = file(static::$default_language_file);

        if (count($tmp) != count(static::$default_language_pack))
            return sprintf("Language file [%s] is corrupted!", static::$language);

        for ($i = 0; $i < count($tmp); $i++) {
            static::$language_pack[trim($tmp[$i])] = trim(static::$default_language_pack[$i]);
        }

        var_dump(static::$default_language_pack);
        return null;
    }

    /**
     * @return string
     */
    static public function t()
    {
        $text = func_get_arg(0);
        $params = @array_shift(func_get_args());
        $key = array_search($text, static::$language_pack);
        var_dump($text);
        var_dump($params);
        var_dump($key);
        return ($key != FALSE) ? vsprintf($key, $params) : vsprintf($text, $params);
    }
}
