<?php
// Update the list of speakers on the Meetings page
// 2013/10/11 we now select the members at random and generate a list that contains all the
// members.
// We no longer ask for the number of weeks because we always use all of the members.

define('TOPFILE', "/home/barton11/includes/siteautoload.php");
if(file_exists(TOPFILE)) {
  include(TOPFILE);
} else throw new Exception(TOPFILE . " not found");

$S = new GranbyRotary;
define(BUSINESS_MEETING_WEEK, 0); // first week of the month!

// Generate a list of meeting dates from today for six months.
// Select members from database starting at id#=x. Send out emails to
// members telling them of their assignments.

$startWeek = $_GET['start'];
$year = $_GET['year'];
$test = $_GET['test'];

// If the arguments are not set then show a start page to get the argumnets

if(empty($startWeek) || empty($year)) {
  // Show first page and get the arguments
  $h->banner = <<<EOF
<h1>Enter Arguments</h1>
EOF;
  list($top, $footer) = $S->getPageTopBottom($h);

  echo <<<EOF
$top
<p>This generates a randomly sorted list of all members starting with the week of the year following
the last date on the current Meetings list.</p>
<p>Use the calendar app to get the week of the year to start with.
Enter the year we are starting in, for example if the last entry in the current list is in 2011
and the first new entry would also be in 2011 then enter 2011.
To test only and NOT update the Meetings table enter a 1 in 'Test Only' otherwise leave blank or
enter a zero.</p>

<form method="GET">
Start Week of Year: <input type="text" name="start"/><br>
Year: <input type="text" name="year"/><br>
Test Only: <input type="text" name="test"/><br>
<input type="submit" />
</form>
$footer
EOF;
  exit();
}
--$startWeek;

$query = "select id, concat(FName, ' ', LName) as name ".
         "from rotarymembers where status='active' order by rand()";

$S->query($query);

while(list($id, $name) = $S->fetchrow('num')) {
  $idName[] = "$id:$name";
}

for($i=$startWeek; $i < $startWeek + count($idName); ++$i) {
  $date = find_first_day_ofweek($i, $year, 'wednesday');
  $dates[] = $date;
}

foreach($idName as $k=>$v) {
  // Wed is day three starting with Sunday as 0
  // Second week, first week is 0

  if(isDay($dates[$k], 3, BUSINESS_MEETING_WEEK)) {
    $eout[] = "0:Business Meeting:$dates[$k]";
    continue;
  }
  //echo "in % count=" . $in % count($idName) . "<br>";
  $item = $v;
  //echo "item=$item<br>";
  list($id, $name) = split(":", $item);

  $date = find_first_day_ofweek($k+$startWeek, $year, 'wednesday');
  $eout[] = "$id:$name:$date";
}

foreach($eout as $value) {
  list($id, $name, $date) = split(":", $value);
  $date = date('Y-m-d', $date);
  $type = "speaker";
  if($id == 0) {
    $type = "business";
  }
  $query = "insert into meetings (name, date, id, type, subject) values('$name', '$date', '$id', '$type', NULL) ".
           "on duplicate key update name='$name', id='$id', type='$type'";

  if(!$test)  {
    $S->query($query);
    echo "$name, $date, $id, $type<br>";
  } else {
    echo "$query<br>\n";
  }
}

// Business meetings are the first Wed of the month
// $data is unix date
// $oday is the target day of the week, sun=0
// $week is the number of weeks from the first target day of the
// week. $week == 0 means the first target day, etc.
// Returns true or false

function isDay($date, $oday, $week) {
  list($yr, $mo, $day) = split("-", date("Y-m-d",$date));
  $ut = strtotime("$yr-$mo-01");
  $oned = date("w", $ut);

  // Number of weeks from target day of week
  
  $w = $week * 7;

  if(($oned - $oday) <= 0) {
    // If the day of the week is less than or equal to our target day
    // Get the number of bays before target, plus one plus weeks
    $d = ($oday - $oned) + $w +1;
  } else {
    // If the day of the week is greater than our target day
    // Move ahead a week then subtract the target from the day of the
    // week, add the weeks plus one day.
    $d = 7 - ($oned - $oday) + $w +1;
  }

  $t = "$yr-$mo-$d";
  $t = strtotime("$t");

  if($date == $t) {
    return true;
  }
  return false;
}

/**
 * Function to find the day of a week in a year
 * @param integer $week The week number of the year
 * @param integer $year The year of the week we need to calculate on
 * @param string  $start_of_week The start day of the week you want returned
 *                Monday is the default Start Day of the Week in PHP. For
 *                example you might want to get the date for the Sunday of wk 22
 * @return integer The unix timestamp of the date is returned
 */

function find_first_day_ofweek($week, $year, $start_of_week='sunday') {
   // Get the target week of the year with reference to the starting day of
   // the year
   $target_week = strtotime("$week week", strtotime("1 January $year"));

   // Get the date information for the day in question which
   // is "n" number of weeks from the start of the year
   $date_info = getdate($target_week);

   // Get the day of the week (integer value)
   $day_of_week = $date_info['wday'];

   // Make an adjustment for the start day of the week because in PHP the
   // start day of the week is Monday
   switch (strtolower($start_of_week)) {
       case 'sunday':
           $adjusted_date = $day_of_week;
           break;
       case 'monday':
           $adjusted_date = $day_of_week-1;
           break;
       case 'tuesday':
           $adjusted_date = $day_of_week-2;
           break;
       case 'wednesday':
           $adjusted_date = $day_of_week-3;
           break;
       case 'thursday':
           $adjusted_date = $day_of_week-4;
           break;
       case 'friday':
           $adjusted_date = $day_of_week-5;
           break;
       case 'saturday':
           $adjusted_date = $day_of_week-6;
           break;

       default:
           $adjusted_date = $day_of_week-1;
           break;
   }

   // Get the first day of the weekday requested
   $first_day = strtotime("-$adjusted_date day",$target_week);

   //return date('l dS of F Y h:i:s A', $first_day);

   return $first_day;
}
?>