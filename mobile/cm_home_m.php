<?php
session_start();
if (!$_SESSION){echo "You must be logged in to view this page.";die;}

?>

<html>
<head>
<title>ClinicCases Mobile - Main</title>
<link rel="stylesheet" href="mobile_style.css" type="text/css">
</head>
<body>




<h1>ClinicCases <span style="color:gray;font-style:italic;">Mobile</span></h1>
<P><form name="search_form" method="get" action="results_m.php"><strong>Search:</strong>
<select id="chooser" name="chooser">
<option value="address">Contact Information</option>
<option value="cases">Cases</option>
</select>
<input type="text" name="searchterm">
</form>
<strong>Choose an Activity</strong>
<ul>
<li><a href="messages_m.php">Messages</a></li>
<li><a href="cases_m.php">Your Open Cases</a></li>
<li><a href="recent_activity_m">Recent Activity</a></li>
<li><a href="upcoming_events_m.php">Upcoming Events</a></li>
<?php
if ($_SESSION['class'] = 'prof')
echo "<li><a href=\"\">Your Students</a>";

?>
<li><a href="logout_m.php">Logout</a></li>
</ul>
</body>
</html>

