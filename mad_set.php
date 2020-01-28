<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<?php
$config = parse_ini_file("config.ini", TRUE);



// Create database mapadroid
$mysqli = new mysqli($config["mysql"]["dbHost"], $config["mysql"]["dbUsername"], $config["mysql"]["dbPassword"], $config["mapadroid"]["dbName"]);
if ($mysqli->connect_error) {
	die('Error : ('. $mysqli->connect_errno .') '. $mysqli->connect_error);
}

$structure = __DIR__."/routecalc";
if (!file_exists($structure)) {
	if (!mkdir($structure, 0777, true)) {
    	die('Erstellung der Verzeichnisse schlug fehl...');
	}
}

// show pokemon_ids on string
if(isset($_GET["list"])) {

	$poeted = mysqli_real_escape_string($mysqli, $_GET["list"]);
	
	$sql = $mysqli->query("SELECT * FROM settings_monivlist WHERE monlist_id = $poeted ");
	$name = $sql->fetch_array();
	
	$get_list = $mysqli->query("SELECT * FROM settings_monivlist_to_mon WHERE monlist_id = $poeted ORDER BY mon_order asc");
	
	$ivs = array();
	while($iv = $get_list->fetch_array()) {
		$ivs[] = $iv["mon_id"];
	}
	
	$ivs = implode(', ',$ivs);
	echo '<a href="mad_set.php"><h1>back</h1></a>';
	echo '<h3>IV List of: <i>'.$name["name"].'</i></h3>';
	echo '<div style="width:90%; padding:10px; border:solid 1px #333333">'.$ivs.'</div>';
	exit();
}

// show routecalc on string
if(isset($_GET["route"])) {
	
	$poeted 	= mysqli_real_escape_string($mysqli, $_GET["route"]);
	
	if(isset($_POST["newroute"])) {
		$routefile	= mysqli_real_escape_string($mysqli, $_POST["route"]);
		mysqli_query($mysqli, "UPDATE settings_routecalc SET routefile = '$routefile' WHERE routecalc_id = ".$poeted);
		
		$datei = fopen(__DIR__."/routecalc/$poeted.txt","w");
		fwrite($datei, date("d.m.Y - H:i:s"),100);
		fclose($datei);
		
		echo '<h1 style="background:#009900">save</h1>';
	}
	
	$sql = $mysqli->query("SELECT * FROM settings_routecalc WHERE routecalc_id = $poeted ");
	$name = $sql->fetch_array();
	
	echo '<a href="mad_set.php"><h1>back</h1></a>';
	echo '<h3>routefile of: <i>'.$_GET["name"].'</i> | <span style="background:#FFFF00;padding:5px">[]</span> for recalc with "python3 start.py -or" !!</h3>';
	echo '<form action="" method="post">';
	echo '<textarea name="route" cols="220" rows="45" style="max-width:100%">'.$name["routefile"].'</textarea><br />';
	echo '<p><input type="submit" name="newroute" value="routecalc ändern!" /></p>';
	echo '</form>';
	exit();
}


// insert new iv list
if(isset($_POST["submit"]) and $_POST["name"] and $_POST["idlist"]) {
	
	$name = mysqli_real_escape_string($mysqli, $_POST["name"]);
	//$list = mysqli_real_escape_string($mysqli, $_POST["idlist"]);
	$list = $_POST["idlist"];
	$ivlist = explode(',',$list);
	$ivlist = array_map('trim', $ivlist);
	
	$insert_name = "INSERT INTO settings_monivlist SET guid = NULL, instance_id = '".$config["option"]["instance_id"]."', name = '".$name."' ";
	
	if($insert_name = $mysqli->query($insert_name)) {
		
		$monlist_id = $mysqli->insert_id;
		$i=0;
		
		foreach ($ivlist as $row) {
			if(is_numeric($row)) {
				$insert_ids .= mysqli_query($mysqli, "INSERT INTO settings_monivlist_to_mon SET monlist_id = $monlist_id, mon_id = $row, mon_order = $i");
				$i++;
			}
		}
		
		echo '<h1 style="background:#009900">save</h1>';
	}
} else {
	$tbl = '<table><tr><td><b>typ</b></td><td><b>name</b></td><td><b>area_id</b></td><td><b>calc</b></td><td><b>last change</b></td></tr>';
	$sql_p = $mysqli->query("SELECT q.routecalc_id, p.area_id, a.name FROM settings_routecalc q LEFT JOIN settings_area_pokestops p	ON p.routecalc = q.routecalc_id LEFT JOIN settings_area a ON a.area_id = p.area_id WHERE p.area_id IS NOT NULL ");
	$sql_m = $mysqli->query("SELECT q.routecalc_id, m.area_id, a.name FROM settings_routecalc q LEFT JOIN settings_area_mon_mitm m	ON m.routecalc = q.routecalc_id LEFT JOIN settings_area a ON a.area_id = m.area_id WHERE m.area_id IS NOT NULL ");
	$sql_i = $mysqli->query("SELECT q.routecalc_id, i.area_id, a.name FROM settings_routecalc q LEFT JOIN settings_area_iv_mitm i	ON i.routecalc = q.routecalc_id LEFT JOIN settings_area a ON a.area_id = i.area_id WHERE i.area_id IS NOT NULL ");
	$sql_r = $mysqli->query("SELECT q.routecalc_id, r.area_id, a.name FROM settings_routecalc q LEFT JOIN settings_area_raids_mitm r	ON r.routecalc = q.routecalc_id LEFT JOIN settings_area a ON a.area_id = r.area_id WHERE r.area_id IS NOT NULL ");
	$out = '';	  
	while($id = $sql_m->fetch_array() ) {
		$change = file_exists($structure."/".$id["routecalc_id"].".txt") ? file_get_contents(__DIR__."/routecalc/".$id["routecalc_id"].".txt") : 'NULL';
		$out.= '<tr class="p"><td>mon_mitm</td><td>'.$id["name"].'</td><td>'.$id["area_id"].'</td><td><a href="?route='.$id["routecalc_id"].'&amp;name='.$id["name"].'">'.$id["routecalc_id"].'</a></td><td>'.$change.'</td></tr>';
	}
	while($id = $sql_p->fetch_array() ) {
		$change = file_exists($structure."/".$id["routecalc_id"].".txt") ? file_get_contents(__DIR__."/routecalc/".$id["routecalc_id"].".txt") : 'NULL';
		$out.= '<tr class="m"><td>pokestops</td><td>'.$id["name"].'</td><td>'.$id["area_id"].'</td><td><a href="?route='.$id["routecalc_id"].'&amp;name='.$id["name"].'">'.$id["routecalc_id"].'</a></td><td>'.$change.'</td></tr>';
	}
	while($id = $sql_i->fetch_array() ) {
		$change = file_exists($structure."/".$id["routecalc_id"].".txt") ? file_get_contents(__DIR__."/routecalc/".$id["routecalc_id"].".txt") : 'NULL';
		$out.= '<tr class="i"><td>iv_mitm</td><td>'.$id["name"].'</td><td>'.$id["area_id"].'</td><td><a href="?route='.$id["routecalc_id"].'&amp;name='.$id["name"].'">'.$id["routecalc_id"].'</a></td><td>'.$change.'</td></tr>';
	}
	while($id = $sql_r->fetch_array() ) {
		$change = file_exists($structure."/".$id["routecalc_id"].".txt") ? file_get_contents(__DIR__."/routecalc/".$id["routecalc_id"].".txt") : 'NULL';
		$out.= '<tr class="r"><td>raids_mitm</td><td>'.$id["name"].'</td><td>'.$id["area_id"].'</td><td><a href="?route='.$id["routecalc_id"].'&amp;name='.$id["name"].'">'.$id["routecalc_id"].'</a></td><td>'.$change.'</td></tr>';
	}
		$end = '</table>';
}


// show all your iv lists
$sql = $mysqli->query("SELECT * FROM settings_monivlist");
$anzahl = $sql->num_rows;
if($anzahl != 0) {
	$sp = "<p><b>my IV lists:</b> ";
	$monlists = '';
	$n=0;
	while($show = $sql->fetch_array()) {
		$n++;
		if($n == $anzahl) {
			$monlists .= '&nbsp;&nbsp;&nbsp;<a href="?list='.$show["monlist_id"].'">'.$show["name"].'</a>';
		} else {
			$monlists .= '&nbsp;&nbsp;&nbsp;<a href="?list='.$show["monlist_id"].'">'.$show["name"].'</a>&nbsp;&nbsp;&nbsp;|';
		}
	} 
	$ep = "</p>";
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
<title>MAD - IV List Manager</title>
<style>
.p {
background: #FF9966
}
.m {
background: #6699FF
}
.i {
background: #FF6633
}
.r {
background: #99CC00
}
td {
padding-left:2px;
padding-right:2px;
line-height:160%;
border-collapse: collapse
}
table {
border-collapse: collapse
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
</style>
</head>
<body>

<div style="float:left; padding:10px">
<h3>Iv Listen anzeigen / anlegen</h3>
<?php 
if(isset($sp)) {
	echo $sp.$monlists.$ep;
}
?>
<form action="" method="post">
monlist Name:<br /><input type="text" name="name" size="20" /><br />
list of ids:<br /><textarea name="idlist" cols="80" rows="20" style="width:100%" placeholder="1,2,3,4,11"></textarea><br />
<p><input type="submit" name="submit" value="Liste anlegen" /></p>
</form>
</div>

<?php
if(isset($tbl)) { ?>
	<div style="float:left; max-width:100%; padding-top:15px"><h3 style="padding-left:10px">Routecalc finden</h3><?=$tbl.$out.$end ?></div>
	<div style="clear:both; padding-top:50px">&nbsp;</div>
<?php } ?>

	<nav class="navbar py-0 fixed-bottom navbar-expand-md navbar-light" style="background-color:#E6E6E6">
      <span class="navbar-brand">IV Manager</span>
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
          <li class="nav-item active">
            <a class="nav-link" href="mad_set.php">IV List Manager</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="mad_stats.php?date=<?=date("Y-m-d")?>">Worker Statistik</a>
          </li>
        </ul>
      </div>
    </nav>

<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
</body>
</html>