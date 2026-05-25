<?php
/*
Plugin Name: Exiftool GPS
Version: 0.8
Description: Uses command line exiftool to read exif GPS data. (Plugin based on Exiftool Keywords.)
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=850
Author: ramack, plg
Author URI: http://www.raphael-mack.de
*/

if (!defined('PHPWG_ROOT_PATH'))
{
  die('Hacking attempt!');
}

/**
 * Parses a GPS coordinate string in DMS, DM, or decimal formats
 * and returns an array of three rational strings (degrees, minutes, seconds)
 * compatible with Piwigo/EXIF standards, or null on failure.
 *
 * @param mixed $val
 * @return array|null
 */
function eg_parse_coordinate($val)
{
  if (!is_string($val) && !is_numeric($val)) {
    return null;
  }

  $val = trim((string)$val);
  if ($val === '') {
    return null;
  }

  // Normalize spaces
  $val = preg_replace('/\s+/u', ' ', $val);

  // Normalize degree symbols
  $normalized = str_ireplace(array('deg', '°', 'o', 'd.'), 'd', $val);

  // Normalize minute symbols
  $normalized = str_ireplace(array('’', '′', 'min', '\''), 'm', $normalized);

  // Normalize second symbols
  $normalized = str_ireplace(array('”', '″', 'sec', 's', "''", '"'), 's', $normalized);

  // 1. DMS (Degrees, Minutes, Seconds) - e.g., 46 d 32 m 35.54 s
  if (preg_match('/(\d+(?:\.\d+)?)\s*d\s*(\d+(?:\.\d+)?)\s*m\s*(\d+(?:\.\d+)?)\s*s/i', $normalized, $matches)) {
    $degrees = floatval($matches[1]);
    $minutes = floatval($matches[2]);
    $seconds = floatval($matches[3]);

    return array(
      intval($degrees) . "/1",
      intval($minutes) . "/1",
      intval(round($seconds * 10000)) . "/10000"
    );
  }

  // 2. DM (Degrees, Minutes) - e.g., 46 d 32.5923 m
  if (preg_match('/(\d+(?:\.\d+)?)\s*d\s*(\d+(?:\.\d+)?)\s*m/i', $normalized, $matches)) {
    $degrees = floatval($matches[1]);
    $minutes_decimal = floatval($matches[2]);
    $minutes = floor($minutes_decimal);
    $seconds = ($minutes_decimal - $minutes) * 60;

    return array(
      intval($degrees) . "/1",
      intval($minutes) . "/1",
      intval(round($seconds * 10000)) . "/10000"
    );
  }

  // 3. Degrees only with 'd' suffix - e.g., 46.54321 d
  if (preg_match('/(\d+(?:\.\d+)?)\s*d/i', $normalized, $matches)) {
    $decimal = floatval($matches[1]);
    $degrees = floor($decimal);
    $minutes_decimal = ($decimal - $degrees) * 60;
    $minutes = floor($minutes_decimal);
    $seconds = ($minutes_decimal - $minutes) * 60;

    return array(
      intval($degrees) . "/1",
      intval($minutes) . "/1",
      intval(round($seconds * 10000)) . "/10000"
    );
  }

  // 4. Pure Decimal - e.g., -46.54321 or 46.54321 N
  if (preg_match('/[-+]?\d+(?:\.\d+)?/', $normalized, $matches)) {
    $decimal = abs(floatval($matches[0]));
    $degrees = floor($decimal);
    $minutes_decimal = ($decimal - $degrees) * 60;
    $minutes = floor($minutes_decimal);
    $seconds = ($minutes_decimal - $minutes) * 60;

    return array(
      intval($degrees) . "/1",
      intval($minutes) . "/1",
      intval(round($seconds * 10000)) . "/10000"
    );
  }

  return null;
}

add_event_handler('format_exif_data', 'eg_format_exif_data', EVENT_HANDLER_PRIORITY_NEUTRAL, 3);
function eg_format_exif_data($exif, $filepath, $map)
{
  $json_string = shell_exec('exiftool -json "'.$filepath.'"');
  /* Correctif suggéré Mistral 28/11/2025 */
  if ($json_string === null)
  {
    $json_string = '{}'; // ou une valeur par défaut appropriée
  }
  $metadata = json_decode($json_string, true);

  if (!is_array($metadata))
  {
    return $exif;
  }

  foreach ($metadata as $key => $section) {
    if (!is_array($section)) {
      continue;
    }
    foreach ($section as $name => $val) {
      if (is_string($name) && substr($name, 0, 3) === "GPS")
      {
        if ($name === "GPSLatitude" or $name === "GPSLongitude")
        {
          $parsed = eg_parse_coordinate($val);
          if ($parsed !== null)
          {
            $exif[$name] = $parsed;
          }
        }
        else if ($name === "GPSLatitudeRef" or $name === "GPSLongitudeRef")
        {
          if (is_string($val) && strlen($val) > 0)
          {
            $exif[$name] = substr($val, 0, 1);
          }
        }
        else
        {
          $exif[$name] = $val;
        }
      }
    }
  }

  return $exif;
}
?>
