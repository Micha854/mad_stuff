<?php
session_start();

$config = json_decode(file_get_contents('config.json'), true);

if (isset($_GET["spalte"]) and isset($_GET["sort"])) {
    $_SESSION["sort"] = '?spalte=' . $_GET["spalte"] . '&sort=' . $_GET["sort"];
    $sortIndex = $_SESSION["sort"];
} elseif (isset($_SESSION["sort"])) {
    $sortIndex = $_SESSION["sort"];
} else {
    $sortIndex = '';
}

$mysqli = new mysqli($config["db"]["dbHost"], $config["db"]["dbUsername"], $config["db"]["dbPassword"], $config["database"]["mapadroid"]);
if ($mysqli->connect_error) {
    die('Error : (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

if (isset($_GET["reset"]) == '1') {
    $sql = $mysqli->query("SELECT d.name AS origin FROM trs_status t LEFT JOIN settings_device d ON t.device_id = d.device_id");
    while ($reset = $sql->fetch_array()) {
        setcookie($reset["origin"] . '[origin]', $reset["origin"], time() - 3600, "/");
        setcookie($reset["origin"] . '[time]', time(), time() - 3600, "/");
        if (isset($_COOKIE[$reset["origin"]]['mute'])) {
            setcookie($reset["origin"] . '[mute]', "", time() - 3600, "/");
        }
    }
    setcookie('mute', "", time());
    session_destroy();
    header("Location: " . $config["option"]["url"] . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME']);
    exit;
}

if (isset($_GET["mute"])) {
    if ($_GET["mute"] == 'off') {
        if (isset($_COOKIE[$_GET['origin']])) {
            //unset($_COOKIE[$_GET['origin'].'[mute]']);
            setcookie($_GET['origin'] . '[origin]', $_GET['origin'], time(), "/");
            setcookie($_GET['origin'] . '[time]', time(), time(), "/");
            setcookie($_GET['origin'] . '[mute]', "", time(), "/");
        }
    } elseif ($_GET["mute"] == 'on') {
        if (isset($_COOKIE[$_GET['origin']])) {
            //$_SESSION[$_GET['origin']]['mute'] = 'mute';
            //$_SESSION[$_GET['origin']]['time'] = 9999999999;
            setcookie($_GET['origin'] . '[origin]', $_GET['origin'], time() + 31536000, "/");
            setcookie($_GET['origin'] . '[time]', time() + 31536000, time() + 31536000, "/");
            setcookie($_GET['origin'] . '[mute]', 'mute', time() + 31536000, "/");
            //setcookie($_GET['origin'].'[time]', '' , time()+3600);
        } else {
            setcookie($_GET['origin'] . '[origin]', $_GET['origin'], time() + 31536000, "/");
            setcookie($_GET['origin'] . '[time]', time() + 31536000, time() + 31536000, "/");
            setcookie($_GET['origin'] . '[mute]', 'mute', time() + 31536000, "/");
        }
    } elseif ($_GET["mute"] == 'all') {
        //$_SESSION['mute'] = 'all';
        setcookie('mute', 'all', time() + 31536000, "/");
    } elseif ($_GET["mute"] == 'reset') {
        //unset($_COOKIE['mute']);
        setcookie('mute', "", time(), "/");
    }
	header("Location: " . $config["option"]["url"] . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . $sortIndex);
    exit;
}

$spalten = array(
    "origin" => "<b>Origin:</b><span class=\"mobile\"><br></span> ",
    "r.name" => "<b>Route:</b><span class=\"mobile\"><br></span> ",
    "t.routePos" => "<b>Pos:</b><span class=\"mobile\"><br></span> ",
    "t.lastProtoDateTime" => "<b>Last:</b><span class=\"mobile\"><br></span> ",
    "t.currentSleepTime" => "<b>Next:</b><span class=\"mobile\"><br></span> ");

$spalte = isset($_GET["spalte"]) ? $_GET["spalte"] : 't.lastProtoDateTime'; // Default-Wert
$sort = isset($_GET["sort"]) ? $_GET["sort"] : 'asc';

if (!array_key_exists($spalte, $spalten)) {
    $spalte = 't.lastProtoDateTime'; // Default-Wert
}

if (!in_array($sort, array('desc', 'asc'))) {
    $sort = 'asc'; // Default-Wert
}

// select all today's quest from all scanned
$trs_quest = $mysqli->query("SELECT count(`GUID`) AS total
,(SELECT count(`GUID`) FROM trs_quest WHERE FROM_UNIXTIME(quest_timestamp,'%Y-%m-%d') = CURDATE()) AS today
FROM trs_quest ")->fetch_array();

$sql = $mysqli->query("SELECT d.name AS origin, t.lastProtoDateTime, t.currentSleepTime, r.name, t.routePos, t.routeMax FROM settings_device d LEFT JOIN trs_status t ON d.device_id = t.device_id LEFT JOIN settings_area r ON r.area_id = t.area_id ORDER BY " . $spalte . " " . $sort . ", origin " . $sort);

$ausgabe = '<table><tr><td class="count" style="font-size:16px"><b>Count:</b></td>';
foreach ($spalten as $spalte => $name) {
    if (isset($_GET["spalte"]) and $_GET["spalte"] == $spalte) {
        if ($_GET["sort"] == 'asc') {
            $active = 'id="active"';
            $active2 = '';
        } elseif ($_GET["sort"] == 'desc') {
            $active = '';
            $active2 = 'id="active"';
        }
    } else {
        $active = '';
        $active2 = '';
    }

    if ($spalte == 't.routePos') {
        $ausgabe .= '<td class="pos">' .
                ucfirst($name) .
                '<a href="?spalte=' . $spalte . '&sort=asc" ' . $active . ' title="Aufsteigend sortieren">&#9650;</a>' .
                '<a href="?spalte=' . $spalte . '&sort=desc" ' . $active2 . ' title="Absteigend sortieren">&#9660;</a>' .
                '</td>';
    } else {
        $ausgabe .= '<td>' .
                ucfirst($name) .
                '<a href="?spalte=' . $spalte . '&sort=asc" ' . $active . ' title="Aufsteigend sortieren">&#9650;</a>' .
                '<a href="?spalte=' . $spalte . '&sort=desc" ' . $active2 . ' title="Absteigend sortieren">&#9660;</a>' .
                '</td>';
    }
}
$ausgabe .= '</tr>';
$audio = '';
$o = 0;
$i = 1;
while ($row = $sql->fetch_array()) {
    $origin = $row["origin"];
    $next_seconds = $row["currentSleepTime"];
    if ($row["lastProtoDateTime"] == NULL) {
        $ausgabe .= "<tr style=\"background:#FF6666\"><td class='count'></td><td>" . $origin . "</td><td>N/A</td><td class='pos'>N/A</td><td>N/A</td><td>N/A</td>";
    } else {

        $next_months = floor($next_seconds / (3600 * 24 * 30));
        $next_day = floor($next_seconds / (3600 * 24));
        $next_hours = floor($next_seconds / 3600);
        $next_mins = floor(($next_seconds - ($next_hours * 3600)) / 60);
        $next_secs = floor($next_seconds % 60);

        if ($next_seconds == 0) {
            $next = "now";
        } else if ($next_seconds < 60) {
            $next = $next_secs . " sec";
        } else if ($next_seconds < 60 * 60) {
            $next = $next_mins . " min";
        } else if ($next_seconds < 24 * 60 * 60) {
            $next = $next_hours . " hours";
        }

        $date_now = new DateTime();
        $date_row = new DateTime($row["lastProtoDateTime"]);
        $seconds = $date_now->getTimestamp() - $date_row->getTimestamp();

        $months = floor($seconds / (3600 * 24 * 30));
        $day = floor($seconds / (3600 * 24));
        $hours = floor($seconds / 3600);
        $mins = floor(($seconds - ($hours * 3600)) / 60);
        $secs = floor($seconds % 60);

        if ($seconds < 60) {
            $time = "$secs sec ago";
        } else if ($seconds < 60 * 60) {
            if ($seconds > $config["option"]["wartime"] + $next_seconds) {
                $time = "<span class=\"warn\">$mins min ago</span>";
            } else {
                $time = "$mins min ago";
            }
        } else if ($seconds < 24 * 60 * 60) {
            $out_hours = ($hours > 1 ? 'hours' : 'hour');
            if ($seconds > $config["option"]["wartime"] + $next_seconds) {
                $time = "<span class=\"warn\">$hours $out_hours ago</span>";
            } else {
                $time = "$hours $out_hours ago";
            }
        } else if ($seconds > 24 * 60 * 60) {
            $out_day = ($day > 1 ? 'days' : 'day');
            $time = "$day $out_day ago";
        } else if ($seconds > 30 * 24 * 60 * 60) {
            $out_months = ($months > 1 ? 'months' : 'month');
            $time = "$months $out_months ago";
        }

        $cooldown = $config["option"]["timeout"] * 60 + $next_seconds;

        if ($seconds < $next_seconds && $row["lastProtoDateTime"] > date("Y-m-d H:i:s", strtotime("- $cooldown seconds"))) {
            $status = 'online';
            $background = '#66CCFF';
        } elseif ($row["lastProtoDateTime"] < date("Y-m-d H:i:s", strtotime("- $cooldown seconds"))) {
            $status = 'offline';
            $background = '#FFFF99';
            if (!isset($_COOKIE[$origin])) {  // none cookie
                //if($i == 1) {
                if (!isset($_COOKIE[$origin]['mute']) && !isset($_COOKIE['mute'])) {
                    $audio .= "<audio autoplay><source src=\"" . $config["option"]["beep"] . "?i=" . time() . "\" type=\"audio/mpeg\"></audio>";
                    //$_SESSION[$origin] = array("origin" => $row["origin"], "time" => time());
                    //setcookie("TestCookie", $value, time()+3600);
                    setcookie($origin . '[origin]', $origin, time() + $config["option"]["notify"], "/");
                    setcookie($origin . '[time]', time() + $config["option"]["notify"], time() + $config["option"]["notify"], "/");
                    //$i++;
                }
                //}
            } elseif (isset($_COOKIE[$origin]["time"]) && $_COOKIE[$origin]["time"] < time() - $config["option"]["notify"] && !isset($_COOKIE['mute'])) {
                //unset($_SESSION[$origin]);
                setcookie($origin, "", time() - 3600, "/");
            } $o++;
        } else {
            $status = 'online';
            $background = '#66CC66';
            if (isset($_COOKIE[$origin])) {
                //unset($_SESSION[$origin]);
                //setcookie($origin,	"",			time()-3600);

                setcookie($origin . '[origin]', $origin, time() - 3600, "/");
                setcookie($origin . '[time]', time(), time() - 3600, "/");
                if (isset($_COOKIE[$origin]['mute'])) {
                    setcookie($origin . '[mute]', "", time() - 3600, "/");
                }
            }
        }

        if ($status == 'offline' && !isset($_COOKIE['mute'])) {
            if (isset($_COOKIE['mute']) or isset($_GET['mute']) && $_GET['mute'] == 'all') {
                $mute = $origin . ' &#128263';
            } elseif (isset($_GET["mute"]) && ($_GET["mute"] == 'off' && isset($_GET["origin"]) && $_GET["origin"] == $origin)) {
                if (isset($_SESSION["sort"])) {
                    $mute = '<a href="' . $sortIndex . '&mute=on&origin=' . $origin . '">' . $origin . '</a>';
                } else {
                    $mute = '<a href="?mute=on&origin=' . $origin . '">' . $origin . '</a>';
                }
            } elseif (isset($_COOKIE[$origin]['mute']) or isset($_GET["mute"]) && ($_GET["mute"] == 'on') && isset($_GET["origin"]) && ($_GET["origin"] == $origin)) {
                if (isset($_SESSION["sort"])) {
                    $mute = '<a href="' . $sortIndex . '&mute=off&origin=' . $origin . '">' . $origin . ' &#128263;</a>';
                } else {
                    $mute = '<a href="?mute=off&origin=' . $origin . '">' . $origin . ' &#128263;</a>';
                }
            } else {
                if (isset($_SESSION["sort"])) {
                    $mute = '<a href="' . $sortIndex . '&mute=on&origin=' . $origin . '">' . $origin . '</a>';
                } else {
                    $mute = '<a href="?mute=on&origin=' . $origin . '">' . $origin . '</a>';
                }
            }
        } elseif (isset($_GET['mute']) && ($_GET['mute'] == 'reset')) {
            $mute = $origin;
        } elseif (isset($_COOKIE['mute']) or isset($_GET['mute']) && $_GET['mute'] == 'all') {
            $mute = $origin . ' &#128263';
        } else {
            $mute = $origin;
        }

        if ($status == 'offline' && isset($_COOKIE[$origin]['time'])) {
			$alert = ($_COOKIE[$origin]['time'] - time() == 0 ? 'next alert' : ($_COOKIE[$origin]['time'] - time()));
            $timer = "<td class=\"count\" style=\"font-size:12px\" id=\"javascript-timer-" . $i . "\">" . $alert . "</td>";
        } else {
            $timer = '<td class="count"></td>';
        }

        if ($row["routeMax"] >= 100 && $config["option"]["route"]) {
            $maxRoute = '<span class="warn2">' . $row["routeMax"] . '</span>';
        } elseif ($row["routeMax"] >= 65 && $config["option"]["route"]) {
            $maxRoute = '<span class="warn">' . $row["routeMax"] . '</span>';
        } else {
            $maxRoute = $row["routeMax"];
        }

        $ausgabe .= "<tr style=\"background:" . $background . "\">$timer<td>" . $mute . "</td><td>" . $row["name"] . "</td><td class='pos'>" . $row["routePos"] . "/" . $maxRoute . "</td><td>$time</td><td>$next</td>";
    } $i++;
}
$mysqli->close();


if (isset($_GET['mute']) && $_GET['mute'] == 'reset') {
    if (isset($_SESSION["sort"])) {
        $set_notify = '<a class="navbar-brand" href="' . $sortIndex . '&mute=all">&#128264;</a>';
    } else {
        $set_notify = '<a class="navbar-brand" href="?mute=all">&#128264;</a>';
    }
} elseif (!isset($_COOKIE["mute"]) == 'all' and ! isset($_GET['mute'])) {
    if (isset($_SESSION["sort"])) {
        $set_notify = '<a class="navbar-brand" href="' . $sortIndex . '&mute=all">&#128264;</a>';
    } else {
        $set_notify = '<a class="navbar-brand" href="?mute=all">&#128264;</a>';
    }
} elseif (isset($_GET['mute']) && ($_GET['mute'] == 'on' or $_GET['mute'] == 'off')) {
    if (isset($_SESSION["sort"])) {
        $set_notify = '<a class="navbar-brand" href="' . $sortIndex . '&mute=all">&#128264;</a>';
    } else {
        $set_notify = '<a class="navbar-brand" href="?mute=all">&#128264;</a>';
    }
} else {
    if (isset($_SESSION["sort"])) {
        $set_notify = '<a class="navbar-brand" href="' . $sortIndex . '&mute=reset">&#128263;</a>';
    } else {
        $set_notify = '<a class="navbar-brand" href="?mute=reset">&#128263;</a>';
    }
}

$o_title = ($o > 0 ? " ($o)" : '');


$full_quest = $trs_quest['today'] * 100 / $trs_quest['total'];

$quest_stat = '
<div style="width:100%;border-bottom:solid 0.5px #dcdcdc;border-top:solid 0.1px #dcdcdc">
	<span style="position:absolute;left:50%;transform:translate(-50%);font-size:15px;font-style:italic">Quests: '.number_format($full_quest,2).'% ('.$trs_quest['today'] .'/'.$trs_quest['total'].')</span>
	<div style="width:'.$full_quest.'%;display:block;min-height:22px;background:#66CCFF"></div>
</div>';

//übernehme die url parameter in den ajax request
$ajaxData = $_REQUEST;
$ajaxData['action'] = 'ajax_refresh';
$ajaxData = json_encode($ajaxData);

//wenn wir ajax machen, dann halte hier an
if ($_REQUEST['action'] == 'ajax_refresh') {
	if (!isset($_GET['mute'])) {
		echo $audio;
	}
	echo $ausgabe . '</table>';
	//if($trs_quest['today'] != $trs_quest['total']) {
		echo $quest_stat;
	//}
	?>
	<title>MAD - Worker Status <?= $o_title ?></title>
	<?php
//echo 'currently: '.time();
//echo '<pre>';
//print_r($_COOKIE);
//echo '</pre>';
    die;
}
?>


<!doctype html>
<html lang="de">
    <head>
        <meta charset="utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!--<meta http-equiv="refresh" content="<?= $config["option"]["reload"] ?>; URL=<?= $config["option"]["url"] . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . $sortIndex ?>">-->
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
        <title>MAD - Worker Status <?= $o_title ?></title>
        <style>
            * {
                margin: 0;
                padding: 0;
            }

            html {
                background:#FAFAFA;
                font-size:15px
            }

            table {
                width:<?= $config["option"]["breite"] ?>
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

            .warn2 {
                background:#FF6666
            }

            @media only screen and (max-width: 550px) {
                table {
                    width:100%
                }
                <?php if ($config["option"]["pos"] == 0) { ?> .pos { display: none} <?php } ?>
                <?php if ($config["option"]["count"] == 0) { ?> .count { display: none} <?php } ?>
            }

            @media only screen and (min-width: 550px) {
                .output {
                    font-size:<?= $config["option"]["size"] ?>
                }
                .mobile {
                    display:none
                }
            }
            .navbar{
                min-height:17px;
            }

            .navbar-brand {
                font-size:16.2px
            }

            .navbar  a {
                font-size: 14.2px;
            }

            .output {
                padding-bottom:50px;
                background:#FAFAFA
            }
        </style>

    </head>
    <body>
        <div id="javascript-timer-init" style="display:none"><?= $config["option"]["notify"] ?></div>
        <div id="output" class="output">
            <?php
//if(isset($audio)) {
//	for($n=1; $n < $i; $n++) {
            if (!isset($_GET['mute'])) {
                echo $audio;
            }
//	}
//}
            echo $ausgabe . '</table>';

			//if($trs_quest['today'] != $trs_quest['total']) {
				echo $quest_stat;
			//}



//echo 'currently: '.time();
//echo '<pre>';
//print_r($_COOKIE);
//echo '</pre>';
            ?>
        </div>

        <nav class="navbar py-0 fixed-bottom navbar-expand-md navbar-light" style="background-color:#E6E6E6">
            <span class="navbar-brand">Worker Status <span id="countdown" class="reload">(<?= $config["option"]["reload"] ?>)</span> <span class="notify"><?= $set_notify ?></span></span>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item dropup">
                        <a class="nav-link dropdown-toggle active" href="index.php" id="dropdown10" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Worker Status</a>
                        <div class="dropdown-menu" aria-labelledby="dropdown10">
                            <a class="dropdown-item" href="index.php?reset=1">Reset Settings</a>
                            <a class="dropdown-item" href="index.php">Worker Status Page</a>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="mad_set.php">IV List Manager</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="mad_stats.php?date=<?= date("Y-m-d") ?>">Worker Statistik</a>
                    </li>
                </ul>
            </div>
        </nav>

        <script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>

        <script>
            $(document).ready(function () {
                // run the first time; all subsequent calls will take care of themselves
                setTimeout(worker, <?=$config["option"]["reload"]*1000;?>);

            });

            function worker() {

                $.ajax({
                    url: 'index.php<?= $sortIndex ?>',
                    data:<?= $ajaxData ?>,
                    success: function (data) {
                        $('#output').html(data);
                    },
                    error: function (data) {
                        $('#output').html('Ein Fehler ist aufgetreten. Bitte Seite manuell neu laden.');
                    },
                    complete: function () {
                        // Schedule the next request when the current one's complete
                        setTimeout(worker, <?=$config["option"]["reload"]*1000;?>);

                        //reset timer
                        startTimer();
                    }
                });
            }

            function startTimer() {
                var counter = <?=$config["option"]["reload"];?>;
                var interval = setInterval(function () {
                    counter--;
                    // Display 'counter' wherever you want to display it.
                    if (counter <= 0) {
                        clearInterval(interval);
                        $('#countdown').html("Aktualisieren...");
                        return;
                    } else {
                        $('#countdown').text('(' + counter + ')');
                        //console.log("Timer --> " + counter);
                    }
                }, 1000);
            }

            startTimer();

        </script>

    </body>
</html>