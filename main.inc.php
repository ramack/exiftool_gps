<?php
/*
Plugin Name: Exiftool GPS
Version: 0.7a
Description: Uses command line exiftool to read exif GPS data. (Plugin based on Exiftool Keywords.)
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=850
Author: ramack, plg
Author URI: http://www.raphael-mack.de
*/

if (!defined('PHPWG_ROOT_PATH'))
{
  die('Hacking attempt!');
}

function eg_format_exif_data($exif, $filepath, $map) {
	if (function_exists('exif_read_data')) {
		$metadata = exif_read_data($filepath, 'GPS');
		foreach ($metadata as $key => $val) {
			$exif[$key] = $val;
		}
	} elseif (!empty(shell_exec('which exiftool'))) {
		$output = shell_exec('exiftool -json "'.$filepath.'"');
		$metadata = json_decode($output, true);

		foreach ($metadata as $key => $section) {
			foreach ($section as $name => $val) {
				if(substr( $name, 0, 3 ) === "GPS") {
					if($name === "GPSLatitude" or $name === "GPSLongitude")	{
						/* convert to x/1 y/1 z/... format */
						$p1 = explode("deg", $val);
						$v1 = intval($p1[0]) . "/1";
						$p2 = explode("'", $p1[1]);
						$v2 = intval($p2[0]) . "/1";

						$p3 = explode("\"", $p2[1]);
						$v3 = intval($p3[0] * 10000) . "/10000";

						$exif[$name] = array($v1, $v2, $v3);
					} elseif($name === "GPSLatitudeRef" or $name === "GPSLongitudeRef") {
						$exif[$name] = substr($val, 0, 1);
					} else {
						$exif[$name] = $val;
					}
				}
			}
		} 
	} else {
			error_log('Error: EXIF-GPS could not find PHP-Exif nor exiftool');
	}
	return $exif;
}

add_event_handler('format_exif_data', 'eg_format_exif_data', EVENT_HANDLER_PRIORITY_NEUTRAL, 3);
?>

