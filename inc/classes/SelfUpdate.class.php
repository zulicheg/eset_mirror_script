<?php

/**
 * Class SelfUpdate
 */
class SelfUpdate
{
    /**
     * @var
     */
    static private $list_to_update;

    /**
 * @var
 */
    static private $CONF;

    /**
     * @return bool
     */
    static public function is_need_to_update()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        return !empty(static::$list_to_update);
    }

    /**
     * @return array
     */
    static private function get_hashes_from_server()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        $content = Tools::download_file(
            array(
                CURLOPT_URL => "http://" . static::$CONF['server'] . "/" . static::$CONF['dir'] . "/" . static::$CONF['file'],
                CURLOPT_PORT => static::$CONF['port'],
                CURLOPT_RETURNTRANSFER => 1),
            $headers);
        $arr = [];

        if (preg_match_all("/(.+)=(.+)=(.+)/", $content, $result, PREG_OFFSET_CAPTURE))
            foreach ($result[1] as $num => $res)
                $arr[trim($result[1][$num][0])] = [$result[2][$num][0], $result[3][$num][0]];

        return $arr;
    }

    /**
     * @param string $directory
     * @return array
     */
    static private function get_hashes_from_local($directory = "./")
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        $hashes = [];
        $d = dir($directory);

        while (false !== ($entry = $d->read())) {
            if (($entry == '.') || ($entry == '..') || ($entry == '.git') || ($entry == 'log') || ($entry == 'www'))
                continue;

            (is_dir($directory . $entry)) ?
                $hashes = array_merge(static::get_hashes_from_local($directory . $entry . DS), $hashes)
                :
                $hashes[str_replace(DS, "/", $directory . $entry)] = [
                    md5_file($directory . $entry),
                    filesize($directory . $entry)
                ];
        }
        $d->close();
        return $hashes;
    }

    /**
     * @return string
     */
    static public function get_version_on_server()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        $response = Tools::download_file(
            [
                CURLOPT_URL => "http://" . static::$CONF['server'] . "/" . static::$CONF['dir'] . "/" . static::$CONF['version'],
                CURLOPT_PORT => static::$CONF['port'],
                CURLOPT_RETURNTRANSFER => 1
            ],
            $headers);
        return trim($response);
    }

    /**
     *
     */
    static public function start_to_update()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);

        foreach (static::$list_to_update as $filename => $info) {
            $fs_filename = str_replace("/", DS, str_replace("./", "", $filename));
            $remote_full_path = sprintf("http://%s/%s/%s", static::$CONF['server'], static::$CONF['dir'], $filename);
            Log::write_log(Language::t("Downloading %s [%s Bytes]", basename($filename), $info), 0);
            Tools::download_file(
                [
                    CURLOPT_URL => $remote_full_path,
                    CURLOPT_PORT => static::$CONF['port'],
                    CURLOPT_FILE => $fs_filename
                ],
                $headers);

            if (is_string($headers))
                //Log::write_log(Language::t("Error while downloading file %s [%s]", basename($filename), $headers), 0);
                throw new SelfUpdateException("Error while downloading file %s [%s]", basename($filename), $headers);
        }
    }

    /**
     * @throws SelfUpdateException
     */
    static public function init()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);

        if (!file_exists(CONF_FILE))
            throw new SelfUpdateException("Config file does not exist!");

        if (!is_readable(CONF_FILE))
            throw new SelfUpdateException("Can't read config file! Check the file and its permissions!");

        static::$CONF = (parse_ini_file(CONF_FILE, true))['SELFUPDATE'];
        $remote_hashes = static::get_hashes_from_server();
        $local_hashes = static::get_hashes_from_local();

        foreach ($remote_hashes as $filename => $info)
            if (!isset($local_hashes[$filename]) || $local_hashes[$filename][0] !== $remote_hashes[$filename][0])
                static::$list_to_update[$filename] = $info[1];
    }

    /**
     * @return bool
     */
    static public function ping()
    {
        return Tools::ping(static::$CONF['server'], static::$CONF['port']);
    }

    /**
     * @param $nm
     * @return mixed|null
     */
    static function get($nm)
    {
        return isset(static::$CONF[$nm]) ? static::$CONF[$nm] : null;
    }
}
