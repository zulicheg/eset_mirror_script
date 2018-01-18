<?php

/**
 * Class Nod32ms
 */
class Nod32ms
{
    /**
     * @var
     */
    static private $start_time;

    /**
     * Nod32ms constructor.
     */
    public function __construct()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        static::$start_time = time();
        Log::write_log(Language::t("Run script %s", VERSION), 0);
        $this->run_script();
    }

    /**
     * Nod32ms destructor.
     */
    public function __destruct()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        Log::write_log(Language::t("Total working time: %s", Tools::secondsToHumanReadable(time() - static::$start_time)), 0);
        Log::destruct();
        Log::write_log(Language::t("Stop script."), 0);
    }

    /**
     * @param $version
     * @param bool $return_time_stamp
     * @return mixed|null
     */
    private function check_time_stamp($version, $return_time_stamp = false)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, $version);
        $days = Config::get('icq_informer_days') * 24 * 60 * 60;
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

            if (isset($timestamps[$version])) {
                if ($timestamps[$version] + $days < time()) {
                    return $timestamps[$version];
                } elseif ($return_time_stamp) {
                    return $timestamps[$version];
                }
            }
        }
        return null;
    }

    /**
     * @param $size
     */
    private function set_database_size($size)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $fn = Tools::ds(Config::get('log_dir'), DATABASES_SIZE);
        $sizes = array();

        if (file_exists($fn)) {
            $handle = file_get_contents($fn);
            $content = Parser::parse_line($handle, false, "/(.+:.+)\n/");

            if (isset($content) && count($content)) {
                foreach ($content as $value) {
                    $result = explode(":", $value);
                    $sizes[$result[0]] = $result[1];
                }
            }
        }

        $sizes[Mirror::$version] = $size;
        @unlink($fn);

        foreach ($sizes as $key => $name)
            Log::write_to_file(DATABASES_SIZE, "$key:$name\r\n");
    }

    /**
     * @return array|null
     */
    private function get_databases_size()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $fn = Tools::ds(Config::get('log_dir'), DATABASES_SIZE);
        $sizes = array();

        if (file_exists($fn)) {
            $handle = file_get_contents($fn);
            $content = Parser::parse_line($handle, false, "/(.+:.+)\n/");

            if (isset($content) && count($content)) {
                foreach ($content as $value) {
                    $result = explode(":", $value);
                    $sizes[$result[0]] = $result[1];
                }
            }
        }

        return (!empty($sizes)) ? $sizes : null;
    }

    /**
     * @param string $directory
     * @return array
     */
    private function get_all_patterns($directory = PATTERN)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        $d = dir($directory);
        static $ar_patterns = array();

        while (false !== ($entry = $d->read())) {
            if (($entry == '.') || ($entry == '..'))
                continue;

            if (is_dir(Tools::ds($directory, $entry))) {
                $this->get_all_patterns(Tools::ds($directory, $entry));
                continue;
            }

            $ar_patterns[] = Tools::ds($directory, $entry);
        }

        $d->close();
        return $ar_patterns;
    }

    /**
     * @param $key
     * @return bool
     */
    private function validate_key($key)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $result = explode(":", $key);
        $format = 'd.m.Y';
        $current_date = date_parse_from_format($format, strftime('%d.%m.%Y'));
        Log::write_log(Language::t("Validating key [%s:%s]", $result[0], $result[1]), 4, Mirror::$version);
        if ($this->key_exists_in_file($result[0], $result[1], KEY_FILE_INVALID)) {
            return false;
        }

        Mirror::set_key(array($result[0], $result[1]));
        $date = $this->get_expire_date();

        if ($date == false) {
            $this->delete_key($result[0], $result[1]);
            return false;
        }
        $parsed_date = date_parse_from_format($format, $date);

        if ((
                ($parsed_date['day'] >= $current_date['day']) &&
                ($parsed_date['month'] == $current_date['month']) &&
                ($parsed_date['year'] == $current_date['year'])
            )
            ||
            (
                ($parsed_date['month'] > $current_date['month']) &&
                ($parsed_date['year'] >= $current_date['year'])
            )
            ||
            ($parsed_date['year'] > $current_date['year'])
        ) {
            $ret = Mirror::test_key();
        } else {
            Log::write_log(Language::t("Found expired key [%s:%s] Expiration date %s", $result[0], $result[1], $date), 4, Mirror::$version);
            $this->delete_key($result[0], $result[1]);
            return false;
        }

        if (is_bool($ret)) {
            if ($ret) {
                $this->write_key($result[0], $result[1], $date);
                return true;
            } else {
                $this->delete_key($result[0], $result[1]);
            }
        } else {
            Log::write_log(Language::t("Unhandled exception [%s]", $ret), 4);
        }
        return false;
    }

    /**
     * @return false|string
     */
    private function get_expire_date()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        return Mirror::exp_nod();
    }

    /**
     * @return array|null
     */
    private function read_keys()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);

        if (!file_exists(Tools::ds(Config::get('log_dir'), KEY_FILE_VALID))) {
            $h = fopen(Tools::ds(Config::get('log_dir'), KEY_FILE_VALID), 'w');
            fclose($h);
        }

        $keys = Parser::parse_keys(Tools::ds(Config::get('log_dir'), KEY_FILE_VALID));

        if (!isset($keys) || !count($keys)) {
            Log::write_log(Language::t("Keys file is empty!"), 4, Mirror::$version);
            return null;
        }

        foreach ($keys as $value) {
            if ($this->validate_key($value))
                return explode(":", $value);
        }

        Log::write_log(Language::t("No working keys were found!"), 4, Mirror::$version);
        return null;
    }

    /**
     * @param string $login
     * @param string $password
     * @param string $date
     */
    private function write_key($login, $password, $date)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        Log::write_log(Language::t("Found valid key [%s:%s] Expiration date %s", $login, $password, $date), 4, Mirror::$version);
        ($this->key_exists_in_file($login, $password, KEY_FILE_VALID) == false) ?
            Log::write_to_file(Tools::ds(Config::get('log_dir'), KEY_FILE_VALID), "$login:$password:" . Mirror::$version . ":$date\r\n", true) :
            Log::write_log(Language::t("Key [%s:%s:%s:%s] already exists", $login, $password, Mirror::$version, $date), 4, Mirror::$version);
    }

    /**
     * @param string $login
     * @param string $password
     */
    private function delete_key($login, $password)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        Log::write_log(Language::t("Invalid key [%s:%s]", $login, $password), 4, Mirror::$version);
        ($this->key_exists_in_file($login, $password, KEY_FILE_INVALID) == false) ?
            Log::write_to_file(Tools::ds(Config::get('log_dir'), KEY_FILE_INVALID), "$login:$password:" . Mirror::$version . "\r\n", true) :
            Log::write_log(Language::t("Key [%s:%s] already exists", $login, $password), 4, Mirror::$version);

        if (Config::get('remove_invalid_keys') == 1)
            Parser::delete_parse_line_in_file($login . ':' . $password . Mirror::$version, Tools::ds(Config::get('log_dir'), KEY_FILE_VALID));
    }

    /**
     * @param string $login
     * @param string $password
     * @param $file
     * @return bool
     */
    private function key_exists_in_file($login, $password, $file)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        $keys = Parser::parse_keys(Tools::ds(Config::get('log_dir'), $file));

        if (isset($keys) && count($keys)) {
            foreach ($keys as $value) {
                $result = explode(":", $value);

                if ($result[0] == $login && $result[1] == $password && $result[2] == Mirror::$version)
                    return true;
            }
        }

        return false;
    }

    /**
     * @param string $search
     * @return string
     */
    private function strip_tags_and_css($search)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        $document = array(
            "'<script[^>]*?>.*?<\/script>'si",
            "'<[\/\!]*?[^<>]*?>'si",
            "'([\r\n])[\s]+'",
            "'&(quot|#34);'i",
            "'&(amp|#38);'i",
            "'&(lt|#60);'i",
            "'&(gt|#62);'i",
            "'&(nbsp|#160);'i",
            "'&(iexcl|#161);'i",
            "'&(cent|#162);'i",
            "'&(pound|#163);'i",
            "'&(copy|#169);'i",
            "'&#(\d+);'e"
        );
        $replace = array(
            "",
            "",
            "\\1",
            "\"",
            "&",
            "<",
            ">",
            " ",
            chr(161),
            chr(162),
            chr(163),
            chr(169),
            "chr(\\1)"
        );
        return trim(preg_replace($document, $replace, $search));
    }

    /**
     * @param string $this_link
     * @param integer $level
     * @param array $pattern
     * @return bool
     */
    private function parse_www_page($this_link, $level, $pattern)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        static $found_key = false;
        $search = Tools::download_file(array(CURLOPT_URL => $this_link, CURLOPT_RETURNTRANSFER => 1, CURLOPT_NOBODY => 0, CURLOPT_USERAGENT => "Mozilla/5.0 (Windows; U; Windows NT 6.1; rv:2.2) Gecko/20110201"));

        if ($search === false) {
            Log::write_log(Language::t("Link wasn't found [%s]", $this_link), 4, Mirror::$version);
            return false;
        }

        Log::write_log(Language::t("Link was found [%s]", $this_link), 4, Mirror::$version);
        $login = array();
        $password = array();

        if (Config::get('debug_html') == 1) {
            $path_info = pathinfo($this_link);
            $dir = Tools::ds(Config::get('log_dir'), DEBUG_DIR, $path_info['basename']);
            @mkdir($dir, 0755, true);
            $filename = Tools::ds($dir, $path_info['filename'] . ".log");
            file_put_contents($filename, $this->strip_tags_and_css($search));
        }

        foreach ($pattern as $key)
            Parser::parse_template($search, $key, $login, $password);
        $logins = count($login);

        if ($logins > 0) {
            Log::write_log(Language::t("Found keys: %s", $logins), 4, Mirror::$version);

            for ($b = 0; $b < $logins; $b++) {
                if (preg_match("/script|googleuser/i", $password[$b]) and
                    $this->key_exists_in_file($login[$b], $password[$b], KEY_FILE_VALID)
                )
                    continue;

                if ($this->validate_key($login[$b] . ':' . $password[$b])) {
                    $found_key = true;
                    return true;
                }
            }
        }

        if ($level > 1) {
            $links = array();
            preg_match_all('/href *= *"([^\s"]+)/', $search, $results);

            foreach ($results[1] as $result) {
                str_replace('webcache.googleusercontent.com/search?q=cache:', '', $result);

                if (!preg_match("/youtube.com|ocialcomments.org/", $result)) {
                    preg_match('/https?:\/\/(?(?!\&amp).)*/', $result, $res);

                    if (!empty($res[0]))
                        $links[] = $res[0];
                }
            }
            Log::write_log(Language::t("Found links: %s", count($links)), 4, Mirror::$version);

            foreach ($links as $url) {
                $this->parse_www_page($url, $level - 1, $pattern);

                if ($found_key)
                    return true;
            }
        }

        return false;
    }

    /**
     * @return null
     */
    private function find_keys()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);

        if (Config::get('find_auto_enable') != 1)
            return null;

        if (Config::get('find_system') === null) {
            $patterns = $this->get_all_patterns();
            shuffle($patterns);
        } else {
            $patterns = array(PATTERN . Config::get('find_system') . '.pattern');
        }

        while ($elem = array_shift($patterns)) {
            $pattern_name = pathinfo($elem);
            Log::write_log(Language::t("Begining search at %s", $pattern_name['basename']), 4, Mirror::$version);
            $find = @file_get_contents($elem);

            if (!$find) {
                Log::write_log(Language::t("File %s doesn't exist!", $pattern_name['basename']), 4, Mirror::$version);
                continue;
            }

            $link = Parser::parse_line($find, "link");
            $pageindex = Parser::parse_line($find, "pageindex");
            $pattern = Parser::parse_line($find, "pattern");
            $page_qty = Parser::parse_line($find, "page_qty");
            $recursion_level = Parser::parse_line($find, "recursion_level");

            if (empty($link)) {
                Log::write_log(Language::t("[link] doesn't set up in %s file!", $elem), 4, Mirror::$version);
                continue;
            }

            if (empty($pageindex))
                $pageindex[] = Config::get('default_pageindex');

            if (empty($pattern))
                $pattern[] = Config::get('default_pattern');

            if (empty($page_qty))
                $page_qty[] = Config::get('default_page_qty');

            if (empty($recursion_level))
                $recursion_level[] = Config::get('default_recursion_level');

            $queries = explode(", ", Config::get('default_search_query'));

            foreach ($queries as $query) {
                $pages = substr_count($link[0], "#PAGE#") ? $page_qty[0] : 1;

                for ($i = 0; $i < $pages; $i++) {
                    $this_link = str_replace("#QUERY#", str_replace(" ", "+", trim($query)), $link[0]);
                    $this_link = str_replace("#PAGE#", ($i * $pageindex[0]), $this_link);

                    if ($this->parse_www_page($this_link, $recursion_level[0], $pattern) == true)
                        break(3);
                }
            }
        }

        return null;
    }

    /**
     *
     */
    private function generate_html()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        Log::write_log(Language::t("Generating html..."), 0);
        $total_size = $this->get_databases_size();
        $html_page = '';

        if (Config::get('generate_only_table') == '0') {
            $html_page .= '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">';
            $html_page .= '<html>';
            $html_page .= '<head>';
            $html_page .= '<title>' . Language::t("ESET NOD32 update server") . '</title>';
            $html_page .= '<meta http-equiv="Content-Type" content="text/html; charset=' . Config::get('html_codepage') . '">';
            $html_page .= '<style type="text/css">html,body{height:100%;margin:0;padding:0;width:100%}table#center{border:0;height:100%;width:100%}table td table td{text-align:center;vertical-align:middle;font-weight:bold;padding:10px 15px;border:0}table tr:nth-child(odd){background:#eee}table tr:nth-child(even){background:#fc0}</style>';
            $html_page .= '</head>';
            $html_page .= '<body>';
            $html_page .= '<table id="center">';
            $html_page .= '<tr>';
            $html_page .= '<td align="center">';
        }

        $html_page .= '<table>';
        $html_page .= '<tr><td colspan="4">' . Language::t("ESET NOD32 update server") . '</td></tr>';
        $html_page .= '<tr>';
        $html_page .= '<td></td>';
        $html_page .= '<td>' . Language::t("Database version") . '</td>';
        $html_page .= '<td>' . Language::t("Database size") . '</td>';
        $html_page .= '<td>' . Language::t("Last update") . '</td>';
        $html_page .= '</tr>';

        global $DIRECTORIES;

        foreach ($DIRECTORIES as $ver => $dir) {
            if (Config::upd_version_is_set($ver) == '1') {
                $update_ver = Tools::ds(Config::get('web_dir'), $dir, 'update.ver');
                $version = Tools::get_DB_version($update_ver);
                $timestamp = $this->check_time_stamp($ver, true);
                $html_page .= '<tr>';
                $html_page .= '<td>' . Language::t("Version %d", $ver) . '</td>';
                $html_page .= '<td>' . $version . '</td>';
                $html_page .= '<td>' . (isset($total_size[$ver]) ? Tools::bytesToSize1024($total_size[$ver]) : Language::t("n/a")) . '</td>';
                $html_page .= '<td>' . ($timestamp ? date("Y-m-d, H:i:s", $timestamp) : Language::t("n/a")) . '</td>';
                $html_page .= '</tr>';
            }
        }

        $html_page .= '<tr>';
        $html_page .= '<td colspan="2">' . Language::t("Present versions") . '</td>';
        $html_page .= '<td colspan="2">' . (Config::get('update_version_ess') ? 'EAV, ESS' : 'EAV') . '</td>';
        $html_page .= '</tr>';

        $html_page .= '<tr>';
        $html_page .= '<td colspan="2">' . Language::t("Present platforms") . '</td>';
        $html_page .= '<td colspan="2">' . ((Config::get('update_version_x32') ? '32bit' : '') . (Config::get('update_version_x64') ? (Config::get('update_version_x32') ? ', 64bit' : '64bit') : '')) . '</td>';
        $html_page .= '</tr>';

        $html_page .= '<tr>';
        $html_page .= '<td colspan="2">' . Language::t("Present languages") . '</td>';
        $html_page .= '<td colspan="2">' . Config::get('present_languages') . '</td>';
        $html_page .= '</tr>';

        $html_page .= '<tr>';
        $html_page .= '<td colspan="2">' . Language::t("Last execution of the script") . '</td>';
        $html_page .= '<td colspan="2">' . (static::$start_time ? date("Y-m-d, H:i:s", static::$start_time) : Language::t("n/a")) . '</td>';
        $html_page .= '</tr>';

        if (Config::get('show_login_password')) {
            $key = null;
            if (file_exists(Tools::ds(Config::get('log_dir'), KEY_FILE_VALID))) {
                $keys = Parser::parse_keys(Tools::ds(Config::get('log_dir'), KEY_FILE_VALID));
                $key = (is_array($keys)) ? explode(":", $keys[0]) : null;
            }

            $html_page .= '<tr>';
            $html_page .= '<td colspan="2">' . Language::t("Used login") . '</td>';
            $html_page .= '<td colspan="2">' . $key[0] . '</td>';
            $html_page .= '</tr>';
            $html_page .= '<tr>';
            $html_page .= '<td colspan="2">' . Language::t("Used password") . '</td>';
            $html_page .= '<td colspan="2">' . $key[1] . '</td>';
            $html_page .= '</tr>';
            $html_page .= (isset($key[2])) ? '<tr><td colspan="2">' . Language::t("Expiration date") . '</td><td colspan="2">' . $key[2] . '</td></tr>' : '';
        }
        $html_page .= '</table>';
        $html_page .= (Config::get('generate_only_table') == '0') ? '</td></tr></table></body></html>' : '';
        $file = Tools::ds(Config::get('web_dir'), Config::get('filename_html'));

        if (file_exists($file))
            @unlink($file);

        Log::write_to_file($file, Tools::conv($html_page, Config::get('html_codepage')), true);
    }

    /**
     *
     */
    private function run_script()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, null);
        global $DIRECTORIES;
        $total_size = array();
        $total_downloads = array();
        $average_speed = array();

        foreach ($DIRECTORIES as $version => $dir) {
            if (Config::upd_version_is_set($version) == '1') {
                Log::write_log(Language::t("Init Mirror for version %s in %s", $version, $dir), 5, $version);
                Mirror::init($version, $dir);
                $key = $this->read_keys();

                if ($key === null) {
                    $this->find_keys();
                    $key = $this->read_keys();

                    if ($key === null) {
                        Log::write_log(Language::t("The script has been stopped!"), 1, Mirror::$version);
                        continue;
                    }
                    Mirror::set_key($key);
                }

                Mirror::find_best_mirrors();
                $old_version = Tools::get_DB_version(Tools::ds(Config::get('web_dir'), $dir, 'update.ver'));

                if (!empty(Mirror::$mirrors)) {
                    foreach (Mirror::$mirrors as $id => $mirror) {
                        if ($mirror['db_version'] !== 0) {
                            Log::write_log(Language::t("The latest database %s was found on %s", $mirror['db_version'], $mirror['host']), 2, Mirror::$version);
                        } else {
                            Log::write_log(Language::t("Latest database not found!"), 2, Mirror::$version);
                            unset(Mirror::$mirrors[$id]);
                            continue;
                        }

                        if ($this->compare_versions($old_version, $mirror['db_version'])) {
                            Log::informer(Language::t("Your version of database is relevant %s", $old_version), Mirror::$version, 2);
                        }
                    }

                    if (!empty(Mirror::$mirrors)) {
                        foreach (Mirror::$mirrors as $id => $mirror) {
                            list($size, $downloads, $speed) = Mirror::download_signature();
                            $this->set_database_size($size);

                            if (is_null($downloads)) {
                                Log::informer(Language::t("Your database has not been updated!"), Mirror::$version, 1);
                            } else {
                                $total_size[Mirror::$version] = $size;
                                $total_downloads[Mirror::$version] = $downloads;
                                if (!empty($speed)) {
                                    $average_speed[Mirror::$version] = $speed;
                                }

                                if (empty($old_version)) {
                                    Log::informer(Language::t("Your database was successfully updated to %s", $mirror['db_version']), Mirror::$version, 2);
                                } elseif ($old_version <= $mirror['db_version']) {
                                    Log::informer(Language::t("Your database was successfully updated from %s to %s", $old_version, $mirror['db_version']), Mirror::$version, 2);
                                }
                                break;
                            }
                        }
                    }
                } else {
                    Log::write_log(Language::t("All mirrors is down!"), 1, Mirror::$version);
                }

                Mirror::destruct();
            }
        }

        Log::write_log(Language::t("Total size for all databases: %s", Tools::bytesToSize1024(array_sum($total_size))), 3);

        if (array_sum($total_downloads) > 0)
           Log::write_log(Language::t("Total downloaded for all databases: %s", Tools::bytesToSize1024(array_sum($total_downloads))), 3);

        if (array_sum($average_speed) > 0)
           Log::write_log(Language::t("Average speed for all databases: %s/s", Tools::bytesToSize1024(array_sum($average_speed) / count($average_speed))), 3);

        if (Config::get('generate_html') == '1')
            $this->generate_html();
    }

    /**
     * @param $old_version
     * @param $new_version
     * @return bool
     */
    private function compare_versions($old_version, $new_version)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, Mirror::$version);
        return (intval($old_version) >= intval($new_version));
    }
}
