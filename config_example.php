<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);


// mapadroid database
$dbHost     = "localhost";	// host
$dbUsername = "DB-USER";	// Username
$dbPassword = "PASSWORD";	// Password
$dbName     = "DATABASE";	// Database

$url	= 'https';		// "http" or "https"

$reload = 30;		  // reload page in seconds
$wartime= 180;		  // es wird nach dieser zeit (sekunden) lediglich eine warnung angezeigt
$offline= 10;		  // Geräte nach 10 Minuten als Offline kennzeichnen und Benachrichtigen

$beep	= 'beep.mp3'; // mp3 sound file
$notify = 1200;		  // bei offline erneute Benachrichtigung nach 20 Minuten

$breite	= '100%';	  // tabellenbreite, angabe in % oder px
$size	= '26px';	  // font-size in px or pt | not work on mobile
$pos	= 1;		  // Route Pos auf der mobilen Version anzeigen = 1 oder ausblenden = 0
$count	= 0;		  // notification counter in mobile page?
