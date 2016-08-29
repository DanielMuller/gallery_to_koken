<?php

// configure your Koken setup here

define("KOKEN_PREFIX", "koken_");
define("KOKEN_URL", "www.mysite.com/koken");
define("KOKEN_SQL_HOST", "localhost");
define("KOKEN_SQL_DB", "koken");
define("KOKEN_SQL_USER", "koken");
define("KOKEN_SQL_PASSWORD", "my_secret_password");
define("KOKEN_PATH", "/var/www/www.mysite.com/koken/");

// set a locale that suits you

setlocale(LC_NUMERIC, "fr_CH");

// for Gallery 1 set this accordingly

$root_album = "my_album";

// for Gallery 3 switch off EXIF GPS use
$use_exif_coordinates = FALSE;

// --------------------------------
// end of user serviceable parts ;)
// --------------------------------

ini_set("default_charset", "utf-8");
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');

/**
 *
 *
 * @param array $a
 * @param array $b
 * @return int
 */
function order_content(array $a, array $b) {
    if ($a['type'] == "album" && $b['type'] == "photo") {
        return 1;
    }
    if ($a['type'] == "photo" && $b['type'] == "album") {
        return -1;
    }
    if ($a['type'] == "album" && $b['type'] == "album") {
        if ($a['upload_date'] == $b['upload_date']) {
            return 0;
        }

        return ($a['upload_date'] > $b['upload_date']) ? -1 : 1;
    }
    if ($a['type'] == "photo" && $b['type'] == "photo") {
        if ($a['capture_date'] == $b['capture_date']) {
            return 0;
        }

        return ($a['capture_date'] < $b['capture_date']) ? -1 : 1;
    }

    return 0;
}

/**
 *
 *
 * @param string $str
 * @return string
 */
function clean_text($str) {
    $str = trim($str);
    $str = preg_replace("/(\r\n|\r|\n)+/", " ", $str);
    $str = preg_replace("/\s+/", " ", $str);

    $encoding = mb_detect_encoding($str, "WINDOWS-1252, ISO-8859-1, ISO-8859-15, UTF-8, ASCII", TRUE);

    $str = mb_convert_encoding($str, "UTF-8", $encoding);
    $str = html_entity_decode($str);

    return trim($str);
}

/**
 *
 *
 * @param string $str
 * @return string
 */
function convert_text($str) {
    $encoding = mb_detect_encoding($str, "WINDOWS-1252, ISO-8859-1, ISO-8859-15, UTF-8, ASCII", TRUE);
    $str = mb_convert_encoding($str, "UTF-8", $encoding) . "\n";
    $str = html_entity_decode($str);

    return trim($str);
}

/**
 *
 *
 * @param array $items
 * @return int|mixed
 */
function get_creation(array $items) {
    $upload_date = time();
    foreach ($items as $item) {
        if (isset($item['upload_date'])) {
            $upload_date = min($upload_date, $item['upload_date']);
        }
    }

    return $upload_date;
}

/**
 *
 *
 * @param string $str
 * @return mixed
 */
function remove_accents($str) {
    if (is_readable("foreign_chars.php")) {
        $foreign_characters = include("foreign_chars.php");

        return preg_replace(array_keys($foreign_characters), array_values($foreign_characters), utf8_encode($str));
    }

    return $str;
}

/**
 *
 *
 * @param string $str
 * @return string
 */
function url_title($str) {
    $separator = "-";
    $q_separator = preg_quote($separator);
    $trans = array(
        '&.+?;' => '',
        '[^a-z0-9 _-]' => '',
        '\s+' => $separator,
        '(' . $q_separator . ')+' => $separator
    );

    $str = strip_tags($str);

    foreach ($trans as $key => $val) {
        $str = preg_replace("#" . $key . "#i", $val, $str);
    }

    $str = strtolower($str);

    return trim($str);
}

/**
 *
 *
 * @param string $str
 * @return string
 */
function slug($str) {
    return url_title(remove_accents($str));
}

/**
 *
 *
 * @param array $content
 * @param int $left
 * @return int
 */
function rebuild_tree(array $content, $left) {
    global $sqlfp;
    $right = $left + 1;
    if (array_key_exists("items", $content) && $content['items'] !== array()) {
        $last = sizeof($content['items']) - 1;
        if ($content['items'][$last]['type'] == "photo") {
            $content['items'] = array();
        }
        if (is_array($content['items'])) {
            foreach($content['items'] as $val) {
                $right = rebuild_tree($val, $right);
            }
        }
        $query = "update " . KOKEN_PREFIX . "albums set left_id=" . $left . ",right_id=" . $right . " where id='" . $content['id'] . "'";
        fwrite($sqlfp, $query . ";\n");

        return $right + 1;
    }

    return $left;
}
