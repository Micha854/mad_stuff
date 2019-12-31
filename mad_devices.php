<?php
session_start();

// CONFIG THIS PART ######################################################################
$url	= 'https';		// "http" or "https"

$reload = 30;		  // reload page in seconds
$wartime= 180;		  // es wird nach dieser zeit (sekunden) lediglich eine warnung angezeigt
$offline= 10;		  // Geräte nach 10 Minuten als Offline kennzeichnen und Benachrichtigen

$beep	= 'beep.mp3'; // mp3 sound file
$notify = 1200;		  // bei offline erneute Benachrichtigung nach 20 Minuten

$breite	= '100%';	  // tabellenbreite, angabe in % oder px
$size	= '26px';	  // font-size in px or pt | not work on mobile
##########################################################################################





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
<meta http-equiv="refresh" content="<?=$reload?>; URL=<?=$url?>">
<meta http-equiv="refresh" content="<?=$reload?>; URL=<?=$url."://".$_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . $sortIndex?>">
<title>MAD - Devices</title>
<style type="text/css">
* {
    margin: 0;
    padding: 0;
}

table {
    width:<?=$breite?>
}
  
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

@media only screen and (max-width: 550px) {
  table {
    width:100%
  }
}

@media only screen and (min-width: 550px) {
  html {
    font-size:<?=$size?>
  }
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

if(isset($_GET["reset"]) == '1') {
	session_destroy();
	header("Location: ".$url."://".$_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME']);
	exit;
}

$spalten = array(
"origin"				=> "<b>Origin:</b><span class=\"mobile\"><br></span> ",
"r.name"				=> "<b>Route:</b><span class=\"mobile\"><br></span> ",
"t.routePos"			=> "<b>Pos:</b><span class=\"mobile\"><br></span> ",
"t.lastProtoDateTime"	=> "<b>Last:</b><span class=\"mobile\"><br></span> ",
"t.currentSleepTime"	=> "<b>Next:</b><span class=\"mobile\"><br></span> ");

$spalte = isset($_GET["spalte"]) ? $_GET["spalte"] : 't.lastProtoDateTime'; // Default-Wert
$sort = isset($_GET["sort"]) ? $_GET["sort"] : 'asc';

if (!array_key_exists($spalte ,$spalten)) {
	$spalte = 't.lastProtoDateTime'; // Default-Wert
}

if (!in_array($sort, array('desc', 'asc'))) {
	$sort = 'asc'; // Default-Wert
}

$sql = $mysqli->query("SELECT d.name AS origin, t.lastProtoDateTime, t.currentSleepTime, r.name, t.routePos, t.routeMax FROM settings_device d LEFT JOIN trs_status t ON d.name = t.origin LEFT JOIN settings_area r ON r.area_id = t.routemanager ORDER BY " . $spalte . " " . $sort .", origin ".$sort);

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
$i = 1;
while($row = $sql->fetch_array()) {
	$origin = $row["origin"];
	$next_seconds = $row["currentSleepTime"];
	if($row["lastProtoDateTime"] == NULL ) {
		echo "<tr style=\"background:#FF6666\"><td>".$origin."</td><td>N/A</td><td>N/A</td><td>N/A</td><td>N/A</td>";
	} else {
	
		$next_months = floor($next_seconds / (3600*24*30));
        $next_day = floor($next_seconds / (3600*24));
        $next_hours = floor($next_seconds / 3600);
        $next_mins = floor(($next_seconds - ($next_hours*3600)) / 60);
        $next_secs = floor($next_seconds % 60);
		
		if($next_seconds == 0) {
			$next = "now";
		} else if($next_seconds < 60) {
            $next = $next_secs." sec";
        } else if($next_seconds < 60*60 ) {
            $next = $next_mins." min";
        } else if($next_seconds < 24*60*60) {
            $next = $next_hours." hours";
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
            $time = $secs." sec ago";
        } else if($seconds < 60*60 ) {
			if($seconds > $wartime) {
            	$time = "<span class=\"warn\">".$mins." min ago</span>";
			} else {
				$time = $mins." min ago";
			}
        } else if($seconds < 24*60*60) {
			if($seconds > $next_seconds) {
            	$time = "<span class=\"warn\">".$hours." hours ago</span>";
			} else {
				$time = $hours." hours ago";
			}
        } else if($seconds < 4*24*60*60) {
            $time = $day." day ago";
        } else {
            $time = $months." month ago";
		}
		
		$cooldown = $offline * 60 + $next_seconds;
		
		if($seconds < $next_seconds && $row["lastProtoDateTime"] > date("Y-m-d H:i:s", strtotime("- $cooldown seconds")) ) {
			$status = 'online';
			$background = '#66CCFF';
		} elseif($row["lastProtoDateTime"] < date("Y-m-d H:i:s", strtotime("- $offline minutes"))) {
			$status = 'offline';
			$background = '#FFFF99';
				if(!isset($_SESSION[$origin])) {
					if($i == 1) {
						echo "<audio autoplay height=\"0\" width=\"0\"><source src=\"".$beep."?i=".time()."\" type=\"audio/mpeg\"></audio>";
						$_SESSION[$origin] = array("origin" => $row["origin"], "time" => time());
						$i++;
					}
				} elseif($_SESSION[$origin]["time"] < time()-$notify) {
					unset($_SESSION[$origin]);
				}
		} else {
			$status = 'online';
			$background = '#66CC66';
		}
		
		echo "<tr style=\"background:".$background."\"><td>".$origin."</td><td>".$row["name"]."</td><td>".$row["routePos"]."/".$row["routeMax"]."</td><td>$time</td><td>$next</td>";
	}
}
echo '</table>';
//echo '<pre>';
//print_r($_SESSION);
//echo '</pre>';
//session_destroy();
?>
<script type="text/javascript">
var i = <?=$reload?>;
(function timer(){
    if (--i < 0) return;
    setTimeout(function(){
        document.getElementsByTagName('h4')[0].innerHTML = 'reload in ' + i;
        timer();
    }, 1000);
})();
</script>
<h4 style="margin-top:20px; text-align:center">reload in <?=$reload?></h4>
<div style="margin-top:20px; text-align:center"><a href="mad_devices.php?reset=1">reset notify &amp; sorting</a></div>
</body>
</html>
