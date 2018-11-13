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

add_event_handler('format_exif_data', 'eg_format_exif_data', EVENT_HANDLER_PRIORITY_NEUTRAL, 3);
function eg_format_exif_data($exif, $filepath, $map)
{

  $output = shell_exec('exiftool -json "'.$filepath.'"');
  $metadata = json_decode($output, true);

  $exif = array();
  if(is_array($metadata)) {
    foreach ($metadata as $key => $section) {
  	  if(!is_array($section)) {
  		  continue;
  	  }
      foreach ($section as $name => $val) {
        if(substr( $name, 0, 3 ) === "GPS")
        {
          if($name === "GPSLatitude" or $name === "GPSLongitude")
          {
            /* convert to x/1 y/1 z/... format */
            $p1 = explode("deg", $val);
            $v1 = intval($p1[0]) . "/1";
            $p2 = explode("'", $p1[1]);
            $v2 = intval($p2[0]) . "/1";
  
            $p3 = explode("\"", $p2[1]);
            $v3 = intval($p3[0] * 10000) . "/10000";
  
  	        $exif[$name] = array($v1, $v2, $v3);
  	      }
          else if($name === "GPSLatitudeRef" or $name === "GPSLongitudeRef")
  	      {
            $exif[$name] = substr($val, 0, 1);
          }
          else
          {
            $exif[$name] = $val;
          }
        }
      }
    }
  }
  return $exif;
}
?>
