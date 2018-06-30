<?php
// Configuration can be read from the ini file in the parent directory of the
// script.  Format is:
//
// [weather-cal]
// city = Paris
// units = C
// appkey = sdfksdjfklsdfasdf


// Variables used in this script:
$conf = parse_ini_file("../weather-cal.ini");
$unittype = array(
	"F" => "us",
	"C" => "si"
	);

$appkey = $conf['appkey']; // Get a API Key at https://openweathermap.org/appid

$long = isset($_GET['long']) ? $_GET['long'] : $conf['long'];
$lat = isset($_GET['lat']) ? $_GET['lat'] : $conf['lat'];
$units = isset($_GET['unittype']) ? $_GET['unittype'] : $conf['unittype'];
if (!array_key_exists($units, $unittype)) {
   die('Illegal unittype: ' . $units);
}
$summary = 'Weather for your calendar';

// Loading json
if (!$string = file_get_contents("https://api.darksky.net/forecast/" . $appkey. "/" . $lat . "," . $long . "?exclude=currently,minutely,hourly,flags")) {
   die('Error getting weather data');
}
$json = json_decode($string, true);
//
// Notes:
//  - the UID should be unique to the event, so in this case I'm just using
//    uuid
//
//  - iCal requires a date format of "yyyymmddThhiissZ". The "T" and "Z"
//    characters are not placeholders, just plain ol' characters. The "T"
//    character acts as a delimeter between the date (yyyymmdd) and the time
//    (hhiiss), and the "Z" states that the date is in UTC time. Note that if
//    you don't want to use UTC time, you must prepend your date-time values
//    with a TZID property. See RFC 5545 section 3.3.5
//
//  - The Content-Disposition: attachment; header tells the browser to save/open
//    the file. The filename param sets the name of the file, so you could set
//    it as "my-event-name.ics" or something similar.
//
//  - Read up on RFC 5545, the iCalendar specification. There is a lot of helpful
//    info in there, such as formatting rules. There are also many more options
//    to set, including alarms, invitees, busy status, etc.
//
//      https://www.ietf.org/rfc/rfc5545.txt
// 1. Set the correct headers for this file
header('Content-type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename=weather-cal.ics');
// 2. Define helper functions
// Converts a unix timestamp to an ics-friendly format
// NOTE: "Z" means that this timestamp is a UTC timestamp. If you need
// to set a locale, remove the "\Z" and modify DTEND, DTSTAMP and DTSTART
// with TZID properties (see RFC 5545 section 3.3.5 for info)
//
// Also note that we are using "H" instead of "g" because iCalendar's Time format
// requires 24-hour time (see RFC 5545 section 3.3.12 for info).
function dateToCal($timestamp) {
  return date('Ymd\THis\Z', $timestamp);
}
function dayToCal($timestamp) {
  return date('Ymd', $timestamp);
}
function nextDayToCal($timestamp) {
  return date('Ymd', strtotime('+1 day', $timestamp));
}
// Escapes a string of characters
function escapeString($string) {
  return preg_replace('/([\,;])/','\\\$1', $string);
}

date_default_timezone_set($json['timezone']);
// 3. Echo out the ics file's contents
?>
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//nettempo.com//v0.1//EN
X-WR-CALNAME:Weather for <?= $lat . "," . $long . '
' ?>
X-APPLE-CALENDAR-COLOR:#ffffff
CALSCALE:GREGORIAN

<?php
//print_r($json['list']);
foreach ($json['daily']['data'] as $key => $val) {
  //print_r($val);
  if (is_file("/proc/sys/kernel/random/uuid")) {
	  $uid = trim(file_get_contents("/proc/sys/kernel/random/uuid"));
  }
  else {
	  $uid = dayToCal($val['time']) . "@nettempo.com";
  }

  switch ($val['icon']) {
  	case 'clear-day':
  		$desc = 'Sunny️';
  		break;
  	case 'clear-night':
  		$desc = 'Clear?';
  		break;
  	case 'rain':
  		$desc = 'Rain';
  		break;
  	case 'snow':
  		$desc = 'Snow️';
  		break;
  	case 'sleet':
  		$desc = 'Sleet';
  		break;
  	case 'wind':
  		$desc = 'Wind';
  		break;
  	case 'fog':
  		$desc = 'Fog';
  		break;
  	case 'cloudy':
  		$desc = 'Cloudy';
  		break;
  	case 'partly-cloudy-day';
  		$desc = 'Partly Cloudy';
  		break;
  	case 'partly-cloudy-night';
  		$desc = 'Partly Cloudy';
  		break;
  	default:
  		$desc = 'See Details';
  		break;
  }
	?>

BEGIN:VEVENT
<?= trim(chunk_split('SUMMARY;LANGUAGE=en:' . $desc . ' ' . round($val['temperatureHigh']) . $units . "/" . round($val['temperatureLow']) . $units . " Precip:" . round($val['precipProbability']*100) . "% RH:" . round($val['humidity']*100) . '%', 74, "\n ")) . "\n" ?>
X-FUNAMBOL-ALLDAY:1
CONTACT:Powered by Dark Sky
UID:<?= $uid . '
' ?>
DTSTART;VALUE=DATE:<?= dayToCal($val['time']) . '
' ?>
LOCATION:<?= $lat . ',' . $long . '
' ?>
X-MICROSOFT-CDO-ALLDAYEVENT:TRUE
URL;VALUE=URI:https://darksky.net/poweredby/
DTEND;VALUE=DATE:<?= nextDayToCal($val['time']) . '
' ?>
X-APPLE-TRAVEL-ADVISORY-BEHAVIOR:AUTOMATIC
<?= trim(chunk_split('DESCRIPTION;LANGUAGE=en:' . $val['summary'], 74, "\n ")) . "\n" ?>
END:VEVENT
<?php
	}
?>

<?php
//print_r($json['list']);
if (isset($json['alerts']) ) {
    foreach ($json['alerts'] as $key => $val) {
        //print_r($val);
        if (is_file("/proc/sys/kernel/random/uuid")) {
            $uid = trim(file_get_contents("/proc/sys/kernel/random/uuid"));
        }
        else {
            $uid = dayToCal($val['time']) . "@nettempo.com";
        }

        ?>

BEGIN:VEVENT
<?= trim(chunk_split('SUMMARY;LANGUAGE=en:' . $val['title'], 74, "\n ")) . "\n" ?>
CONTACT:Powered by Dark Sky
UID:<?= $uid . '
' ?>
DTSTART;VALUE=DATE:<?= dateToCal($val['time']) . '
' ?>
LOCATION:<?= $lat . ',' . $long . '
' ?>
URL;VALUE=URI:https://darksky.net/poweredby/
DTEND;VALUE=DATE:<?= dateToCal($val['expires']) . '
' ?>
X-APPLE-TRAVEL-ADVISORY-BEHAVIOR:AUTOMATIC
<?= trim(chunk_split('DESCRIPTION;LANGUAGE=en:' . trim(json_encode($val['description']), '"'), 65, "\n ")) . "\n" ?>
END:VEVENT
        <?php
    }
}
?>


END:VCALENDAR
