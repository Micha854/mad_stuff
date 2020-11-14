<?php
session_start();

error_reporting(E_ALL); // Error engine - always E_ALL!
ini_set('ignore_repeated_errors', TRUE); // always TRUE
ini_set('display_errors', FALSE); // Error display - FALSE only in production environment or real server. TRUE in development environment
ini_set('log_errors', TRUE); // Error logging engine
ini_set('error_log', dirname(__FILE__).'/errors.log'); // Logging file path
ini_set('log_errors_max_len', 1024); // Logging file size

$config = json_decode(file_get_contents('config.json'), true);
$theme = $config["option"]["theme"];
$instance = $config["option"]["instance_id"];
include("colors.php");

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

$structure = dirname(__FILE__)."/mon_mitm";
if($config["option"]["record"] == 1) {
    if (!file_exists($structure)) {
        if (!mkdir($structure, 0777, true)) {
            die('Creating the directory "mon_mitm" failed ...');
        }
    }
}

// SET DEFAULT CHARSETS TO UTF-8
mysqli_query($mysqli, "SET NAMES 'utf8'");
header("Content-Type: text/html; charset=utf-8");

if (isset($_GET["reset"]) == '1') {
    // delete logfiles
    if (is_dir($structure)) {
        if ($dh = opendir($structure)) {
            while (($file = readdir($dh)) !== false) {
                if ($file!="." AND $file !="..") {
                    unlink("$structure/$file");
                }
            }
            closedir($dh);
        }
    }
    // reset settings
    $sql = $mysqli->query("SELECT d.name AS origin FROM trs_status t LEFT JOIN settings_device d ON t.device_id = d.device_id WHERE d.instance_id = ".$instance);
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
            setcookie($_GET['origin'] . '[origin]', $_GET['origin'], time(), "/");
            setcookie($_GET['origin'] . '[time]', time(), time(), "/");
            setcookie($_GET['origin'] . '[mute]', "", time(), "/");
        }
    } elseif ($_GET["mute"] == 'on') {
        if (isset($_COOKIE[$_GET['origin']])) {
            setcookie($_GET['origin'] . '[origin]', $_GET['origin'], time() + 31536000, "/");
            setcookie($_GET['origin'] . '[time]', time() + 31536000, time() + 31536000, "/");
            setcookie($_GET['origin'] . '[mute]', 'mute', time() + 31536000, "/");
        } else {
            setcookie($_GET['origin'] . '[origin]', $_GET['origin'], time() + 31536000, "/");
            setcookie($_GET['origin'] . '[time]', time() + 31536000, time() + 31536000, "/");
            setcookie($_GET['origin'] . '[mute]', 'mute', time() + 31536000, "/");
        }
    } elseif ($_GET["mute"] == 'all') {
        setcookie('mute', 'all', time() + 31536000, "/");
    } elseif ($_GET["mute"] == 'reset') {
        setcookie('mute', "", time(), "/");
    }
    header("Location: " . $config["option"]["url"] . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . $sortIndex);
    exit;
}

$spalten = array(
    "origin" => "<b>Origin:</b><span class=\"mobile\"><br></span> ",
    "r.name" => "<b>Route:</b><span class=\"mobile\"><br></span> ",
    "t.routeMax" => "<b>Points:</b><span class=\"mobile\"><br></span> ",
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
FROM trs_quest q LEFT JOIN pokestop p ON q.GUID = p.pokestop_id WHERE q.GUID = p.pokestop_id ")->fetch_array();

$sql = $mysqli->query("SELECT d.name AS origin, t.lastProtoDateTime, t.currentSleepTime, r.name, r.mode, t.routePos, t.routeMax, t.restartCounter FROM settings_device d LEFT JOIN trs_status t ON d.device_id = t.device_id LEFT JOIN settings_area r ON r.area_id = t.area_id WHERE d.instance_id = $instance ORDER BY " . $spalte . " " . $sort . ", r.name, origin " . $sort);

$ausgabe = '<table id="tbl"><tr><td class="count"><b>Count:</b></td>';
$ausgabe2 = '<table id="tbl2"><tr><td class="count"><b>Count:</b></td>';
$ausgabe_mobile = '<table id="tblmobile"><tr><td class="count"><b>Count:</b></td>';
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

    if ($spalte == 't.routeMax') {
        $ausgabe .= '<td class="pos">' .
                ucfirst($name) .
                '<a href="?spalte=' . $spalte . '&sort=asc" ' . $active . ' title="Aufsteigend sortieren">&#9650;</a>' .
                '<a href="?spalte=' . $spalte . '&sort=desc" ' . $active2 . ' title="Absteigend sortieren">&#9660;</a>' .
                '</td>';
        $ausgabe2 .= '<td class="pos">' .
                ucfirst($name) .
                '<a href="?spalte=' . $spalte . '&sort=asc" ' . $active . ' title="Aufsteigend sortieren">&#9650;</a>' .
                '<a href="?spalte=' . $spalte . '&sort=desc" ' . $active2 . ' title="Absteigend sortieren">&#9660;</a>' .
                '</td>';
        $ausgabe_mobile .= '<td class="pos">' .
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
        $ausgabe2 .= '<td>' .
                ucfirst($name) .
                '<a href="?spalte=' . $spalte . '&sort=asc" ' . $active . ' title="Aufsteigend sortieren">&#9650;</a>' .
                '<a href="?spalte=' . $spalte . '&sort=desc" ' . $active2 . ' title="Absteigend sortieren">&#9660;</a>' .
                '</td>';
        $ausgabe_mobile .= '<td>' .
                ucfirst($name) .
                '<a href="?spalte=' . $spalte . '&sort=asc" ' . $active . ' title="Aufsteigend sortieren">&#9650;</a>' .
                '<a href="?spalte=' . $spalte . '&sort=desc" ' . $active2 . ' title="Absteigend sortieren">&#9660;</a>' .
                '</td>';
    }
}
$ausgabe .= '</tr>';
$ausgabe2 .= '</tr>';
$ausgabe_mobile .= '</tr>';
$audio = '';
$nextOut = 0;
$o = 0;
$i = 1;
while ($row = $sql->fetch_array()) {
    $origin = $row["origin"];
    $next_seconds = $row["currentSleepTime"];
    $clock = $config["option"]["record"] == 1 ? '<a href="javascript:MitteFenster(\'times.php?file='.$origin.'.txt\', 500, 600);"><i class="material-icons" id="clock">query_builder</i></a>' : '';
    if ($row["lastProtoDateTime"] == NULL) {
        $ausgabe .= "<tr style=\"background:$colorRed\"><td class='count'></td><td>" . $origin . "</td><td>N/A</td><td class='pos'>N/A</td><td>N/A</td><td>N/A</td>";
        $ausgabe2 .= "<tr style=\"background:$colorRed\"><td class='count'></td><td>" . $origin . "</td><td>N/A</td><td class='pos'>N/A</td><td>N/A</td><td>N/A</td>";
        $ausgabe_mobile .= "<tr style=\"background:$colorRed\"><td class='count'></td><td>" . $origin . "</td><td>N/A</td><td class='pos'>N/A</td><td>N/A</td><td>N/A</td>";
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
            $time = "$secs sec <span class='ago'>ago</span>";
        } else if ($seconds < 60 * 60) {
            if ($seconds > $config["option"]["wartime"] + $next_seconds) {
                $time = "<span class=\"warn\">$mins min <span class='ago'>ago</span></span>";
            } else {
                $time = "$mins min <span class='ago'>ago</span>";
            }
        } else if ($seconds < 24 * 60 * 60) {
            $out_hours = ($hours > 1 ? 'hours' : 'hour');
            if ($seconds > $config["option"]["wartime"] + $next_seconds) {
                $time = "<span class=\"warn\">$hours $out_hours <span class='ago'>ago</span></span>";
            } else {
                $time = "$hours $out_hours <span class='ago'>ago</span>";
            }
        } else if ($seconds > 24 * 60 * 60) {
            $out_day = ($day > 1 ? 'days' : 'day');
            $time = "$day $out_day <span class='ago'>ago</span>";
        } else if ($seconds > 30 * 24 * 60 * 60) {
            $out_months = ($months > 1 ? 'months' : 'month');
            $time = "$months $out_months <span class='ago'>ago</span>";
        }

        $cooldown = $config["option"]["timeout"] * 60 + $next_seconds;

        if ($seconds < $next_seconds && $row["lastProtoDateTime"] > date("Y-m-d H:i:s", strtotime("- $cooldown seconds"))) {
            $status = 'online';
            $background = $colorBlue;
        } elseif ($row["lastProtoDateTime"] < date("Y-m-d H:i:s", strtotime("- $cooldown seconds"))) {
            $status = 'offline';
            $background = $colorYellow;
            if (!isset($_COOKIE[$origin])) {  // none cookie
                if (!isset($_COOKIE[$origin]['mute']) && !isset($_COOKIE['mute'])) {
                    $audio .= "<audio autoplay><source src=\"" . $config["option"]["beep"] . "?i=" . time() . "\" type=\"audio/mpeg\"></audio>";
                    setcookie($origin . '[origin]', $origin, time() + $config["option"]["notify"], "/");
                    setcookie($origin . '[time]', time() + $config["option"]["notify"], time() + $config["option"]["notify"], "/");
                }
            } elseif (isset($_COOKIE[$origin]["time"]) && $_COOKIE[$origin]["time"] < time() - $config["option"]["notify"] && !isset($_COOKIE['mute'])) {
                setcookie($origin, "", time() - 3600, "/");
            } $o++;
        } else {
            $status = 'online';
            $background = $colorGreen;
            if (isset($_COOKIE[$origin])) {
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
                    $mute = '<a href="' . $sortIndex . '&mute=on&origin=' . $origin . '">' . $origin . '</a>'.$clock;
                } else {
                    $mute = '<a href="?mute=on&origin=' . $origin . '">' . $origin . '</a>'.$clock;
                }
            } elseif (isset($_COOKIE[$origin]['mute']) or isset($_GET["mute"]) && ($_GET["mute"] == 'on') && isset($_GET["origin"]) && ($_GET["origin"] == $origin)) {
                if (isset($_SESSION["sort"])) {
                    $mute = '<a href="' . $sortIndex . '&mute=off&origin=' . $origin . '">' . $origin . $clock . ' &#128263;</a>';
                } else {
                    $mute = '<a href="?mute=off&origin=' . $origin . '">' . $origin . $clock . ' &#128263;</a>';
                }
            } else {
                if (isset($_SESSION["sort"])) {
                    $mute = '<a href="' . $sortIndex . '&mute=on&origin=' . $origin . '">' . $origin . '</a>'.$clock;
                } else {
                    $mute = '<a href="?mute=on&origin=' . $origin . '">' . $origin . '</a>'.$clock;
                }
            }
        } elseif (isset($_GET['mute']) && ($_GET['mute'] == 'reset')) {
            $mute = $origin . $clock;
        } elseif (isset($_COOKIE['mute']) or isset($_GET['mute']) && $_GET['mute'] == 'all') {
            $mute = $origin . $clock . ' &#128263';
        } else {
            $mute = $origin . $clock;
        }

        if ($status == 'offline' && isset($_COOKIE[$origin]['time'])) {
            $alert = ($_COOKIE[$origin]['time'] - time() == 0 ? 'next alert' : ($_COOKIE[$origin]['time'] - time()));
            $timer = "<td class=\"count\" style=\"font-size:12px\" id=\"javascript-timer-" . $i . "\">" . $alert . "</td>";
        } else {
            $timer = '<td class="count"></td>';
        }

        if ($row["routeMax"] >= 100 && $config["option"]["route"] && $row["mode"] != 'pokestops') {
            $maxRoute = '<span class="warn2">' . $row["routeMax"] . '</span>';
        } elseif ($row["routeMax"] >= 65 && $config["option"]["route"] && $row["mode"] != 'pokestops') {
            $maxRoute = '<span class="warn">' . $row["routeMax"] . '</span>';
        } else {
            $maxRoute = $row["routeMax"];
        }

        if($row["restartCounter"] and $config["option"]["restartCount"] === true) {
            $restartCounter = '<span class="warn">('.$row["restartCounter"].')</span>';
        } else {
            $restartCounter = '';
        }
        
        //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        if($config["option"]["record"] == 1) {
            $logfile = $structure.'/'.$origin.'.txt';
            
            if(is_file($logfile)) {
                $lines = file ($logfile);
                //$letzte_zeile = $lines[count($lines)-1];
                $letzte_zeile = array_slice($lines, -1);
                $csv = array_map('str_getcsv', file($logfile));
                array_walk($csv, function(&$a) use ($csv) {
                    $a = array_combine(array('start', 'area', 'end'), $a);
                });
            
                $last = end($csv);
            } else {
                $datei = fopen($logfile,"a");
            }
            
            if($status == 'offline' && $last["end"] == 'unknown') {    // gerät offline, route zurücksetzen falls begonnen
                $lines = file ($logfile);
                array_pop($lines);
                $text = join('', $lines);
                $fp = fopen($logfile, "w"); 
                foreach($lines as $key => $text) {
                    fputs($fp, $text);
                } 
                fclose ($fp);
            } elseif($row["routePos"] == 1 && $row["mode"] == 'mon_mitm' && ($last["start"] && $last["end"] != 'unknown' or empty($letzte_zeile))) {    // start route
                $datei = fopen($logfile,"a");
                fwrite($datei, date("Y-m-d H:i:s") . ',' . $row["name"] . ',' . 'unknown',100);
                fclose($datei);
            } elseif($row["routePos"] == $row["routeMax"] && $last["start"] && $last["end"] == 'unknown') {    // end route
                $lines = file ($logfile);
                if($last["area"] != $row["name"]) {    // die area ist eine andere wie zu beginn
                    array_pop($lines);
                    $text = join('', $lines);
                } else {    // alles ok, route abschließen
                    $lines[count($lines)-1] = $last["start"] . ',' . $last["area"] . ',' . date("Y-m-d H:i:s") . "\n";
                }
                $fp = fopen($logfile, "w"); 
                foreach($lines as $key => $text) {
                    fputs($fp, $text);
                } 
                fclose ($fp);
            }
        }
        //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        $nextOut++;
        $ausgabe_mobile .= "<tr style=\"background:" . $background . "\">$timer<td>" . $mute . "</td><td>" . $row["name"].$restartCounter . "</td><td class='pos'>" . $row["routePos"] . "/" . $maxRoute . "</td><td>$time</td><td>$next</td>";
        
        if($nextOut > $config["option"]["rows"]) {
            $ausgabe2 .= "<tr style=\"background:" . $background . "\">$timer<td>" . $mute . "</td><td>" . $row["name"].$restartCounter . "</td><td class='pos'>" . $row["routePos"] . "/" . $maxRoute . "</td><td>$time</td><td>$next</td>";
        } else {
            $ausgabe .= "<tr style=\"background:" . $background . "\">$timer<td>" . $mute . "</td><td>" . $row["name"].$restartCounter . "</td><td class='pos'>" . $row["routePos"] . "/" . $maxRoute . "</td><td>$time</td><td>$next</td>";
        }
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

$o_title = $o > 0 ? $o : "OK";

$full_quest = $trs_quest['today'] * 100 / $trs_quest['total'];

$quest_position = str_replace(array(["%","px"]), "", $config["option"]["breite"]);
$quest_position = $quest_position / 2;



$quest_stat = '
<div class="quest_bar" style="clear:both;border-bottom:solid 0.5px '.$colorNoQuest.';border-top:solid 0.1px '.$colorNoQuest.'">
    <span class="quest_span" style="font-size:14.5px;font-style:italic">Quest: '.number_format($full_quest,2).'% ('.$trs_quest['today'] .'/'.$trs_quest['total'].')</span>
    <div style="width:'.$full_quest.'%;display:block;min-height:21px;background:'.$colorQuest.'"></div>
</div>';

//übernehme die url parameter in den ajax request
$ajaxData = $_REQUEST;
$ajaxData['action'] = 'ajax_refresh';
$ajaxData = json_encode($ajaxData);

//wenn wir ajax machen, dann halte hier an
if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'ajax_refresh') {
    if (!isset($_GET['mute'])) {
        echo $audio;
    }
    
    echo $ausgabe_mobile  . '</table>' . $ausgabe . '</table>';
    
    if($nextOut > $config["option"]["rows"]) {
        echo $ausgabe2 . '</table>';
    }
    echo "<script>var page_title = 'MAD - Worker Status (' + '". $o_title ."' + ')';</script>";
    //if($trs_quest['today'] != $trs_quest['total']) {
        echo $quest_stat;
    //}
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
        <meta name="viewport" content="minimal-ui, width=device-width, initial-scale=1.0, maximum-scale=1.0">
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <title>MAD - Worker Status (<?= $o_title ?>)</title>
        <style>
            * {
                margin: 0;
                padding: 0;
            }

            html {
                background:<?=$colorBackground?>;
                font-size:14.5px
            }
            
            #clock {
                font-size:14.5px
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
                color: <?=$colorLink?>;
                text-decoration: None;
            }

            a:hover, a:active {
                color: <?=$colorLinkHover?>;
            }

            #active {
                color: <?=$colorLinkActive?>;
            }

            .warn {
                background:<?=$colorYellow?>
            }

            .warn2 {
                background:<?=$colorRed?>
            }

            @media only screen and (max-width: 550px) {
                table {
                    width:100%
                }
                #tbl {
                    display:none
                }
                #tbl2 {
                    display:none
                }
                .ago {
                    display:none
                }
                .quest_bar {
                    width:100%
                }
                .quest_span {
                    position:absolute;
                    left:50%;
                    transform:translate(-50%)
                }
                <?php if ($config["option"]["pos"] == 0) { ?> .pos { display: none} <?php } ?>
                <?php if ($config["option"]["count"] == 0) { ?> .count { display: none} <?php } ?>
            }

            @media only screen and (min-width: 550px) {
                
                .mobile {
                    display:none
                }
                
                
                <?php if($nextOut > $config["option"]["rows"]) { ?>
                    #tbl {
                        float:left;
                        width:50%;
                        border-right:solid 2px #CCCCCC
                    }
                    #tbl2 {
                        float:left;
                        width:50%;
                    }
                    .output, #clock {
                        font-size:16px
                    }
                    .quest_bar {
                        width:100%
                    }
                    .quest_span {
                        position:absolute;
                        left:50%;
                        transform:translate(-50%)
                    }
                    
                <?php } else { ?>
                    table {
                        width:<?= $config["option"]["breite"] ?>
                    }
                    .output, #clock {
                        font-size:<?= $config["option"]["size"] ?>
                    }
                    .quest_bar {
                        width:<?= $config["option"]["breite"] ?>
                    }
                    .quest_span {
                        position:absolute;
                        <?php if($config["option"]["breite"] == '100%') {
                            echo 'left:50%;';
                            echo 'transform:translate(-50%)';
                        } ?>
                    }
                <?php } ?>
                
                @media only screen and (min-width: 751px) and (max-width: 900px) {
                    .output, #clock {
                        font-size:14px
                    }
                }
                @media only screen and (min-width: 550px) and (max-width: 750px) {
                    .output, #clock {
                        font-size:10px
                    }
                }
                
                #tblmobile {
                    display:none
                }
            }
            .navbar{
                min-height:17px
            }

            .navbar-brand {
                font-size:16.2px
            }

            .navbar  a {
                font-size: 14.2px;
            }
            
            .notify {
                margin-left:8px
            }

            .output {
                padding-bottom:50px;
                background:<?=$colorBackground?>;
                height:100%;
                color:<?=$colorFont?>
            }
            
            i, material-icons {
                vertical-align: middle;
            }
        </style>

    </head>
    <body>
        <div id="javascript-timer-init" style="display:none"><?= $config["option"]["notify"] ?></div>
        <div id="output" class="output">
            <?php
            if (!isset($_GET['mute'])) {
                echo $audio;
            }
            
            echo $ausgabe_mobile  . '</table>' . $ausgabe . '</table>';
            
            if($nextOut > $config["option"]["rows"]) {
                echo $ausgabe2 . '</table>';
            }

            //if($trs_quest['today'] != $trs_quest['total']) {
                echo $quest_stat;
            //}



//echo 'currently: '.time();
//echo '<pre>';
//print_r($_COOKIE);
//echo '</pre>';
            ?>
        </div>

        <nav class="navbar py-0 fixed-bottom navbar-expand-md navbar-light" style="background-color:<?=$colorNav?>">
            <span class="navbar-brand">Worker Status <span id="countdown" class="reload">(<?= $config["option"]["reload"] ?>)</span> <span class="notify"><?= $set_notify ?></span>
                <a href="#" id="goFS"><i class="material-icons">crop_free</i></a>
</span>
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
            function MitteFenster(Dateiname,PopUpBreite,PopUpHoehe) {
                sbreite = screen.availWidth;
                shoehe = screen.availHeight;
                x = (sbreite-PopUpBreite)/2;
                y = (shoehe-PopUpHoehe)/2;
                Eigenschaften="left="+x+",top="+y+",screenX="+x+",screenY="+y+",width="+PopUpBreite+",height="+PopUpHoehe+",menubar=no,location=no,toolbar=no,status=no,resizable=no,scrollbars=no,dependent=yes";
                fenster=window.open(Dateiname,"order",Eigenschaften);
                fenster.focus();
            }
            
            var goFS = document.getElementById("goFS");
            goFS.addEventListener("click", function() {
                document.body.requestFullscreen();
            }, false);
            
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
                        $("title").text(page_title);
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
                        $('#countdown').html('<i class="material-icons">refresh</i>');
                        return;
                    } else {
                        $('#countdown').text('(' + counter + ')');
                        
                        $('.count').each(function() {
                            var cc = parseInt($(this).text());
                            
                            if(cc>0){
                                cc--;
                                $(this).text(cc);
                            }
                        });

                        //console.log("Timer --> " + counter);
                    }
                }, 1000);
            }

            startTimer();

        </script>

    </body>
</html>