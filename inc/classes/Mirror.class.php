<?php

/**
 * Class Mirror
 */
class Mirror
{
    /**
     * @var int
     */
    static public $total_downloads = 0;

    /**
     * @var
     */
    static public $version = null;

    /**
     * @var
     */
    static public $dir = null;

    /**
     * @var
     */
    static public $mirror_dir = null;

    /**
     * @var array
     */
    static public $mirrors = array();

    /**
     * @var array
     */
    static public $key = array();

    /**
     *
     */
    static private function fix_time_stamp()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $fn = Tools::ds(Config::get('log_dir'), SUCCESSFUL_TIMESTAMP);
        $timestamps = array();

        if (file_exists($fn)) {
            $handle = file_get_contents($fn);
            $content = Parser::parse_line($handle, false, "/(.+:.+)\n/");

            if (isset($content) && count($content)) {
                foreach ($content as $value) {
                    $result = explode(":", $value);
                    $timestamps[$result[0]] = $result[1];
                }
            }
        }

        $timestamps[static::$version] = time();
        @unlink($fn);

        foreach ($timestamps as $key => $name)
            Log::write_to_file(SUCCESSFUL_TIMESTAMP, "$key:$name\r\n");
    }

    /**
     * @return bool
     */
    static public function test_key()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        Log::write_log(Language::t("Testing key [%s:%s]", static::$key[0], static::$key[1]), 4, static::$version);

        foreach (Config::get('mirror') as $mirror) {
            $tries = 0;
            $quantity = Config::get('default_errors_quantity');

            while (++$tries <= $quantity) {
                if ($tries > 1)
                    usleep(CONNECTTIMEOUT * 1000000);

                return (preg_match("/401/", @get_headers("http://" . static::$key[0] . ":" . static::$key[1] . "@$mirror/v3-rel-sta/mod_021_horus_13117/em021_32_n9.nup")[0])) ? false : true;
            }
        }

        return false;
    }

    /**
     *
     */
    static public function find_best_mirrors()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $test_mirrors = array();
        $options = array(
            CURLOPT_CONNECTTIMEOUT => CONNECTTIMEOUT,
            CURLOPT_HEADER => false,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_USERPWD => static::$key[0] . ":" . static::$key[1]
        );

        if (Config::get('proxy_enable') !== 0) {
            $options[CURLOPT_PROXY] = Config::get('proxy_server');
            $options[CURLOPT_PROXYPORT] = Config::get('proxy_port');

            if (Config::get('proxy_user') !== NULL) {
                $options[CURLOPT_PROXYUSERNAME] = Config::get('proxy_user');
                $options[CURLOPT_PROXYPASSWORD] = Config::get('proxy_passwd');
            }
        }

        if (function_exists('curl_multi_init')) {
            $master = curl_multi_init();

            foreach (Config::get('mirror') as $mirror) {
                $ch = curl_init();
                $url = "http://" . $mirror . "/" . static::$mirror_dir . "/update.ver";
                $options[CURLOPT_URL] = $url;
                curl_setopt_array($ch, $options);
                curl_multi_add_handle($master, $ch);
            }

            do {
                $run = curl_multi_exec($master, $running);
                curl_multi_select($master);

                while ($done = curl_multi_info_read($master)) {
                    $ch = $done['handle'];
                    $info = curl_getinfo($ch);
                    $url = parse_url($info['url']);
                    if ($info['http_code'] == 200) {
                        $test_mirrors[$url['host']] = round($info['total_time'] * 1000);
                        Log::write_log(Language::t("Mirror %s active", $url['host']), 3, static::$version);
                    } else {
                        Log::write_log(Language::t("Mirror %s inactive", $url['host']), 3, static::$version);
                    }
                    curl_multi_remove_handle($master, $ch);
                    curl_close($ch);
                }
            } while ($running && $run === CURLM_OK);
            curl_multi_close($master);
        } else {
            foreach (Config::get('mirror') as $mirror) {
                $ch = curl_init();
                $url = "http://" . $mirror . "/" . static::$mirror_dir . "/update.ver";
                $options[CURLOPT_URL] = $url;
                curl_setopt_array($ch, $options);
                curl_exec($ch);
                $info = curl_getinfo($ch);
                $url = parse_url($info['url']);
                if ($info['http_code'] == 200) {
                    $test_mirrors[$url['host']] = round($info['total_time'] * 1000);
                    Log::write_log(Language::t("Mirror %s active", $url['host']), 3, static::$version);
                } else {
                    Log::write_log(Language::t("Mirror %s inactive", $url['host']), 3, static::$version);
                }
                curl_close($ch);
            }
        }
        asort($test_mirrors);

        foreach ($test_mirrors as $mirror => $time)
            static::$mirrors[] = array('host' => $mirror, 'db_version' => static::check_mirror($mirror));
    }

    /**
     * @param $mirror
     * @return int|null
     */
    static public function check_mirror($mirror)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $new_version = null;
        $file = Tools::ds(Tools::ds(Config::get('web_dir'), TMP_PATH, static::$mirror_dir), 'update.ver');
        Log::write_log(Language::t("Checking mirror %s with key [%s:%s]", $mirror, static::$key[0], static::$key[1]), 4, static::$version);
        static::download_update_ver($mirror);
        $new_version = Tools::get_DB_version($file);
        @unlink($file);

        return $new_version;
    }

    /**
     * @param $mirror
     */
    static public function download_update_ver($mirror)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $tmp_path = Tools::ds(Config::get('web_dir'), TMP_PATH, static::$mirror_dir);
        @mkdir($tmp_path, 0755, true);
        $archive = Tools::ds($tmp_path, 'update.rar');
        $extracted = Tools::ds($tmp_path, 'update.ver');
        $header = Tools::download_file("http://" . static::$key[0] .":" . static::$key[1] . "@$mirror/" . static::$mirror_dir . "/update.ver", $archive);

        if (is_array($header) and $header['http_code'] == 200) {
            if (preg_match("/text/", $header['content_type'])) {
                rename($archive, $extracted);
            } else {
                Log::write_log(Language::t("Extracting file %s to %s", $archive, $tmp_path), 5, static::$version);
                Tools::extract_file($archive, $tmp_path);
                @unlink($archive);
            }
        }
    }

    /**
     * @return array
     */
    static public function download_signature()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        static::download_update_ver(current(static::$mirrors)['host']);
        $dir = Config::get('web_dir');
        $cur_update_ver = Tools::ds($dir, static::$mirror_dir, 'update.ver');
        $tmp_update_ver = Tools::ds($dir, TMP_PATH, static::$mirror_dir, 'update.ver');
        $content = @file_get_contents($tmp_update_ver);
        $start_time = microtime(true);
        preg_match_all('#\[\w+\][^\[]+#', $content, $matches);
        $total_size = null;
        $average_speed = null;

        if (!empty($matches)) {
            // Parse files from .ver file
            list($new_files, $total_size, $new_content) = static::parse_update_file($matches[0]);

            // Create hardlinks/copy file for empty needed files (name, size)
            list($download_files, $needed_files) = Tools::create_links($dir, $new_files, static::$version);

            // Download files
            if (!empty($download_files)) {
                static::download_files($download_files);
            }

            // Delete not needed files
            $del_files = 0;
            foreach (glob(Tools::ds($dir, static::$dir), GLOB_ONLYDIR) as $file) {
                $del_files = Tools::del_files($file, $needed_files);
                if ($del_files > 0)
                    Log::write_log(Language::t("Deleted files: %s", $del_files), 3, static::$version);
            }

            // Delete empty folders
            $del_folders = 0;
            foreach (glob(Tools::ds($dir, static::$dir), GLOB_ONLYDIR) as $folder) {
                $del_folders = Tools::del_folders($folder);
                if ($del_folders > 0)
                    Log::write_log(Language::t("Deleted folders: %s", $del_folders), 3, static::$version);
            }

            Tools::create_dir(dirname($cur_update_ver));
            @file_put_contents($cur_update_ver, $new_content);

            Log::write_log(Language::t("Total size database: %s", Tools::bytesToSize1024($total_size)), 3, static::$version);

            if (count($download_files) > 0) {
                $average_speed = round(static::$total_downloads / (microtime(true) - $start_time));
                Log::write_log(Language::t("Total downloaded: %s", Tools::bytesToSize1024(static::$total_downloads)), 3, static::$version);
                Log::write_log(Language::t("Average speed: %s/s", Tools::bytesToSize1024($average_speed)), 3, static::$version);
            }

            if (count($download_files) > 0 || $del_files > 0 || $del_folders > 0) static::fix_time_stamp();
        } else {
            Log::write_log(Language::t("Error while parsing update.ver from %s", current(static::$mirrors)['host']), 3, static::$version);
        }
        @unlink($tmp_update_ver);
        return array($total_size, static::$total_downloads, $average_speed);
    }

    /**
     * @return false|string
     */
    static public function exp_nod()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $NodProduct = "eav";
        $NodVer = "7.0.302.8";
        $NodLang = "419";
        $SysVer = "5.1";
        $ProdCode = "6A";
        $Platform = "Windows";

        $hash = "";
        $Cmap = array("Z", "C", "B", "M", "K", "H", "F", "S", "Q", "E", "T", "U", "O", "X", "V", "N");
        $Cmap2 = array("Q", "A", "P", "L", "W", "S", "M", "K", "C", "D", "I", "J", "E", "F", "B", "H");
        $i = 0;
        $length = strlen(static::$key[1]);

        while ($i <= 7 And $i < $length) {
            $a = Ord(static::$key[0][$i]);
            $b = Ord(static::$key[1][$i]);

            if ($i >= strlen(static::$key[0]))
                $a = 0;

            $f = (2 * $i) << ($b & 3);
            $h = $b ^ $a;
            $g = ($h >> 4) ^ ($f >> 4);
            $hash .= $Cmap2[$g];
            $m = ($h ^ $f) & 15;
            $hash .= $Cmap[$m];
            ++$i;
        }

        $j = 0;
        $lengthUser = strlen(static::$key[0]);

        while ($j <= $lengthUser - 1) {
            $k = ord(static::$key[0][$j]);
            $hash .= $Cmap[($k >> 4)];
            $hash .= $Cmap2[($k & 15)];
            ++$j;
        }

        $xml = '<?xml version="1.0" encoding="utf-8"?>
  <GETLICEXP>
  <SECTION ID="1000103">
  <LICENSEREQUEST>
  <NODE NAME="UsernamePassword" VALUE="' . $hash . '" TYPE="STRING" />
  <NODE NAME="Product" VALUE="' . $NodProduct . '" TYPE="STRING" />
  <NODE NAME="Version" VALUE="' . $NodVer . '" TYPE="STRING" />
  <NODE NAME="Language" VALUE="' . $NodLang . '" TYPE="DWORD" />
  <NODE NAME="UpdateTag" VALUE="" TYPE="STRING" />
  <NODE NAME="System" VALUE="' . $SysVer . '" TYPE="STRING" />
  <NODE NAME="EvalInfo" VALUE="0" TYPE="DWORD" />
  <NODE NAME="ProductCode" VALUE="' . $ProdCode . '" TYPE="DWORD" />
  <NODE NAME="Platform" VALUE="' . $Platform . '" TYPE="STRING" />
  </LICENSEREQUEST>
  </SECTION>
  </GETLICEXP>';

        $options = array(
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => CONNECTTIMEOUT,
            CURLOPT_HEADER => 'Content-type: application/x-www-form-urlencoded',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => 'http://expire.eset.com/getlicexp',
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $xml,
        );

        if (Config::get('download_speed_limit') !== 0) {
            $options[CURLOPT_MAX_RECV_SPEED_LARGE] = Config::get('download_speed_limit');
        }

        if (Config::get('proxy_enable') !== 0) {
            $options[CURLOPT_PROXY] = Config::get('proxy_server');
            $options[CURLOPT_PROXYPORT] = Config::get('proxy_port');

            if (Config::get('proxy_user') !== NULL) {
                $options[CURLOPT_PROXYUSERNAME] = Config::get('proxy_user');
                $options[CURLOPT_PROXYPASSWORD] = Config::get('proxy_passwd');
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        curl_close($ch);

        $LicInfo = array();

        if ($response == "unknownlic\n") return false;

        if (class_exists('SimpleXMLElement')) {
            foreach ((new SimpleXMLElement($response))->xpath('SECTION/LICENSEINFO/NODE')[0]->attributes() as $key => $value) {
                $LicInfo[$key] = (string)$value;
            }
        }
        return date('d.m.Y', hexdec($LicInfo['VALUE']));
    }

    /**
     * @param $download_files
     */
    static protected function multi_download($download_files)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $master = curl_multi_init();
        $options = array(
            CURLOPT_USERPWD => static::$key[0] .":" . static::$key[1],
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => CONNECTTIMEOUT,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        );

        if (Config::get('download_speed_limit') !== 0) {
            $options[CURLOPT_MAX_RECV_SPEED_LARGE] = Config::get('download_speed_limit');
        }

        if (Config::get('proxy_enable') !== 0) {
            $options[CURLOPT_PROXY] = Config::get('proxy_server');
            $options[CURLOPT_PROXYPORT] = Config::get('proxy_port');

            if (Config::get('proxy_user') !== NULL) {
                $options[CURLOPT_PROXYUSERNAME] = Config::get('proxy_user');
                $options[CURLOPT_PROXYPASSWORD] = Config::get('proxy_passwd');
            }
        }

        $files = array();
        $handles = array();
        $threads = 0;

        foreach ($download_files as $i => $file) {
            $ch = curl_init();
            $handles[Tools::get_resource_id($ch)] = current(static::$mirrors)['host'];
            $res = dirname(Tools::ds(Config::get('web_dir'), $file['file']));
            if (!@file_exists($res)) @mkdir($res, 0755, true);
            $options[CURLOPT_URL] = "http://" . current(static::$mirrors)['host'] . $file['file'];
            $options[CURLOPT_FILE] = $files['url'] = fopen(Tools::ds(Config::get('web_dir'), $download_files[$i]['file']), 'w');
            curl_setopt_array($ch, $options);
            curl_multi_add_handle($master, $ch);
            $threads++;

            if ($threads >= Config::get('threads')) {
                while ($threads >= Config::get('threads')) {
                    usleep(100);
                    while (($execrun = curl_multi_exec($master, $running)) === -1) {}
                    curl_multi_select($master);
                    while ($done = curl_multi_info_read($master)) {
                        $ch = $done['handle'];
                        $id = Tools::get_resource_id($ch);
                        $info = curl_getinfo($ch);
                        $host = $handles[$id];
                        if ($info['http_code'] == 200) {
                            @fclose($files[$info['url']]);
                            unset($files[$info['url']]);
                            Log::write_log(
                                Language::t("From %s downloaded %s [%s] [%s/s]", $host, basename($info['url']),
                                    Tools::bytesToSize1024($info['download_content_length']),
                                    Tools::bytesToSize1024($info['speed_download'])),
                                3,
                                static::$version
                            );
                            unset($handles[$id]);
                            static::$total_downloads += $info['download_content_length'];
                            curl_multi_remove_handle($master, $ch);
                            curl_close($ch);
                            $threads--;
                        } else {
                            Log::write_log(Language::t("Error download url %s", $info['url']), 3, static::$version);

                            if (next(static::$mirrors)) {
                                Log::write_log(Language::t("Try next mirror %s", current(static::$mirrors)['host']), 3, static::$version);
                                $options[CURLOPT_URL] = str_replace(prev(static::$mirrors)['host'], current(static::$mirrors)['host'], $info['url']);
                                curl_setopt_array($ch, $options);
                            } else {
                                @fclose($files[$info['url']]);
                                reset(static::$mirrors);
                                unset($files[$info['url']]);
                                Log::write_log(Language::t("All mirrors is down!"), 3, static::$version);
                                curl_multi_remove_handle($master, $ch);
                                curl_close($ch);
                                $threads--;
                            }
                        }
                    }
                }
            }
        }

        do {
            usleep(100);
            while (($execrun = curl_multi_exec($master, $running)) === -1) {}
            curl_multi_select($master);
            while ($done = curl_multi_info_read($master)) {
                $ch = $done['handle'];
                $id = Tools::get_resource_id($ch);
                $info = curl_getinfo($ch);
                $host = $handles[$id];
                if ($info['http_code'] == 200) {
                    @fclose($files[$info['url']]);
                    unset($files[$info['url']]);
                    Log::write_log(
                        Language::t("From %s downloaded %s [%s] [%s/s]", $host, basename($info['url']),
                            Tools::bytesToSize1024($info['download_content_length']),
                            Tools::bytesToSize1024($info['speed_download'])),
                        3,
                        static::$version
                    );
                    unset($handles[$id]);
                    static::$total_downloads += $info['download_content_length'];

                    curl_multi_remove_handle($master, $ch);
                    curl_close($ch);
                } else {
                    Log::write_log(Language::t("Error download url %s", $info['url']), 3, static::$version);

                    if (next(static::$mirrors)) {
                        Log::write_log(Language::t("Try next mirror %s", current(static::$mirrors)['host']), 3, static::$version);
                        $options[CURLOPT_URL] = str_replace(prev(static::$mirrors)['host'], current(static::$mirrors)['host'], $info['url']);
                        curl_setopt_array($ch, $options);
                    } else {
                        @fclose($files[$info['url']]);
                        reset(static::$mirrors);
                        unset($files[$info['url']]);
                        Log::write_log(Language::t("All mirrors is down!"), 3, static::$version);

                        curl_multi_remove_handle($master, $ch);
                        curl_close($ch);
                    }
                }
            }
        } while ($running > 0);
        curl_multi_close($master);
    }

    /**
     * @param $download_files
     */
    static protected function single_download($download_files)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);

        foreach ($download_files as $file) {
            foreach (static::$mirrors as $id => $mirror) {
                $time = microtime(true);
                Log::write_log(Language::t("Trying download file %s from %s", basename($file['file']), $mirror['host']), 3, static::$version);
                $header = Tools::download_file("http://" . static::$key[0] .":" . static::$key[1] . "@" . $mirror['host'] . $file['file'], Tools::ds(Config::get('web_dir'), $file['file']));

                if (is_array($header) and $header['http_code'] == 200 and $header['size_download'] == $file['size']) {
                    static::$total_downloads += $header['size_download'];
                    Log::write_log(Language::t("From %s downloaded %s [%s] [%s/s]", $mirror['host'], basename($file['file']),
                        Tools::bytesToSize1024($header['size_download']),
                        Tools::bytesToSize1024($header['size_download'] / (microtime(true) - $time))),
                        3,
                        static::$version
                    );
                    static::$total_downloads += $header['size_download'];
                    break;
                } else {
                    @unlink(Tools::ds(Config::get('web_dir'), $file['file']));
                }
            }
        }
    }

    /**
     * @param $download_files
     */
    static protected function download($download_files)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);

        switch (function_exists('curl_multi_init')) {
            case true:
                static::multi_download($download_files);
                break;
            case false:
            default:
                static::single_download($download_files);
                break;
        }
    }

    /**
     * @param $matches
     * @return array
     */
    static protected function parse_update_file($matches)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $new_content = '';
        $new_files = array();
        $total_size = 0;

        foreach ($matches as $container) {

            $parsed_container = parse_ini_string((preg_replace("/version=(.*?)\n/i", "version=\"\${1}\"\n", str_replace("\r\n", "\n", $container))), true);
            $output = array_shift($parsed_container);

            if (intval(static::$version) < 10) {
                if (empty($output['file']) or empty($output['size']) or empty($output['date']) or
                    (!empty($output['language']) and !in_array($output['language'], Config::get('update_version_lang'))) or
                    (Config::get('update_version_x32') != 1 and preg_match("/32|86/", $output['platform'])) or
                    (Config::get('update_version_x64') != 1 and preg_match("/64/", $output['platform'])) or
                    (Config::get('update_version_ess') != 1 and preg_match("/ess/", $output['type']))
                )
                    continue;
            } else {
                if (empty($output['file']) or empty($output['size']) or
                    (Config::get('update_version_x32') != 1 and preg_match("/32|86/", $output['platform'])) or
                    (Config::get('update_version_x64') != 1 and preg_match("/64/", $output['platform']))
                )
                    continue;
            }

            $new_files[] = $output;
            $total_size += $output['size'];
            $new_content .= $container;
        }

        return array($new_files, $total_size, $new_content);
    }

    /**
     * @param $download_files
     */
    static protected function download_files($download_files)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        shuffle($download_files);
        Log::write_log(Language::t("Downloading %d files", count($download_files)), 3, static::$version);

        if (Tools::ping(current(static::$mirrors)['host']) == true && static::check_mirror(current(static::$mirrors)['host']) != null) {
            static::download($download_files);
        }
    }

    /**
     * @param $version
     * @param $dir
     */
    static public function init($version, $dir)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, $version);
        register_shutdown_function(array('Mirror', 'destruct'));
        static::$total_downloads = 0;
        static::$version = $version;
        static::$dir = 'v' . static::$version . '-rel-*';
        static::$mirror_dir = $dir;
        Log::write_log(Language::t("Mirror initiliazed with dir=%s, mirror_dir=%s", static::$dir, static::$mirror_dir), 5, static::$version);
    }

    /**
     * @param $key
     */
    static public function set_key($key)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        static::$key = $key;
    }

    /**
     *
     */
    static public function destruct()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        static::$total_downloads = 0;
        static::$version = null;
        static::$dir = null;
        static::$mirror_dir = null;
        static::$mirrors = array();
        static::$key = array();
    }
}
