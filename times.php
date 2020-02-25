<?php
error_reporting(E_ALL); // Error engine - always E_ALL!
ini_set('ignore_repeated_errors', TRUE); // always TRUE
ini_set('display_errors', FALSE); // Error display - FALSE only in production environment or real server. TRUE in development environment
ini_set('log_errors', TRUE); // Error logging engine
ini_set('error_log', dirname(__FILE__).'/errors.log'); // Logging file path
ini_set('log_errors_max_len', 1024); // Logging file size

$device = explode(".", $_GET["file"]);

$csv = array_map('str_getcsv', file('mon_mitm/'.$_GET["file"]));
array_walk($csv, function(&$a) use ($csv) {
    $a = array_combine(array('start', 'area', 'end'), $a);
});


$anzahl = count ( $csv );

if($anzahl == 0) {
    echo '<h3>für "'.$device[0].'" sind noch keine Einträge vorhanden!</h3>';
    die();
}

$last = end($csv);
$preserved = array_reverse($csv, true);
//var_dump($preserved);
$hight = 100;
$balken = 0;
$summe = 0;
for ($x = $anzahl-1; $x >= 0; $x--) {
    if($preserved[$x]["end"] != 'unknown') {
        $date_now = new DateTime($preserved[$x]["end"]);
        $date_row = new DateTime($preserved[$x]["start"]);
        $seconds = $date_now->getTimestamp() - $date_row->getTimestamp();
        
        $mins = number_format($seconds / 60, 2);
                
        if ( $mins < $hight ) {
            $hight = $mins;
        }
        
        $summe+= $mins;
        
    }
}
$abc = $last["end"] != 'unknown' ? $anzahl : $anzahl-1;
$durch = $summe / $abc;
$output = '<div style="text-align:center;margin:5px">Durchschnittliche Rundenzeit: <b>'.number_format($durch,2).' Minuten</b></div>';
$balken2 = $hight * 100 / $durch;
//echo 'b2: '.$balken2.'<br><br>';

for ($x = $anzahl-1; $x >= 0; $x--) {

    if($preserved[$x]["end"] != 'unknown') {
        $date_now = new DateTime($preserved[$x]["end"]);
        $date_row = new DateTime($preserved[$x]["start"]);
        $seconds = $date_now->getTimestamp() - $date_row->getTimestamp();
        
        $mins = number_format($seconds / 60, 2);
    
        $balken = $hight * 100 / $mins;
    
        //echo '<b>'.$balken.'</b><br>';
        if($mins == $hight) {
            $best_time = '#66CCFF';
        } else {
            $best_time = '#66CC66';
        }
        
        $output .= '

        <div style="width:100%;border-bottom:solid 0.5px #CCCCCC;border-top:solid 0.5px #CCCCCC">
            <span style="position:absolute;left:50%;transform:translate(-50%);font-weight:bolder">'.$mins.' min</span>
            <div style="width:2px;position:absolute;left:'.$balken2.'%; height:21px;background:#FF0000"></div>
    
            <span style="position:absolute">'.$preserved[$x]["area"].', ['.date("H:i", strtotime($preserved[$x]["start"])).']</span>
            <div style="width:'.$balken.'%;display:block;height:20px;background:'.$best_time.'"></div>
        </div>';
    
    }
}
?>
<!doctype html>
<html lang="de">
    <head>
        <meta charset="utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <title><?=$device[0]?> - Roundtimes</title>
        <style>
            * {
                margin: 0;
                padding: 0;
            }
            
            html {
                font-family:Arial, Helvetica, sans-serif
            }
            
            i, material-icons {
                vertical-align:middle
            }
        </style>
    </head>
    <body>
        <div style="width:100%">
            <h3 style='text-align:center'><?=$device[0]?><a href="javascript:location.reload()"><i class="material-icons">refresh</i></a></h3>
            <?=$output?>
        </div>
    </body>
</html>