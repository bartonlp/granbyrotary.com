<?php
// BLP 2014-07-17 -- removed admin from here and added it to includes/banner.i.php

define('TOPFILE', "/home/barton11/includes/siteautoload.php");
if(file_exists(TOPFILE)) {
  include(TOPFILE);
} else throw new Exception(TOPFILE . "not found");

session_cache_limiter('private');
session_start();

$d = date("U"); // date to force uncached GETs

// ********************************************************************************
// News Feed Logic. This is an Ajax call. This lets the rest of the page load quickly and the only
// thing that waits is the div 
// with the feed. We put a message there saying that the feed is loading.

if($_GET['page'] == 'rssinit') {
  $h->count = false;
  $S = new GranbyRotary($h);

  if($S->id) {
    // For members keep track of read news and don't show it again.
    // We create the $readfeeds array which has the title of the article as the key and the value
    // is set to 'start'. Later (below) any feed item that is in this array is marked 'skip'. Still
    // later (at the end) we look to see if any items in the $readfeeds array are still marked
    // 'start'. If there are entries that are still 'start' that means we did not find a feed with
    // that title and therefore we should delete the entry in the memberfeedinfo table because that
    // feed has expired.
    
    $readfeeds = array();
  
    try {
      $n = $S->query("select title from memberfeedinfo where id='$S->id'");
    } catch(Exception $e) {
      throw($e);
    }

    if($n) {
      while(list($r) = $S->fetchrow('num')) {
        if(!$r) $r = "NO TITLE";
        $readfeeds[strtolower($r)] = "start";
      }
    }

    $markasread = <<<EOF
<form action="$S->self" method="get">
<button name='markread' value="read">Mark All As Read</a></button>
<input type="hidden" name="date" value="$d"/>
EOF;
  
    if(count($readfeeds)) {
      $markasread .= <<<EOF
&nbsp;<button name='markread' value='unread'>Mark All As Unread</button>
EOF;
    }
    $markasread .= "</form>";
  }

  try {
    $feed = new RssFeed("http://www.skyhidailynews.com/csp/mediapool/sites/SwiftShared/assets/csp/rssCategoryFeed.csp?pub=SkyHiDaily&sectionId=817&sectionLabel=News");
  } catch(Exception $e) {
    if($e->getCode() == 5001) {
      echo "<span style='color: red'>Can't Connect to SkyHi new feed</span>";
      exit();
    }
    throw($e);
  }
  
  $rssFeed = $feed->getDb();

  // Use a session variable to pass the $rssFeed to the other ajax function
  
  $_SESSION['rssFeed'] = $rssFeed;
  
  $skyhinews = "";

  foreach($rssFeed as $i=>$f) {
    if(!$f['title']) {
      $f['title'] = "NO TITLE";
    }
    $title = strtolower($f['title']);

    if(isset($readfeeds[$title])) {
      $readfeeds[$title] = "skip"; 
      continue; // skip this one
    }

    // The new format is: Tue, 14 May 2013 17:29 MST. The time is no loger Tdd:dd:dd-
    $pubDate = $f['pubDate']; //preg_replace("/T(\d\d:\d\d:\d\d)-.*/", ' $1', $f['pubDate']);

    $pubDate = date("Y-m-d H:i", strtotime($pubDate));
    $skyhinews .= <<<EOF
<tr>
<td><a href='$S->self?item=$i#skyhianchor'>{$f['title']}</a></td>
<td>$pubDate</td>
</tr>

EOF;
  }

  if($skyhinews) {
    $skyhinews = <<<EOF
<p>Click on Headline to expand article.</p>
$markasread
<table id="skyhinewstbl" border="1" style="width: 100%">
<thead>
<tr><th>Headline</th><th>Date</th></tr>
</thead>
<tbody>
$skyhinews
</tbody>
</table>

EOF;
  } else {
    $skyhinews = <<<EOF
<h4>There are no unread news feeds at this time</h4>
<form action="$S->self" method="get">
<button name='markread' value='unread'>Mark all as Unread</button>
<input type="hidden" name="date" value="$d"/>
</form>
EOF;
  }

  echo $skyhinews;

  // Get rid of records that no longer have feeds active.
  // Do garbage collection of expired titles

  if($S->id) {
    // ONLY Members otherwise $readfeeds is empty and we will get an error.
    
    foreach($readfeeds as $k=>$v) {
      // If the record still has 'start' then we didn't find a feed
      
      if($v == "start") {
        // Remove this from the table
        $k = $S->escape($k);
        $S->query("delete from memberfeedinfo where title = '$k'");
      }
    }
  }

  exit();
}

// Ajax to get the individual articles. This can ONLY happend after the initial rssFeed has been
// loaded via the above Ajax call. 

if($_GET['page'] == 'ajaxinx') {
  $h->count = false;
  $S = new GranbyRotary($h);

  //cout("date: {$_GET['date']}");
  
  header("Content-type: text/plain");
  
  $rssFeed = $_SESSION['rssFeed'];

  $i = $_GET['ajaxinx'];
  $f = $rssFeed[$i];

  // Members can remember what they have already read.
  // So if this is a member 

  if($S->id) {
    // We keep track of the title for articles that the member has read.
    try {
      $title = $S->escape($f['title']);
      $query = "insert ignore into memberfeedinfo (title, id, date) values('$title', '$S->id', now())";
      $S->query($query);
    } catch(Exception $e) {
      throw($e);
    }
  }
  
  $pubDate = $f['pubDate']; //preg_replace("/T\d.*/", '', $f['pubDate']);

  echo <<<EOF
<div id="skyhinewsajaxitem" style="border: 10px groove white; padding: 5px; margin-bottom: 20px;">
<p><a target="_blank" href="{$f['link']}">{$f['title']}</a></p>
{$f['description']}
</div>
EOF;

  exit();
}

// ********************************************************************************
// Page Logic. Above is Ajax logic this is the main flow of the page

$S = new GranbyRotary;

// Mark All Feeds Read or Unread
// NOTE: the two forms are method="get" not POST

if($type = $_GET['markread']) {
  // From Ajax call at start

  $rssFeed = $_SESSION['rssFeed'];

  switch($type) {
    case "read":
      // Mark all of the feeds read
      if($rssFeed) {
        foreach($rssFeed as $f) {
          $title = $S->escape($f['title']);
          $sql = "insert ignore into memberfeedinfo (title, id, date) values('$title', '$S->id', now())";
          $n = $S->query($sql);
        }
      }
      break;
    case "unread":
      $n = $S->query("delete from memberfeedinfo where id='$S->id'");
      break;
    default:
      throw(new Exception("markread not read or unread: $type"));
  }
}

// If a member then keep track of feeds read

$S->lookedAtNews();

$s->siteclass = $S;
$s->site = "granbyrotary.org";
$s->page = "news.php";
$s->itemname ="Message";

$u = new UpdateSite($s); // Should do this outside of the START comments

// START UpdateSite Message "Important Message"
$item = $u->getItem();
// END UpdateSite Message

// If item is false then no item in table

if($item !== false) {
  $message = <<<EOF
<div>
<h2>{$item['title']}</h2>
<div>{$item['bodytext']}</div>
<p class="itemdate">Created: {$item['date']}</p>
</div>
<hr/>
EOF;
}

$page = "";
$hdr = "";

// Read news from database
  
$S->query("select article, rssfeed, articleInclude, created, expired, header, " .
                     "left(created, 10) as creat, left(expired, 10) as exp " .
                     "from articles where expired > now() order by pageorder, created desc");

while($row = $S->fetchrow("assoc")) {
  extract($row);
  switch($articleInclude ) {
    case "article":
      $story = $article;
      break;
    case "rss":
      $story = $rssfeed;
      break;
    case "both":
      $story = "$rssfeed\n$article";
      break;
  }
  if($exp == "2020-01-01") {
    $exp = "NEVER";
  }
    
  $page .= <<<EOF
<div>
$story 
</div>
<p style="color: brown; font-size: 10px; font-style: italic">Creation date: $creat, Expires: $exp</p>

<hr>

EOF;

  // $header is possible script/styles that should be added to the <head> section

  $hdr .= "$header";
}

$h->extra = <<<EOF
  <script>
jQuery(document).ready(function($) {
  // Please wait for news feed
  $("#skyhinews").html("<p style='color: red'>Please Wait While SkyHiNews Features are Loaded</p>"+
                       "<div style='text-align: center;'><img src='images/loading.gif'></div>");

  // Get the news feed

  var date = new Date;

  // add a date to the ajax calls to prevent caching!

  $.get("$S->self", { page: 'rssinit', date: date.getTime() }, function(data) {
    $("#skyhinews").html(data);
    var tr;

    $("#skyhinews").on("click", "a", function(e) {
      var href = $(this).attr("href");
      // If this is an anchor from the rssfeed then it has the format ?item=<number>#skihianchor.
      // If it is the anchor to the skihinew webpage with the full article it does NOT have the
      // above format and 'inx' will be null.
      var inx = href.match(/\?item=(\d+)/);

      if(inx == null) return true; // Return true and the anchor will be followed to the site.

      // if 'inx' is not null then it has a number that is the index for the article.

      var t = $(this).parent(); // t is the <td> of the <a>

      $.get("$S->self", { page: 'ajaxinx', ajaxinx: inx[1], date: date.getTime() }, function(data) {
        //$("#skyhinewsajaxitem").remove(); // remove the div
        // tr if set is the tr of the last article so hide it
        if(tr) {
          tr.hide(); //hide();
        }
        t.html(data); // replace the title with the thumbnail of the article
        // now get this article's tr 
        tr = t.parent(); // the tr of this item. This sets this so the above if works when it is set
        $("body").scrollTop(tr.offset().top);
      });
      return false; // don't do the <a> stay on this page
    });
  });

});
  </script>
  <style>
h1 {
  text-align: center;
}

#skyhinewstbl tbody tr td:nth-child(2) {
  font-family: Verdana;
  font-size: 10px;
  width: 120px;
}

#skyhinewstbl td {
  padding: 5px;
}
   </style>
   <!-- Possible head stuff from database -->
$hdr
   <!-- End database head stuff -->

EOF;

$date = date("l F d, Y");   

if($S->id != 0) {
  // Show Info for MEMBERS
  
  $greet = "<h2>News<br>$date<br>Welcome {$S->getUser()}</h2>";

} else {
  $greet = <<<EOF
<h2>News<br>$date</h2>
<h2>More to see on the web site if you <a href='login.php?return=/news.php'>login</a></h2>
<p>Not a Grand County Rotarian? You can <b>Register</b> as a visitor.
<a href="login.php?return=$S->self]&page=visitor">Register</a></p>

<p>If you are a member take this time to <a href='login.php?return=/news.php'>login</a>, it is easy.
   All you need is the email address you gave the club.
   After you login click on the <b>User Profile</b> item in the navigation menu above.
   You can add a seperate password, update your email
   address, phone number, and home address.
</p>

EOF;
}

$h->title = "Club News";
$h->banner = $greet;

list($top, $footer) = $S->getPageTopBottom($h);

/* BLP 2014-07-17 -- 
// Check to see if this member is a web administrator

if($S->isAdmin($S->id)) {
  // Make the Administrator's greeting
  
  $top .= $S->adminText();
}
*/

// Echo the UpdateSite message if one
echo <<<EOF
$top
<!-- Start UpdateSite: Important Message -->
$message
<!-- UpdateSite: Importand Message End -->
<div style="text-align: center; margin-top: 10px;">
<a target="_blank" href="http://www.wunderground.com/US/CO/Granby.html?bannertypeclick=miniStates">
<img src="http://weathersticker.wunderground.com/weathersticker/miniStates_both/language/www/US/CO/Granby.gif"
alt="Click for Granby, Colorado Forecast" border="0" height="100" width="150" /></a>
</div>

EOF;

// Upcoming meetings
include("upcomingmeetings.php");

echo <<<EOF
$page
<h2>Sky Hi News Feeds</h2>
<div id="skyhinewsitem">
<a name="skyhianchor"></a>
$skyhinewsitem
</div>
<div id="skyhinews">
<p>Not available without Javascript</p>
</div>
<hr/>
$footer
EOF;

//  -------------------------------------------------------------------------------------------
// Given a text string, calculate the width in pixles based on using Verdana 10px non-bold font
// Return: total pixel width.

// NOT USED 5/16/2013
function strlen_pixels($text) {
  /*
  Pixels utilized by each char (Verdana, 10px, non-bold)
  04: j
  05: I\il,-./:; <espace>
  06: J[]f()
  07: t
  08: _rz*
  09: ?csvxy
  10: Saeko0123456789$
  11: FKLPTXYZbdghnpqu
  12: A�BCERV
  13: <=DGHNOQU^+
  14: w
  15: m
  16: @MW
  */

  // CREATING ARRAY $ps ('pixel size')
  // Note 1: each key of array $ps is the ascii code of the char.
  // Note 2: using $ps as GLOBAL can be a good idea, increase speed
  // keys:    ascii-code
  // values:  pixel size

  // $t: array of arrays, temporary
  $t[] = array_combine(array(106), array_fill(0, 1, 4));

  $t[] = array_combine(array(73,92,105,108,44), array_fill(0, 5, 5));
  $t[] = array_combine(array(45,46,47,58,59,32), array_fill(0, 6, 5));
  $t[] = array_combine(array(74,91,93,102,40,41), array_fill(0, 6, 6));
  $t[] = array_combine(array(116), array_fill(0, 1, 7));
  $t[] = array_combine(array(95,114,122,42), array_fill(0, 4, 8));
  $t[] = array_combine(array(63,99,115,118,120,121), array_fill(0, 6, 9));
  $t[] = array_combine(array(83,97,101,107), array_fill(0, 4, 10));
  $t[] = array_combine(array(111,48,49,50), array_fill(0, 4, 10));
  $t[] = array_combine(array(51,52,53,54,55,56,57,36), array_fill(0, 8, 10));
  $t[] = array_combine(array(70,75,76,80), array_fill(0, 4, 11));
  $t[] = array_combine(array(84,88,89,90,98), array_fill(0, 5, 11));
  $t[] = array_combine(array(100,103,104), array_fill(0, 3, 11));
  $t[] = array_combine(array(110,112,113,117), array_fill(0, 4, 11));
  $t[] = array_combine(array(65,195,135,66), array_fill(0, 4, 12));
  $t[] = array_combine(array(67,69,82,86), array_fill(0, 4, 12));
  $t[] = array_combine(array(78,79,81,85,94,43), array_fill(0, 6, 13));
  $t[] = array_combine(array(60,61,68,71,72), array_fill(0, 5, 13));
  $t[] = array_combine(array(119), array_fill(0, 1, 14));
  $t[] = array_combine(array(109), array_fill(0, 1, 15));
  $t[] = array_combine(array(64,77,87), array_fill(0, 3, 16));  

  // merge all temp arrays into $ps
  $ps = array();
  foreach($t as $sub) $ps = $ps + $sub;
  
  // USING ARRAY $ps
  $total = 1;
  for($i=0; $i<strlen($text); $i++) {
    $temp = $ps[ord($text[$i])];
    if(!$temp) $temp = 10.5; // default size for 10px
    $total += $temp;
  }
  return $total;
}
?>
