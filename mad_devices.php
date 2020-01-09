<?php
session_start();

require_once("config.php");

if(isset($_GET["spalte"]) and isset($_GET["sort"])) {
	$_SESSION["sort"] = '?spalte='.$_GET["spalte"].'&sort='.$_GET["sort"];
	$sortIndex = $_SESSION["sort"];
} elseif(isset($_SESSION["sort"])) {
	$sortIndex = $_SESSION["sort"];
} else {
	$sortIndex = '';
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
  <?php if($pos == 0) { ?> .pos { display: none} <?php } ?>
  <?php if($count == 0) { ?> .count { display: none} <?php } ?>
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
<div id="javascript-timer-init" style="display:none"><?=$notify?></div>
<?php
$mysqli = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);
if ($mysqli->connect_error) {
	die('Error : ('. $mysqli->connect_errno .') '. $mysqli->connect_error);
}

if(isset($_GET["reset"]) == '1') {
	session_destroy();
	header("Location: ".$url."://".$_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME']);
	exit;
}

if(isset($_GET["mute"])) {
	if($_GET["mute"] == 'off') {
		if(isset($_COOKIE[$_GET['origin']])) {
			//unset($_COOKIE[$_GET['origin'].'[mute]']);
			setcookie($_GET['origin'].'[origin]',	$_GET['origin'],		time());
			setcookie($_GET['origin'].'[time]',		time(),					time());
			setcookie($_GET['origin'].'[mute]',		"",						time());
		}
	} elseif($_GET["mute"] == 'on') {
		if(isset($_COOKIE[$_GET['origin']])) {
			//$_SESSION[$_GET['origin']]['mute'] = 'mute';
			//$_SESSION[$_GET['origin']]['time'] = 9999999999;
			setcookie($_GET['origin'].'[origin]',	$_GET['origin'],		time()+31536000);
			setcookie($_GET['origin'].'[time]', 	time()+31536000,		time()+31536000);
			setcookie($_GET['origin'].'[mute]',		'mute',					time()+31536000);
			//setcookie($_GET['origin'].'[time]', '' , time()+3600);
		}
	} elseif($_GET["mute"] == 'all') {
			//$_SESSION['mute'] = 'all';
			setcookie('mute',						'all',					time()+31536000);
	} elseif($_GET["mute"] == 'reset') {
			//unset($_COOKIE['mute']);
			setcookie('mute',						"",						time());
	}
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

echo '<table><tr><td class="count" style="font-size:16px"><b>Count:</b></td>';
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
	
	if($spalte == 't.routePos') {
		echo '<td class="pos">' .
		ucfirst($name) .
		'<a href="?spalte=' . $spalte . '&sort=asc" '.$active.' title="Aufsteigend sortieren">&#9650;</a>' .
		'<a href="?spalte=' . $spalte . '&sort=desc" '.$active2.' title="Absteigend sortieren">&#9660;</a>' .
		'</td>';
	} else {
		echo '<td>' .
		ucfirst($name) .
		'<a href="?spalte=' . $spalte . '&sort=asc" '.$active.' title="Aufsteigend sortieren">&#9650;</a>' .
		'<a href="?spalte=' . $spalte . '&sort=desc" '.$active2.' title="Absteigend sortieren">&#9660;</a>' .
		'</td>';
	}
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
            $time = "$secs sec ago";
        } else if($seconds < 60*60 ) {
			if($seconds > $wartime + $next_seconds) {
            	$time = "<span class=\"warn\">$mins min ago</span>";
			} else {
				$time = "$mins min ago";
			}
        } else if($seconds < 24*60*60) {
			$out_hours = ($hours > 1 ? 'hours' : 'hour');
			if($seconds > $wartime + $next_seconds) {
            	$time = "<span class=\"warn\">$hours $out_hours ago</span>";
			} else {
				$time = "$hours $out_hours ago";
			}
        } else if($seconds > 24*60*60) {
			$out_day = ($day > 1 ? 'days' : 'day');
            $time = "$day $out_day ago";
        } else if($seconds > 30*24*60*60) {
			$out_months = ($months > 1 ? 'months' : 'month');
            $time = "$months $out_months ago";
		}
		
		$cooldown = $offline * 60 + $next_seconds;
		
		if($seconds < $next_seconds && $row["lastProtoDateTime"] > date("Y-m-d H:i:s", strtotime("- $cooldown seconds")) ) {
			$status = 'online';
			$background = '#66CCFF';
		} elseif($row["lastProtoDateTime"] < date("Y-m-d H:i:s", strtotime("- $cooldown seconds"))) {
			$status = 'offline';
			$background = '#FFFF99';
				if(!isset($_COOKIE[$origin])) {		// none cookie
					//if($i == 1) {
						if(!isset($_COOKIE[$origin]['mute']) && !isset($_COOKIE['mute'])) {
							echo "<audio autoplay height=\"0\" width=\"0\"><source src=\"".$beep."?i=".time()."\" type=\"audio/mpeg\"></audio>";
							//$_SESSION[$origin] = array("origin" => $row["origin"], "time" => time());
							//setcookie("TestCookie", $value, time()+3600);
							setcookie($origin.'[origin]',	$origin,	time()+$notify);
							setcookie($origin.'[time]',		time()+$notify,		time()+$notify);
							//$i++;
						}
					//}
				} elseif($_COOKIE[$origin]["time"] < time()-$notify && !isset($_COOKIE['mute'])) {
					//unset($_SESSION[$origin]);
					setcookie($origin, "", time()-3600);
				}
		} else {
			$status = 'online';
			$background = '#66CC66';
			if(isset($_COOKIE[$origin])) {
				//unset($_SESSION[$origin]);
				//setcookie($origin,	"",			time()-3600);
				
				setcookie($origin.'[origin]',	$origin,	time()-3600);
				setcookie($origin.'[time]',		time(),		time()-3600);
				if(isset($_COOKIE[$origin]['mute'])) {
					setcookie($origin.'[mute]',	"",			time()-3600);
				}
			}
		}

		if($status == 'offline' && !isset($_COOKIE['mute'])) {
			if(isset($_COOKIE[$origin]['mute'])) {
				if(isset($_SESSION["sort"])) {
					$mute = '<a href="'.$sortIndex.'&mute=off&origin='.$origin.'">'.$origin.' &#128263;</a>';
				} else {
					$mute = '<a href="?mute=off&origin='.$origin.'">'.$origin.' &#128263;</a>';
				}
			} else {
				if(isset($_SESSION["sort"])) {
					$mute = '<a href="'.$sortIndex.'&mute=on&origin='.$origin.'">'.$origin.'</a>';
				} else {
					$mute = '<a href="?mute=on&origin='.$origin.'">'.$origin.'</a>';
				}
			}
		} elseif(isset($_COOKIE['mute'])) {
			$mute = $origin.' &#128263';
		} else {
			$mute = $origin;
		}
		
		if($status == 'offline' && isset($_COOKIE[$origin]['time'])) {
			$timer = "<td class=\"count\" style=\"font-size:12px\" id=\"javascript-timer-".$i."\">".($_COOKIE[$origin]['time'] - time() + $notify)."</td>";
		} else {
			$timer = '<td class="count"></td>';
		}
		echo "<tr style=\"background:".$background."\">$timer<td>".$mute."</td><td>".$row["name"]."</td><td class='pos'>".$row["routePos"]."/".$row["routeMax"]."</td><td>$time</td><td>$next</td>";
	} $i++;
}
echo '</table>';


echo 'currently: '.time();
echo '<pre>';
print_r($_COOKIE);
echo '</pre>';

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

        <script type="text/javascript">
            // singleton timer
            var Timer = new function()
            {
                // store all instances here
                this.instances = [];
            
                // init with id, current init time and destination time
                // init time needed to synchronize clock (server-side/client-side)
                this.init = function(id, initTime, destTime)
                {
                    this.instances[id] = {
                        "iv"   : setTimeout("Timer.countdown('" + id + "')", 5), // set interval did not work properly
                        "rest" : destTime - initTime}; // we are just counting down
                }
                
                this.countdown = function(id)
                {
                    if (this.instances[id].rest > 0) {
                        setTimeout("Timer.countdown('" + id + "')", 1000); // new timeout
                    }
                    this.display(id, this.instances[id].rest); // call display function
                    --this.instances[id].rest; // decrement
                }
                
                this.display = function(id, seconds)
                {
                    // display what ever you like
                    document.getElementById(id).innerHTML = seconds;
                }
            }
        
            function init()
            {
                // look after timer init (needed for time synchronisation)
                if (typeof document.getElementById("javascript-timer-init") != "undefined") {
                    var initTime = document.getElementById("javascript-timer-init").innerHTML;
                    var i = 1, e;
                    // increment "i" index, if not found, stop loop
                    while (document.getElementById("javascript-timer-" + i)) {
                        e = document.getElementById("javascript-timer-" + i);
                        var destTime = e.innerHTML;
                        // init timer with id, init time and destination time
                        Timer.init("javascript-timer-" + i, initTime, destTime);
                        ++i;
                    }
                }
            }
            
            // TODO use a better onload event
            window.onload = init;
        </script>

<h4 style="margin-top:20px; text-align:center">reload in <?=$reload?></h4>
<div style="margin-top:20px; text-align:center">
<?php
if(!isset($_COOKIE["mute"]) == 'all') {
	if(isset($_SESSION["sort"])) {
		echo '<a href="'.$sortIndex.'&mute=all">&#128264;</a> | ';
	} else {
		echo '<a href="?mute=all">&#128264;</a> | ';
	}
} else {
	if(isset($_SESSION["sort"])) {
		echo '<a href="'.$sortIndex.'&mute=reset">&#128263;</a> | ';
	} else {
		echo '<a href="?mute=reset">&#128263;</a> | ';
	}
}
?>
	<a href="mad_devices.php?reset=1">reset notify &amp; sorting</a>
</div>
</body>
</html>
