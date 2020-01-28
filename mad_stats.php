<!DOCTYPE HTML>
<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
		<title>MAD - Worker Statistik</title>
        <style type="text/css">
            * {
                margin: 0;
                padding: 0;
            }
            .output {
                float:left;
                height:500px;
                margin-left:0px;
                margin-right:0px;
                padding-top:20px;
                margin-bottom:50px;
                border-right:#d0d0d0 0px solid;
                border-left:#d0d0d0 1px solid;
                border-top:#d0d0d0 0px solid;
                border-collapse: collapse
            }
			
			@media only screen and (min-width: 525px) {
				.output {
					width:33%;
					min-width:525px;
				}
			}
			@media only screen and (max-width: 525px) {
				.output {
					width:99%;
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

*,
*::before,
*::after {
  box-sizing: unset;
}

        </style>


        <?php
        require_once("config.php");

        error_reporting(0);

        $mysqli = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);
        if ($mysqli->connect_error) {
            die('Error : (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }

        $date = $_GET["date"];
        $dateOut = date("d.m.y", strtotime($date));
        $num = 0;
        $chart = 1;

        $all_devices = $mysqli->query("SELECT d.name AS origin FROM trs_status t LEFT JOIN settings_device d ON t.device_id = d.device_id WHERE NOT d.name = 'test'");
        while ($device = $all_devices->fetch_array()) {

            $origin = $device["origin"];

//$origin = 'S7Micha2'; //debug

            $sql = "
	SELECT a.time as Beginn, origin, createdate, a.status
        FROM (
          SELECT time, origin, createdate, 
                 status,
                 @status IS NULL OR @status != status AS changeonoff,
                 @status := status 
            FROM device_stats.status
            JOIN (SELECT @status := NULL) init
        WHERE origin = '$origin' AND createdate = '$date'
              ) a
       WHERE a.changeonoff = 1 AND origin = '$origin' AND createdate = '$date' ";


            $query = $mysqli->query($sql);

            $noneSum = 0;
            $onSum = 0;
            $offSum = 0;
            $tag = 86400; // seconds
            $data = array();
            $myArray = mysqli_fetch_all($query);

			$myArray[0][0] = $myArray[0][2] . ' 00:00:00'; // starte den ersten datensatz bei 0 h
			
//filter out incorrect dates
            foreach ($myArray as $k => $v) {
                //if entry date not equal to url date, delete entry
                if (stripos($v[0], $date) === false) {
                    unset($myArray[$k]);
                }
				if($myArray[$k][0] == $myArray[$k+1][0]) {	// lösche gleiche datetime einträge !!
					unset($myArray[$k]);
					unset($myArray[$k+1]);
				}
            }
            $myArray = array_values($myArray);
//end filter

			
            
			$myStatusDefs = ["Offline", "Online", "None"];
            if (date("Y-m-d", time()) == substr($myArray[0][0], 0, 10))
                array_push($myArray, [date("Y-m-d H:i:s", time()), $myArray[0][1], $myArray[0][2], 2]);

            $c = count($myArray);

            

            for ($i = 0; $i < $c; $i++) {
                $value = end($myArray);
                $key = key($myArray);

                $add = array();

                $myEnd = isset($myArray[$i + 1]) ? $myArray[$i + 1][0] : substr($myArray[0][0], 0, 10) . ' 23:59:59';

                $seconds = strtotime($myEnd) - strtotime($myArray[$i][0]);
                $anteil = number_format($seconds * 100 / $tag, 2);
				
				if($anteil == '0.00') {
					$anteil = '0.01';
				}

                $add['y'] = $anteil;

                if ($myStatusDefs[$myArray[$i][3]] == 'Online') {
                    $add['label'] = date("H:i:s", strtotime($myArray[$i][0])) . ' - ' . date("H:i:s", strtotime($myEnd));
                    $onSum += $seconds;
                } elseif ($myStatusDefs[$myArray[$i][3]] == 'Offline') {
                    $add['label'] = date("H:i:s", strtotime($myArray[$i][0])) . ' - ' . date("H:i:s", strtotime($myEnd));
                    $add['color'] = '#FF6666';
                    $offSum += $seconds;
                } else {
                    $add['label'] = date("H:i:s", strtotime($myArray[$i][0])) . ' - ' . date("H:i:s", strtotime($myEnd));
                    $add['color'] = '#d0d0d0';
                    $noneSum += $seconds;
                }

                $data[] = $add;
            }

            $anteil_none[] = number_format($noneSum * 100 / $tag, 2);
            $anteil_online[] = number_format($onSum * 100 / $tag, 2);
            $anteil_offline[] = number_format($offSum * 100 / $tag, 2);

            $data = json_encode($data);

            if ($num == 0) {
                ?>
                <script>
                    window.onload = function () {
        <?php
    }
	//print_r($myArray);
    $num++;
    ?>



                    var chart<?= $chart ?> = new CanvasJS.Chart("chartContainer<?= $chart ?>", {
                        exportEnabled: true,
                        animationEnabled: true,
                        title: {
                            text: "<?= $origin ?>",
							fontFamily: "tahoma",
							fontSize: 26,
							fontWeight: "bold"
                        },
						subtitles:[{
							text: "<?=$dateOut?>",
							fontFamily: "tahoma",
							fontSize: 12,
							fontWeight: "Normal"
						}],
                        legend: {
                            cursor: "pointer",
                            itemclick: explodePie<?= $chart ?>
                        },
                        data: [{
                                type: "pie",
                                showInLegend: false,
                                toolTipContent: "{label}: <strong>{y}%</strong>",
                                startAngle: -90,
                                //yValueFormatString: "##0.00\"%\"",
                                indexLabel: "{label}",
                                color: "#66CC66",
                                dataPoints: <?= $data ?>
                            }]
                    });

    <?php
    $chart++;
}

for ($i = 1; $i < $chart; $i++) {
    ?>
                    chart<?= $i ?>.render();
    <?php
}
?>
            }

<?php for ($i = 1; $i < $chart; $i++) { ?>

                function explodePie<?= $i ?>(e) {
                    if (typeof (e.dataSeries.dataPoints[e.dataPointIndex].exploded) === "undefined" || !e.dataSeries.dataPoints[e.dataPointIndex].exploded) {
                        e.dataSeries.dataPoints[e.dataPointIndex].exploded = true;
                    } else {
                        e.dataSeries.dataPoints[e.dataPointIndex].exploded = false;
                    }
                    e.chart.render();

                }
<?php }
?>
        </script>


        <?php for ($i = 1; $i < $chart; $i++) { ?>
        <div class="output">
            <div id="chartContainer<?= $i ?>"></div>
            <div style="padding-top:450px; text-align:center">

                <span style="background:#66CC66; padding:2px">Online: <?= $anteil_online[$i - 1] ?>%</span><br>
                <span style="background:#FF6666; padding:2px">Offline: <?= $anteil_offline[$i - 1] ?>%</span><br>
                <span style="background:#d0d0d0; padding:2px">None: <?= $anteil_none[$i - 1] ?>%</span></div>

            <div style="padding-top:50px; width:100%; margin-left:-1px; margin-top:-22px; border-bottom: #d0d0d0 1px solid; border-right:#d0d0d0 0px solid; border-left:#d0d0d0 1px solid; border-collapse: collapse"></div>
        </div>
        <?php
    }
    ?>
	<div style="clear:both; padding-top:50px">&nbsp;</div>
	
	<nav class="navbar py-0 fixed-bottom navbar-expand-md navbar-light" style="background-color:#E6E6E6">
      <span class="navbar-brand">Worker Statistik</span>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarCollapse">
        <ul class="navbar-nav ml-auto">
          <li class="nav-item dropup">
            <a class="nav-link dropdown-toggle" href="index.php" id="dropdown10" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Worker Status</a>
            <div class="dropdown-menu" aria-labelledby="dropdown10">
			  <a class="dropdown-item" href="index.php?reset=1">Reset Settings</a>
			  <a class="dropdown-item" href="index.php">Worker Status Page</a>
            </div>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="mad_set.php">IV List Manager</a>
          </li>
          <li class="nav-item active">
            <a class="nav-link" href="mad_stats.php?date=<?=date("Y-m-d")?>">Worker Statistik</a>
          </li>
        </ul>
      </div>
    </nav>
    <script src="https://canvasjs.com/assets/script/canvasjs.min.js"></script>
	<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
</body>
</html>