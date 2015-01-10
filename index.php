<?php
// BLP 2014-11-23 -- work on responsive scaling. Modify css/rotary.css and move some
// stuff to here that only applies to index.php
// BLP 2014-07-17 -- removed admin from here and added it to includes/banner.i.php
define('TOPFILE', "/home/barton11/includes/siteautoload.php");
if(file_exists(TOPFILE)) {
  include(TOPFILE);
} else throw new Exception(TOPFILE . "not found");

$S = new GranbyRotary;

$x = glob("banner-photos/*");
foreach($x as $v) {
  $banner_photos .= "\"$v\",";
}
$banner_photos = rtrim($banner_photos, ",");

// For a fair description of how the updatesite class works look at the class file.
// I have updated the comments quite a lot.
// Check out the admintext.php file and the updatesite.php and updatesite2.php files.

//require_once("includes/updatesite.class.php");

$s->siteclass = $S;
$s->page = "index.php"; // the name of this page
$s->itemname ="PresidentMsg"; // the item we want to get first

$u = new UpdateSite($s); // Should do this outside of the '// START UpdateSite ...' comments

// Now getItem gets the info for the $s->itemname sections
// The special comments around each getItem() are MANDATORY and are used by the UpdateSite class
// to maintain the information in the 'site' table in the bartonphillipsdotorg database at
// bartonphillips.com

// START UpdateSite PresidentMsg "President's Message"
$item = $u->getItem();
// END UpdateSite PresidentMsg

// If item is false then no item in table

if($item !== false) {
  $presidentmsg = <<<EOF
<div>
<h2>{$item['title']}</h2>
<div>{$item['bodytext']}</div>
<p class="itemdate">Created: {$item['date']}</p>
</div>
<hr/>
EOF;
}

// To get subsequent sections just set the itemname and call getItem with the $s with the new
// itemname set.

$s->itemname ="Polio";

// START UpdateSite Polio
$item = $u->getItem($s);
// END UpdateSite Polio

if($item !== false) {
  $endpolio = <<<EOF
<div>
<h2>{$item['title']}</h2>
<div>{$item['bodytext']}</div>
<p class="itemdate">Created: {$item['date']}</p>
</div>
<hr/>
EOF;
}

$s->itemname ="OtherStuff";

// START UpdateSite OtherStuff
$item = $u->getItem($s);
// END UpdateSite OtherStuff

if($item !== false) {
  $otherstuff = <<<EOF
<div>
<h2>{$item['title']}</h2>
<div>{$item['bodytext']}</div>
<p class="itemdate">Created: {$item['date']}</p>
</div>
<hr/>
EOF;
}

// This is the END of the UpdateSite section
// *****

// If the 'memberid' is set in $_GET then automagically log the member in.
// This is set via member emails etc.

if(isset($_GET['memberid'])) {
  $mid = $_GET['memberid'];

  // The member is responding to an email with the query ?memberid=id
  // Set the member cookie
  $S->SetIdCookie($mid); // This sets the browser's cookie but not php's $_COOKIE
  $_COOKIE['SiteId'] = $mid;  // force $_COOKIE[GrId] to be $mid so we can set everything with CheckId!!!
  $S->CheckId();  // sets all of the GXxxx publics
}
  
//$NewBBmsg = $S->checkBBoard(); // Check if new BB posts

// set up the scripts and styles

$h->extra = <<<EOF
  <!-- local extra -->
  <script async src="js/dropdown.js"></script>
EOF;

$h->script = <<<EOF
   <!-- local script -->
   <script>
var bannerImages = new Array, binx = 0;

// Banner photos

dobanner(new Array($banner_photos));

function dobanner(photos) {
  for(var i=0, l=0; i < photos.length; ++i) {
    var image = new Image;
    image.inx = i;
    image.src = photos[i];

    $(image).load(function() {
      bannerImages[this.inx] = this;
    });

    $(image).error(function(err) {
      console.log(err);
    });
  }
};

function bannershow() {
  if(binx > bannerImages.length) {
    binx = 0;
  }

  var image = bannerImages[binx++];
  //$(image).css({width: '100%', height: '100%', 'z-index': '100'});

  $("#header-image").html(image);
  $("#wheel, #granbyrotarytext").remove();
  $("#header-image").append("<img id='wheel' src='images/wheel.gif'/>"+
                  "<img id='granbyrotarytext' src='images/text-granbyrotary.png'/>");

  setTimeout(bannershow, 5000);
}

$(window).load(function() {
  bannershow();
});

jQuery(document).ready(function($) {
  $("#child").hide();

  $("#parent").click(function() {
    if(!this.flag)
      $("#child").show();
    else
      $("#child").hide();
    this.flag = !this.flag;
  });
});
  </script>
EOF;

$h->css = <<<EOF
  <!-- local css -->
  <style>
#loginMsg {
        text-align: center;
}
#loginMsg a {
        font-variant: small-caps;
        font-size: x-large;
}
/* class button is also in rotary.css so check there too if thing change */
.button {
        border: 4px outset gray; 
        text-align: center;
        background-color: red;
        color: white;
        cursor: pointer;
        width: 280px;
}
.button a {
        color: white;
}

/* style for drop down */
#rotarylinks  {
        border: 0;
        width: 25%;
        margin-left: auto;
        margin-right: auto;
        margin-top: 30px;
        margin-bottom: 30px;
}
#button-group {
        margin-bottom: 10px;
}
#parent {
        cursor: pointer;
        margin: 0;
}
#child {
        display: inline;
}
#child a {
        border: 1px solid black;
        display: block;
        padding: 2px 5px;
        background-color: white; /* #FFFFEE; */
        line-height: 50px;
}

/* Who table */
#who { /* who has birthday/been here */
        width: 50%;
        margin-bottom: 10px;
        margin-right: 5px;
}
.who {
        background-color: white;
        width: 100%;
}
.who thead tr:nth-child(2) th:first-child {
        width: 60%;
}
.who
 td {
        padding: 5px;
}
@media (max-width: 600px) {
        .who {
                width: 100%;
                font-size: 80%;
        }
}
  </style>
EOF;

// Check if a member  

if($S->id != 0) {
  // MEMBER

  $memberOrLogin = <<<EOF
<h3 id='loginMsg'>Welcome {$S->getUser()}.</h3>
EOF;
} else {
  // NOT A MEMBER OF NOT LOGGED IN

  $memberOrLogin = <<<EOF
<h3 id='loginMsg'>If you are a Grand County Rotarian please
<a href='login.php?return=$S->self'>Login</a> at this time.<br/>
There is a lot more to see if you <a href='login.php?return=$S->self'>Login</a>!
</h3>
<p class="center">Not a Grand County Rotarian? You can <b>Register</b> as a visitor.
<a href="login.php?return=$S->self&amp;page=visitor">Register</a></p>
EOF;
}


/* BLP 2014-07-17 -- Remove. Added to includes/banner.i.php
// Check to see if this member is a web administrator

if($S->isAdmin($S->id)) {
  // Make the Administrator's greeting
  
  $AdminText = $S->adminText();
}
*/
  
$h->banner = <<<EOF
      <p>PO Box 1430<br/>
      Granby, CO 80446<br/>
      e-mail:
      <a href='mailto:info@granbyrotary.org'>info@granbyrotary.org</a><br/>

      Meets Wednesday 12:00 PM at<br/>
      <a target="_blank" href='http://www.mavericksgrille.com/'>Maverick's Grille</a><br>
      15 E. Agate Avenue (US Highway 40)<br>
      Granby, Colorado 80446<br>
      Phone: (970) 887-9000<br>
      <a target="_blank" href="http://maps.google.com/maps?hl=en&amp;q=Maverick's%20Grille%2015%20E.%20Agate%20Ave.%20Granby%20CO%2080446">Map</a>
      </p>
$memberOrLogin$AdminText
EOF;

$h->title ="Rotary Club of Granby CO.";

// Set up the footer info
$b = new stdClass;
$b->msg2 = <<<EOF
<p class="center"><a href='aboutwebsite.php'>About This Site</a></p>
EOF;

// Get the page top and footer

list($top, $footer) = $S->getPageTopBottom($h, $b);

$S->query("select concat(fname, ' ', lname), bday, day(bday) as day from rotarymembers ".
          "where month(bday) = month(now()) ".
          "and status='active' and otherclub='granby' ".
          "order by day");

while(list($bname, $bday, $bdayday) = $S->fetchrow('num')) {
  if($bdayday < 20 && $bdayday > 9) {
    $ext = 'th';
  } else {
    switch(substr($bdayday, -1)) {
      case '1':
        $ext = 'st';
        break;
      case '2':
        $ext = 'nd';
        break;
      case '3':
        $ext = 'rd';
        break;
      default:
        $ext = 'th';
        break;
    }
  }
  $whoHasABirthdayThisMonth .= "<tr><td>$bname's birthday is on the ".
                               "$bdayday<sup>$ext</sup></td></tr>\n";
}
$whoHasABirthdayThisMonth = <<<EOF
<table class="who" border="1">
<thead>
<tr><th>Who Has A Birthday This Month</th></tr>
</thead>
<tbody>
$whoHasABirthdayThisMonth
</tbody>
</table>
EOF;

$whosBeenHereToday = $S->getWhosBeenHereToday();

$mostart = date("Y-m-01");

$n = $S->query("select concat(fname, ' ', lname) from daycounts as d ".
               "left join rotarymembers as r on d.id=r.id where d.id!=0 and ".
               "date >='$mostart' ".
               "group by d.id, date");

$namecnt = array();

while(list($name) = $S->fetchrow()) {
  $namecnt[$name]++;
}

foreach($namecnt as $k=>$v) {
  $memlist .= <<<EOF
<tr><td>$k</td><td>$v</td></tr>
EOF;
}

$moTotal = count($namecnt);

$whosBeenHereThisMonth = <<<EOF
<table class="who" border="1">
<thead>
<tr>
<th colspan="2">Total Members or Vistors using the site this month: $moTotal</th>
</tr>
<tr>
<th>Who Visited This Month?</th>
<th>Number of Days Visited</th>
</tr>
</thead>
<tbody>
$memlist
</tbody>
</table>
EOF;

// ************************
// Start to Render the page
// ************************

// Has the member seen all the BB posts?
/*
if($NewBBmsg) {
  $bb = <<<EOF
<p class='button' onclick='location.href="bboard.php";'>
<a href='bboard.php'>Bulletin Board $NewBBmsg</a>
</p>

EOF;
} else {
  $bb = <<<EOF
<p class='button' onclick='location.href="bboard.php";'>
<a href='bboard.php'>Bulletin Board</a>
</p>

EOF;
}
*/
// Render page
  
echo <<<EOF
$top
<!-- START UpdateSite: PresidentMsg -->
$presidentmsg
<!-- UpdateSite: PresidentMsg End -->
<h2>Who We Are</h2>
<p>The Rotary Club of Granby was chartered in 1987, and its membership includes men and women representing a wide cross-section of
local businesses and professions. The club meets each Wednesday for fellowship, lunch, and interesting and informative programs
dealing with topics of local and global importance.</p>
<p>The 2014-2015 Club President is
<a href="email.php?id=189">Sean Richardson</a>.
See the <a href="about.php#officerstbl">About Page</a> for a full list of officers and chair persons.</p>
<p>The club is part of Rotary District 5450, comprised of 51 clubs and over 3,000 members.
The 2014-2015 Rotary District Governor
is <a target="_blank" href="http://www.clubrunner.ca/portal/Home.aspx?accountid=50085">Peter Ewing</a>.</p>
<p>The 2014-2015 President of Rotary International is Gary C.K. Huang.</p>

<hr/>
<!-- Start UpdateSite: Polio Info -->
$endpolio
<!-- UpdateSite: Polio Info End -->
<!-- Start UpdateSite: Other Stuff -->
$otherstuff
<!-- UpdateSite: Other Stuff End -->

<div id="button-group">
<img src="images/find_us_on_facebook_badge.gif" title="Find Us On Facebook" alt="find us on facebook" /><br>
<a target="_blank" href="http://www.facebook.com/group.php?gid=105501794053">Rotary Club of Granby</a><br>
<a target="_blank" href="http://www.facebook.com/home.php?ref=home#!/pages/MPHS-Interact-Club/123052577747543">
Middle Park High School Interact Club on Facebook</a>

<!-- Rotary Links -->
<div id="rotary-links">
  <label for="rotary-links-checkbox">Rotary Links</label>
  <input type="checkbox" id="rotary-links-checkbox" role="button">
  <ul id="rotary-links-menu">
    <li><a target="_blank" href="http://www.clubrunner.ca/portal/Home.aspx?accountid=50085">District 5450 Web Site</a>
    <li><a target="_blank" href="http://www.rotary.org/">Rotary International Web Site</a>
    <li><a target="_blank" href="http://www.endpolio.com">District 5450 End Polio Campaign</a>
    <li><a target="_blank" href='http://rmryla.org'>RYLA</a>
    <li><a target="_blank" href='http://WinterparkFraserRotary.org'>Winter Park Rotary Club</a>
    <li><a target="_blank" href='http://www.grandlakerotary.org/'>Grand Lake Rotary Club</a>
    <li><a target="_blank" href='http://www.kremmlingrotary.org'>Kremmling Rotary Club</a>
    <li><a target="_blank" href='http://escuelaminga.org/Minga/Rotary.html'>The Equator Project</a>
  </ul>
</div>

<div id="who">$whoHasABirthdayThisMonth
<br>
$whosBeenHereToday
<br>
$whosBeenHereThisMonth
</div>
<hr class="clearboth"/>
$footer
EOF;

?>