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
        $content = Tools::download_file(array(CURLOPT_URL => "http://" . SELFUPDATE_SERVER . "/" . SELFUPDATE_DIR . "/" . SELFUPDATE_FILE, CURLOPT_PORT => SELFUPDATE_PORT, CURLOPT_RETURNTRANSFER => 1));
        $arr = array();

        if (preg_match_all("/(.+)=(.+)=(.+)/", $content, $result, PREG_OFFSET_CAPTURE))
            foreach ($result[1] as $num => $res)
                $arr[trim($result[1][$num][0])] = array($result[2][$num][0], $result[3][$num][0]);

        return $arr;
    }

    /**
     * @param string $directory
     * @return array
     */
    static private function get_hashes_from_local($directory = "./")
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        $hashes = array();
        $d = dir($directory);

        while (false !== ($entry = $d->read())) {
            if (($entry == '.') || ($entry == '..') || ($entry == '.git') || ($entry == 'log') || ($entry == 'www'))
                continue;

            (is_dir($directory . $entry)) ?
                $hashes = array_merge(static::get_hashes_from_local($directory . $entry . DS), $hashes)
                :
                $hashes[str_replace(DS, "/", $directory . $entry)] = array(md5_file($directory . $entry), filesize($directory . $entry));
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
        $response = Tools::download_file(array(CURLOPT_URL => "http://" . SELFUPDATE_SERVER . "/" . SELFUPDATE_DIR . "/" . SELFUPDATE_NEW_VERSION, CURLOPT_PORT => SELFUPDATE_PORT, CURLOPT_RETURNTRANSFER => 1));
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
            $remote_full_path = sprintf("http://%s/%s/%s", SELFUPDATE_SERVER, SELFUPDATE_DIR, $filename);
            Log::write_log(Language::t("Downloading %s [%s Bytes]", basename($filename), $info), 0);
            $status = Tools::download_file(array(CURLOPT_URL => $remote_full_path, CURLOPT_PORT => SELFUPDATE_PORT, CURLOPT_FILE => $fs_filename));

            if (is_string($status))
                Log::write_log(Language::t("Error while downloading file %s [%s]", basename($filename), $status), 0);
        }

        global $SELFUPDATE_POSTFIX;

        foreach ($SELFUPDATE_POSTFIX as $file) {
            $out = str_replace("/", DS, $file);
            Tools::download_file(array(CURLOPT_URL => "http://" . SELFUPDATE_SERVER . "/" . SELFUPDATE_DIR . "/" . $file, CURLOPT_PORT => SELFUPDATE_PORT, CURLOPT_FILE => $out));
        }
    }

    /**
     *
     */
    static public function init()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        $remote_hashes = static::get_hashes_from_server();
        $local_hashes = static::get_hashes_from_local();

        foreach ($remote_hashes as $filename => $info)
            if (!isset($local_hashes[$filename]) || $local_hashes[$filename][0] !== $remote_hashes[$filename][0])
                static::$list_to_update[$filename] = $info[1];
    }
}
