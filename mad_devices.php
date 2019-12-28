<?php
session_start();

$url	= 'http://'.$_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);	// "http" or "https"
$reload = 10;	// reload page in seconds

if(isset($_GET["spalte"]) and isset($_GET["sort"])) {
	$_SESSION["sort"] = '?spalte='.$_GET["spalte"].'&sort='.$_GET["sort"];
	$sortIndex = $_SESSION["sort"];
} elseif(isset($_SESSION["sort"])) {
	$sortIndex = $_SESSION["sort"];
}

?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="<?=$reload?>; URL=<?=$url?>/mad_devices.php<?=$sortIndex?>">
<title>MAD - Devices</title>
<style type="text/css">
td {
padding-left:5px;
padding-right:5px;
line-height:160%;
border-collapse: collapse
}

table {
border-collapse: collapse
}

a:link, a:visited {
color: Royalblue;
text-decoration: None;
}

a:hover, a:active {
color: Red;
}

#active {
color: #FF0000;
}

.warn {
background:#FFFF99
}

@media only screen and (min-width: 500px) {
  .mobile {
  	display:none
  }
}
</style>
</head>
<body>
<?php
require_once("config.php");

$mysqli = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);
if ($mysqli->connect_error) {
	die('Error : ('. $mysqli->connect_errno .') '. $mysqli->connect_error);
}

$spalten = array(
"origin"				=> "<b>Origin:</b><span class=\"mobile\"><br></span> ",
"r.name"				=> "<b>Route:</b><span class=\"mobile\"><br></span> ",
"t.routePos"			=> "<b>Pos:</b><span class=\"mobile\"><br></span> ",
"t.lastProtoDateTime"	=> "<b>Last Action:</b><span class=\"mobile\"><br></span> ");

$spalte = isset($_GET["spalte"]) ? $_GET["spalte"] : 't.lastProtoDateTime'; // Default-Wert
$sort = isset($_GET["sort"]) ? $_GET["sort"] : 'asc';

if (!array_key_exists($spalte ,$spalten)) {
	$spalte = 't.lastProtoDateTime'; // Default-Wert
}

if (!in_array($sort, array('desc', 'asc'))) {
	$sort = 'asc'; // Default-Wert
}

$sql = $mysqli->query("SELECT d.name AS origin, t.lastProtoDateTime, r.name, t.routePos, t.routeMax FROM settings_device d LEFT JOIN trs_status t ON d.name = t.origin LEFT JOIN settings_area r ON r.area_id = t.routemanager ORDER BY " . $spalte . " " . $sort .", origin ".$sort);

echo '<table><tr>';
foreach ($spalten as $spalte => $name) {
	if(isset($_GET["spalte"]) and $_GET["spalte"] == $spalte) {
		if($_GET["sort"] == 'asc') {
			$active = 'id="active"';
			$active2 = '';
		} elseif($_GET["sort"] == 'desc') {
			$active = '';
			$active2 = 'id="active"';
		}
	} else {
		$active = '';
		$active2 = '';
	}
	echo '<td>' .
	ucfirst($name) .
	'<a href="?spalte=' . $spalte . '&sort=asc" '.$active.' title="Aufsteigend sortieren">&#9650;</a>' .
	'<a href="?spalte=' . $spalte . '&sort=desc" '.$active2.' title="Absteigend sortieren">&#9660;</a>' .
	'</td>';
}
echo '</tr>';

while($row = $sql->fetch_array()) {
	if($row["lastProtoDateTime"] == NULL ) {
		echo "<tr style=\"background:#FF6666\"><td>".$row["origin"]."</td><td>N/A</td><td>N/A</td><td>not found in \"trs_status\"</td>";
	} else {
		if($row["lastProtoDateTime"] < date("Y-m-d H:i:s", strtotime('- 10 minutes'))) {
			$status = 'offline';
			$background = '#FFFF99';
		} else {
			$status = 'online';
			$background = '#66CC66';
		}
		
		$date_now = new DateTime();
		$date_row = new DateTime($row["lastProtoDateTime"]);
		$seconds = $date_now->getTimestamp() - $date_row->getTimestamp();
		
		$months = floor($seconds / (3600*24*30));
        $day = floor($seconds / (3600*24));
        $hours = floor($seconds / 3600);
        $mins = floor(($seconds - ($hours*3600)) / 60);
        $secs = floor($seconds % 60);
		
		if($seconds < 60) {
            $time = $secs." seconds ago";
        } else if($seconds < 60*60 ) {
            $time = "<span class=\"warn\">".$mins." min ago</span>";
        } else if($seconds < 24*60*60) {
            $time = $hours." hours ago";
        } else if($seconds < 24*60*60) {
            $time = $day." day ago";
        } else {
            $time = $months." month ago";
		}
		
		echo "<tr style=\"background:".$background."\"><td>".$row["origin"]."</td><td>".$row["name"]."</td><td>".$row["routePos"]."/".$row["routeMax"]."</td><td>$time</td>";
	}
}
echo '</table>';
?>
</body>
</html>