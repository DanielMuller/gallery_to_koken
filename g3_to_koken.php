<?php
require_once("common.php");

define("APPPATH", realpath("application") . "/");
define("MODPATH", realpath("modules") . "/");
define("THEMEPATH", realpath("themes") . "/");
define("SYSPATH", realpath("system") . "/");
define("VARPATH", realpath("var") . "/");

require_once(VARPATH . "database.php");

/**
 *
 *
 * @param int $parent
 * @param string $order_by
 * @param mixed $captured
 * @return array
 */
function get_content($parent = 0, $order_by = NULL, $captured = NULL) {
    global $g3db, $album_id, $content_id, $use_exif_coordinates;
    $items = array();

    $query = "select * from items where parent_id=" . $parent;
    if ($order_by) {
        $query .= " order by " . $order_by;
    }

    $res = $g3db->query($query);
    while ($row = $res->fetch_assoc()) {
        if ($row['type'] == "album") {
            $title = clean_text($row['title']);
            $description = trim(clean_text($row['description']));
            $name = clean_text($row['name']);
            $item = array(
                "type" => "album",
                "id" => $album_id,
                "name" => $name,
                "source" => convert_text($row['name']),
                "title" => $title,
                "description" => $description,
                "level" => $row['level'],
                "upload_date" => $row['created'],
                "slug" => $row['slug'],
                "cover" => $row['album_cover_item_id']
            );
            $album_id++;

            $child_order = $row['sort_column'] . " " . $row['sort_order'];
            $captured = $row['captured'];
            if ($captured == "") {
                $captured = $row['created'];
            }
            $item['items'] = get_content($row['id'], $child_order, $captured);
            $item['upload_date'] = get_creation($item['items']);
            $items[] = $item;
        } else {
            $image_file = urldecode(VARPATH . "albums/" . $row['relative_path_cache']);
            $exif = NULL;
            @$exif = exif_read_data($image_file, NULL, TRUE);
            unset($exif['EXIF']['MakerNote']);
            unset($exif['EXIF']['UserComment']);
            unset($exif['MAKERNOTE']);
            unset($exif['THUMBNAIL']);
            $exif_string = $exif_make = $exif_model = $exif_iso = $exif_camera_lens = $exif_camera_serial = NULL;
            if ($use_exif_coordinates && !array_key_exists('GPS', $exif)) {
                $query = "select latitude, longitude from exif_coordinates where item_id=" . $row['id'];
                $gps_res = $g3db->query($query);
                $gps_row = $gps_res->fetch_assoc();
                $lon = $gps_row['longitude'];
                $lat = $gps_row['latitude'];
                $latref = ($lat > 0) ? "N" : "S";
                $latinfo[0] = floor(abs($lat)) . "/1";
                $latinfo[1] = floor((abs($lat) - floor(abs($lat))) * 60 * 1000000) . "/1000000";
                $latinfo[2] = "0/1";
                $lonref = ($lon > 0) ? "E" : "W";
                $loninfo[0] = floor(abs($lon)) . "/1";
                $loninfo[1] = floor((abs($lon) - floor(abs($lon))) * 60 * 1000000) . "/1000000";
                $loninfo[2] = "0/1";
                $exif['GPS'] = array(
                    "GPSVersion" => "",
                    "GPSLatitudeRef" => $latref,
                    "GPSLatitude" => $latinfo,
                    "GPSLongitudeRef" => $lonref,
                    "GPSLongitude" => $loninfo,
                    "GPSMapDatum" => "WGS-84"
                );
                $sectionsFound = array();
                if (array_key_exists('FILE', $exif)) {
                    if (array_key_exists('SectionsFound', $exif['FILE'])) {
                        $sectionsFound = split(",", $exif['FILE']['SectionsFound']);
                        $sectionsFound = array_map('trim', $sectionsFound);
                    }
                }
                $sectionsFound[] = "GPS";
                $exif['FILE']['SectionsFound'] = implode(", ", $sectionsFound);
            }
            if (!empty($exif)) {
                if (isset($exif['IFD0']['Make'])) {
                    $exif_make = trim($exif['IFD0']['Make']);
                }
                if (isset($exif['IFD0']['Model'])) {
                    $exif_model = trim($exif['IFD0']['Model']);
                }
                if (isset($exif['EXIF']['ISOSpeedRatings'])) {
                    $exif_iso = trim($exif['EXIF']['ISOSpeedRatings']);
                }
                // Best Lens info is in this tag
                if (isset($exif['EXIF']['UndefinedTag:0xA434'])) {
                    $exif_camera_lens = trim($exif['EXIF']['UndefinedTag:0xA434']);
                }
                $exif_string = utf8_encode(serialize($exif));
            } else {
                $exif = NULL;
            }

            if (!isset($old_capture_date) || $old_capture_date == "") {
                $old_capture_date = $captured;
            }
            $capture_date = $row['captured'];
            if ($capture_date == "") {
                $capture_date = $old_capture_date;
            }
            $old_capture_date = $capture_date;
            $item = array(
                "type" => "photo",
                "id" => $content_id,
                "source_id" => $row['id'],
                "caption" => clean_text($row['description']),
                "image_file" => $image_file,
                "capture_date" => $capture_date,
                "upload_date" => $row['created'],
                "width" => $row['width'],
                "height" => $row['height'],
                "aspect_ratio" => round($row['width'] / $row['height'], 3),
                "exif_string" => $exif_string,
                "exif_make" => $exif_make,
                "exif_model" => $exif_model,
                "exif_iso" => $exif_iso,
                "exif_camera_serial" => $exif_camera_serial,
                "exif_camera_lens" => $exif_camera_lens,
                "level" => $row['level'],
                "slug" => $row['slug'],
                "name" => clean_text($row['title']),
                "visibility" => 0
            );
            $content_id++;
            $items[] = $item;
        }
    }

    return $items;
}

/**
 *
 *
 * @param mysqli $mysqli
 * @param array $content
 * @param array $parent
 * @return bool
 */
function create_sql(mysqli $mysqli, array $content, array $parent = NULL) {
    global $sqlfp, $shfp, $mapfp;
    $order = 1;

    foreach ($content as $val) {
        if ($val['type'] == "album") {
            print $val['title'] . " (" . $val['id'] . ")\n";
            $title = $mysqli->real_escape_string($val['title']);
            $slug = $mysqli->real_escape_string($val['slug']);
            $summary = $mysqli->real_escape_string($val['description']);
            $internal_id = md5($slug);
            $created_at = $val['upload_date'];
            $level = $val['level'];
            $last = sizeof($val['items']) - 1;
            if ($val['items'][$last]['type'] == "photo") {
                $album_type = 0;
            } else {
                $album_type = 2;
            }
            $query = "INSERT into " . KOKEN_PREFIX . "albums (id,title,slug,summary,description,level,album_type,published_on,created_on,modified_on,internal_id,total_count) values (" . $val['id'] . ",'" . $title . "','" . $slug . "','" . $summary . "','" . $summary . "', " . $level . ", " . $album_type . "," . $created_at . "," . $created_at . ", " . time() . ", '" . $internal_id . "'," . sizeof($val['items']) . ")";
            fwrite($sqlfp, utf8_decode($query) . ";\n");
            $folder = ($album_type == 2) ? "sets" : "albums";
            fwrite($mapfp, urlencode(utf8_decode($val['source'])) . " " . "http://" . KOKEN_URL . "/" . $folder . "/" . $val['slug'] . "\n");
            create_sql($mysqli, $val['items'], $val);
        }
        if ($val['type'] == "photo") {
            $pathinfo = pathinfo($val['image_file']);
            $filename = $mysqli->real_escape_string($pathinfo['basename']);
            $caption = $mysqli->real_escape_string($val['caption']);
            $filesize = filesize($val['image_file']);
            $slug = $mysqli->real_escape_string($parent['slug'] . "-" . $val['slug']);
            $internal_id = md5($slug);
            $capture_date = $val['capture_date'];
            if ($capture_date == "") {
                $capture_date = "NULL";
            }

            print $filename . "\n";

            $query = "INSERT into " . KOKEN_PREFIX . "content ";
            $query .= " (id,slug,filename,caption,visibility,filesize,width,height,aspect_ratio,published_on,uploaded_on,captured_on,modified_on,internal_id,exif,exif_make,exif_model,exif_iso,exif_camera_serial,exif_camera_lens)";
            $query .= " values";
            $query .= " (" . $val['id'] . ",'" . $slug . "','" . $filename . "','" . $caption . "'," . $val['visibility'] . "," . $filesize . ",'" . $val['width'] . "','" . $val['height'] . "','" . $val['aspect_ratio'] . "'," . $val['upload_date'] . "," . $val['upload_date'] . "," . $capture_date . "," . $val['upload_date'] . ",'" . $internal_id . "'";
            $query .= ($val['exif_string'] === NULL) ? ",NULL" : ",'" . $mysqli->real_escape_string($val['exif_string']) . "'";
            $query .= ($val['exif_make'] === NULL) ? ",NULL" : ",'" . $mysqli->real_escape_string($val['exif_make']) . "'";
            $query .= ($val['exif_model'] === NULL) ? ",NULL" : ",'" . $mysqli->real_escape_string($val['exif_model']) . "'";
            $query .= ($val['exif_iso'] === NULL) ? ",NULL" : ",'" . $mysqli->real_escape_string($val['exif_iso']) . "'";
            $query .= ($val['exif_camera_serial'] === NULL) ? ",NULL" : ",'" . $mysqli->real_escape_string($val['exif_camera_serial']) . "'";
            $query .= ($val['exif_camera_lens'] === NULL) ? ",NULL" : ",'" . $mysqli->real_escape_string($val['exif_camera_lens']) . "'";
            $query .= ")";
            fwrite($sqlfp, utf8_decode($query) . ";\n");
            $newfile = KOKEN_PATH . "/storage/originals/" . substr($internal_id, 0, 2) . "/" . substr($internal_id, 2, 2) . "/" . $pathinfo['basename'];
            fwrite($shfp, "mkdir -p " . dirname($newfile) . "\n");
            fwrite($shfp, "cp \"" . $val['image_file'] . "\" \"" . $newfile . "\"\n");
            $query = "INSERT into " . KOKEN_PREFIX . "join_albums_content (album_id,content_id,`order`) values (" . $parent['id'] . "," . $val['id'] . "," . $order . ")";
            fwrite($sqlfp, $query . ";\n");
            if ($val['source_id'] == $parent['cover']) {
                $query = "INSERT into " . KOKEN_PREFIX . "join_albums_covers (album_id,cover_id) values (" . $parent['id'] . "," . $val['id'] . ")";
                fwrite($sqlfp, $query . ";\n");
            }
            $order++;
        }
    }

    return TRUE;
}

$conn = $config['default']['connection'];
$g3db = new mysqli("localhost", $conn['user'], $conn['pass'], $conn['database']);

$mysqli = new mysqli(KOKEN_SQL_HOST, KOKEN_SQL_USER, KOKEN_SQL_PASSWORD, KOKEN_SQL_DB);

$query = "SHOW TABLE STATUS LIKE '" . KOKEN_PREFIX . "albums'";
$res = $mysqli->query($query);
$row = $res->fetch_assoc();
$album_id = $row['Auto_increment'];
$query = "SHOW TABLE STATUS LIKE '" . KOKEN_PREFIX . "content'";
$res = $mysqli->query($query);
$row = $res->fetch_assoc();
$content_id = $row['Auto_increment'];
$query = "select max(left_id)+1 as left_id_start from " . KOKEN_PREFIX . "albums";
$res = $mysqli->query($query);
$row = $res->fetch_assoc();
$left_id_start = intval($row['left_id_start']);

$content = get_content();
$fake_content = array(
    'title' => "root",
    'id' => 0,
    'type' => "album",
    'items' => $content
);

$sqlfp = fopen("/tmp/koken.sql", "wb");
$shfp = fopen("/tmp/koken.sh", "wb");
$mapfp = fopen("/tmp/koken.txt", "wb");
fwrite($shfp, "#!/bin/bash\n");
create_sql($g3db, $content);
rebuild_tree($fake_content, $left_id_start);
fwrite($shfp, "cd " . KOKEN_PATH . "/storage/originals/\n");
fwrite($shfp, "chmod -R 666 *\n");
fwrite($shfp, "chmod -R +X *\n");
fwrite($shfp, "cat /tmp/koken.sql | mysql -u " . KOKEN_SQL_USER . " --password=" . KOKEN_SQL_PASSWORD . " " . KOKEN_SQL_DB . "\n");
fclose($sqlfp);
fclose($shfp);
fclose($mapfp);
