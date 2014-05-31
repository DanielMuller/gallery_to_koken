<?php
define("KOKEN_PREFIX","koken_");
define("KOKEN_URL","www.mysite.com/koken");
define("KOKEN_SQL_HOST","localhost");
define("KOKEN_SQL_DB","koken");
define("KOKEN_SQL_USER","koken");
define("KOKEN_SQL_PASSWORD","my_secret_password");
define("KOKEN_PATH","/var/www/www.mysite.com/koken/");

$root_album = "my_album";
ini_set("default_charset","utf-8");

function order_content($a,$b) {
    if ($a['type']=="album" && $b['type']=="photo") return 1;
    if ($a['type']=="photo" && $b['type']=="album") return -1;
    if ($a['type']=="album" && $b['type']=="album") {
        if ($a['upload_date']==$b['upload_date']) return 0;
        return ($a['upload_date'] > $b['upload_date']) ? -1 : 1;
    }
    if ($a['type']=="photo" && $b['type']=="photo") {
        if ($a['capture_date']==$b['capture_date']) return 0;
        return ($a['capture_date'] < $b['capture_date']) ? -1 : 1;
    }
    return 0;
}
function clean_text($str) {
    $str = trim($str);
    $str = preg_replace("/(\r\n|\r|\n)+/", " ", $str);
    $str = preg_replace("/\s+/", " ", $str);

    $encoding = mb_detect_encoding($str, "WINDOWS-1252, ISO-8859-1, ISO-8859-15, UTF-8, ASCII", true);

    $str = mb_convert_encoding($str,"UTF-8",$encoding);
    $str = html_entity_decode($str);
    return trim($str);
}
function convert_text($str) {
    $encoding = mb_detect_encoding($str, "WINDOWS-1252, ISO-8859-1, ISO-8859-15, UTF-8, ASCII", true);
    $str = mb_convert_encoding($str,"UTF-8",$encoding)."\n";
    $str = html_entity_decode($str);
    return trim($str);
}

function get_creation($items) {
    $upload_date = time();
    foreach ($items as $item) {
        if (isset($item['upload_date'])) {
            $upload_date=min($upload_date,$item['upload_date']);
        }
    }
    return $upload_date;
}

function get_content($album,$level=0) {
    global $albumDB,$album_id,$content_id;
    $infos = Array();
    foreach($album->photos as $item) {
        if ($item->isAlbum()) {
            if ($item->hidden != 1) {
                $child = $albumDB->getAlbumByName($item->isAlbumName);
                $title = clean_text($child->fields['title']);
                $description = trim(clean_text($child->fields['description']));
                $name = clean_text($child->fields['name']);
                $matches = Array();
                preg_match("/^(\d{8})[\/| ](.*)$/",$title,$matches);
                if (sizeof($matches)>0) {
                    $title=$name=$matches[2];
                    if ($description=="") {
                        $date=mktime(0,0,0,substr($matches[1],4,2),substr($matches[1],6,2),substr($matches[1],0,4));
                        setlocale(LC_TIME, "fr_CH");
                        $description=strftime("%d %B %Y",$date);
                    }
                }
                $info = Array(
                    "type" => "album",
                    "id" => $album_id,
                    "name"=>$name,
                    "source"=>convert_text($child->fields['name']),
                    "title"=>$title,
                    "description"=>$description,
                    "level"=>$level
                );
                $album_id++;
                $info['items'] = get_content($child,$level+1); 
                $info['upload_date'] = get_creation($info['items']);
                $infos[]=$info;
            }
        }
        else {
            if ($level>=1) {
                $image_file = $item->getPhotoPath($album->getAlbumDir(), false);
                $exif = null;
                @$exif = exif_read_data($image_file, 0, true);
                unset($exif['EXIF']['MakerNote']);
                $exif_string = $exif_make = $exif_model = $exif_iso = $exif_camera_lens = $exif_camera_serial = null;
                if (!empty($exif)) {
                    if (isset($exif['IFD0']['Make']))
                    {  
                        $exif_make = trim($exif['IFD0']['Make']);
                    }
                    if (isset($exif['IFD0']['Model']))
                    {
                        $exif_model = trim($exif['IFD0']['Model']);
                    }
                    if (isset($exif['EXIF']['ISOSpeedRatings']))
                    {
                        $exif_iso = trim($exif['EXIF']['ISOSpeedRatings']);
                    }
                    // Best Lens info is in this tag
                    if (isset($exif['EXIF']['UndefinedTag:0xA434']))
                    {
                        $exif_camera_lens = trim($exif['EXIF']['UndefinedTag:0xA434']);
                    }
                    $exif_string = utf8_encode(serialize($exif));
                }
                else {
                    $exif = null;
                }
                if (isset($item->exifData['Camera make'])) $exif_make = $item->exifData['Camera make'];
                if (isset($item->exifData['Camera model'])) $exif_model = $item->exifData['Camera model'];
                if (isset($item->exifData['ISO equiv.'])) $exif_iso = $item->exifData['ISO equiv.'];

                $info = Array(
                    "type" => "photo",
                    "id" => $content_id,
                    "caption" => clean_text($item->caption),
                    "image_file" => $image_file,
                    "capture_date" => $item->itemCaptureDate,
                    "upload_date" => $item->uploadDate,
                    "width" => $item->image->width,
                    "height" => $item->image->height,
                    "aspect_ratio" => round($item->image->width/$item->image->height,3),
                    "exif_string" => $exif_string,
                    "exif_make" => $exif_make,
                    "exif_model" => $exif_model,
                    "exif_iso" => $exif_iso,
                    "exif_camera_serial" => $exif_camera_serial,
                    "exif_camera_lens" => $exif_camera_lens,
                    "highlight" => ($item->highlight==1) ? true : false,
                    "visibility" => ($item->hidden==1) ? 2 : 0
                );
                $content_id++;
                $infos[]=$info;
            }
        }
    }
    usort($infos,"order_content");
    return $infos;
}

function remove_accents($str) {
    include("foreign_chars.php");
    return preg_replace(array_keys($foreign_characters), array_values($foreign_characters), utf8_encode($str));
}
function url_title($str) {
    $separator = "-";
    $q_separator = preg_quote($separator);
    $trans = array(
        '&.+?;'                 => '',
        '[^a-z0-9 _-]'          => '',
        '\s+'                   => $separator,
        '('.$q_separator.')+'   => $separator
    );

    $str = strip_tags($str);

    foreach ($trans as $key => $val)
    {  
        $str = preg_replace("#".$key."#i", $val, $str);
    }

    $str = strtolower($str);

    return trim($str);
}
function slug($str) {
    return url_title(remove_accents($str));
}

require_once("init.php");
Header("Content-Type: text/plain; charset=utf-8");
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit','512M');
$result=Array();
setlocale(LC_NUMERIC,"fr_CH");

$export_username = "username_of_account";
$gallery->session->username=$export_username;
$gallery->session->albumListPage = 1;
$albumDB = new AlbumDB(FALSE);

$perPage=200;
list ($numPhotos, $numAccess, $numAlbums) = $albumDB->numAccessibleItems($gallery->user);
$start = ($gallery->session->albumListPage - 1) * $perPage + 1;
$end = min($start + $perPage - 1, $numAlbums);

$mysqli = new mysqli(KOKEN_SQL_HOST,KOKEN_SQL_USER,KOKEN_SQL_PASSWORD,KOKEN_SQL_DB);
$query = "SHOW TABLE STATUS LIKE '".KOKEN_PREFIX."albums'";
$res = $mysqli->query($query);
$row = $res->fetch_assoc();
$album_id = $row['Auto_increment'];
$query = "SHOW TABLE STATUS LIKE '".KOKEN_PREFIX."content'";
$res = $mysqli->query($query);
$row = $res->fetch_assoc();
$content_id = $row['Auto_increment'];
$query = "select max(left_id)+1 as left_id_start from ".KOKEN_PREFIX."albums";
$res = $mysqli->query($query);
$row = $res->fetch_assoc();
$left_id_start = intval($row['left_id_start']);

$content = Array();
for ($i = $start; $i <= $end; $i++) {
    if(!$gallery->album = $albumDB->getAlbum($gallery->user, $i)) {
        echo gallery_error(sprintf(gTranslate('core', "The requested album with index %s is not valid"), $i));
        continue;
    }
    $isRoot = $gallery->album->isRoot(); // Only display album if it is a root album
    $owner = $gallery->album->getOwner();
    if($isRoot && $gallery->album->fields["name"]==$root_album) {

        $level = 0;
        $child = $gallery->album;
        $title = clean_text($child->fields['title']);
        $description = trim(clean_text($child->fields['description']));
        $name = clean_text($child->fields['name']);
        $matches = Array();
        preg_match("/^(\d{8})[\/| ](.*)$/",$title,$matches);
        if (sizeof($matches)>0) {
            $title=$name=$matches[2];
            if ($description=="") {
                $date=mktime(0,0,0,substr($matches[1],4,2),substr($matches[1],6,2),substr($matches[1],0,4));
                setlocale(LC_TIME, "fr_CH");
                $description=strftime("%d %B %Y",$date);
            }
        }
        $info = Array(
            "type" => "album",
            "id" => $album_id,
            "name"=>$name,
            "source"=>convert_text($child->fields['name']),
            "title"=>$title,
            "description"=>$description,
            "level"=>$level
        );
        $album_id++;
        $info['items'] = get_content($child,$level+1);
        $info['upload_date'] = get_creation($info['items']);
        $infos[]=$info;
        $content = $infos;
    }
}
$fake_content=Array(
    'title' => "root",
    'id' => 0,
    'type' => "album",
    'items' => $content
);
$sqlfp = fopen("/tmp/koken.sql","wb");
$shfp = fopen("/tmp/koken.sh","wb");
$mapfp = fopen("/tmp/koken.txt","wb");
fwrite($shfp,"#!/bin/bash\n");
create_sql($mysqli,$content);
rebuild_tree($content[0],$left_id_start);
fwrite($shfp,"cd ".KOKEN_PATH."storage/originals/\n");
fwrite($shfp,"chmod -R 666 *\n");
fwrite($shfp,"chmod -R +X *\n");
fwrite($shfp,"cat /tmp/koken.sql | mysql -u ".KOKEN_SQL_USER." --password=".KOKEN_SQL_USER." ".KOKEN_SQL_DB."\n");
fclose($sqlfp);
fclose($shfp);
fclose($mapfp);

function create_sql($mysqli,$content,$parent=null) {
    global $sqlfp,$shfp,$mapfp;
    $order = 1;

    foreach ($content as $val) {
        if ($val['type']=="album") {
            print $val['title']." (".$val['id'].")\n";
            $title = $mysqli->real_escape_string($val['title']);
            $slug = $mysqli->real_escape_string(slug($val['name']));
            $summary = $mysqli->real_escape_string($val['description']);
            $internal_id = md5($slug);
            $created_at = $val['upload_date'];
            $level = $val['level']+1;
            $last=sizeof($val['items'])-1;
            if ($val['items'][$last]['type']=="photo") $album_type = 0;
            else $album_type = 2;
            $query = "INSERT into ".KOKEN_PREFIX."albums (id,title,slug,summary,description,level,album_type,published_on,created_on,modified_on,internal_id,total_count) values (".$val['id'].",'".$title."','".$slug."','".$summary."','".$summary."', ".$level.", ".$album_type.",".$created_at.",".$created_at.", ".time().", '".$internal_id."',".sizeof($val['items']).")";
            fwrite($sqlfp,utf8_decode($query).";\n");
            $folder = ($album_type == 2) ? "sets" : "albums";
            fwrite($mapfp,urlencode(utf8_decode($val['source']))." "."http://".KOKEN_URL."/".$folder."/".slug($val['name'])."\n");
            create_sql($mysqli,$val['items'],$val);
        }
        if ($val['type']=="photo") {
            $pathinfo = pathinfo($val['image_file']);
            $filename = $mysqli->real_escape_string($pathinfo['basename']);
            $caption = $mysqli->real_escape_string($val['caption']);
            $filesize = filesize($val['image_file']);
            $slug = $mysqli->real_escape_string(slug($parent['name']." ".$pathinfo['filename']));
            $internal_id = md5($slug);

            print $filename."\n";

            $query = "INSERT into ".KOKEN_PREFIX."content ";
            $query.=" (id,slug,filename,caption,visibility,filesize,width,height,aspect_ratio,published_on,uploaded_on,captured_on,modified_on,internal_id,exif,exif_make,exif_model,exif_iso,exif_camera_serial,exif_camera_lens)";
            $query.=" values";
            $query.=" (".$val['id'].",'".$slug."','".$filename."','".$caption."',".$val['visibility'].",".$filesize.",'".$val['width']."','".$val['height']."','".$val['aspect_ratio']."',".$val['upload_date'].",".$val['upload_date'].",".$val['capture_date'].",".$val['upload_date'].",'".$internal_id."'";
            $query.=($val['exif_string']===null) ? ",NULL" : ",'".$mysqli->real_escape_string($val['exif_string'])."'";
            $query.=($val['exif_make']===null) ? ",NULL" : ",'".$mysqli->real_escape_string($val['exif_make'])."'";
            $query.=($val['exif_model']===null) ? ",NULL" : ",'".$mysqli->real_escape_string($val['exif_model'])."'";
            $query.=($val['exif_iso']===null) ? ",NULL" : ",'".$mysqli->real_escape_string($val['exif_iso'])."'";
            $query.=($val['exif_camera_serial']===null) ? ",NULL" : ",'".$mysqli->real_escape_string($val['exif_camera_serial'])."'";
            $query.=($val['exif_camera_lens']===null) ? ",NULL" : ",'".$mysqli->real_escape_string($val['exif_camera_lens'])."'";
            $query.=")";
            fwrite($sqlfp,utf8_decode($query).";\n");
            $newfile = KOKEN_PATH."/storage/originals/".substr($internal_id,0,2)."/".substr($internal_id,2,2)."/".$pathinfo['basename'];
            fwrite($shfp,"mkdir -p ".dirname($newfile)."\n");
            fwrite($shfp,"cp ".$val['image_file']." ".$newfile."\n");
            $query = "INSERT into ".KOKEN_PREFIX."join_albums_content (album_id,content_id,`order`) values (".$parent['id'].",".$val['id'].",".$order.")";
            fwrite($sqlfp,$query.";\n");
            if ($val['highlight']===true) {
                $query = "INSERT into ".KOKEN_PREFIX."join_albums_covers (album_id,cover_id) values (".$parent['id'].",".$val['id'].")";
                fwrite($sqlfp,$query.";\n");
            }
            $order++;
        }
    }
    return true;
}

function rebuild_tree($content,$left) {
    global $sqlfp;
    $right = $left+1;
    if (array_key_exists("items",$content)) {
        $last=sizeof($content['items'])-1;
        if ($content['items'][$last]['type']=="photo") {
            $content['items']=Array();
        }
        foreach($content['items'] as $val) {
            $right = rebuild_tree($val,$right);
        }
        $query = "update ".KOKEN_PREFIX."albums set left_id=".$left.",right_id=".$right." where id='".$content['id']."'";
        fwrite($sqlfp,$query.";\n");
        return $right+1;
    }
    return $left;
}
