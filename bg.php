<?php 

	$montant = 0;

	if (isset($_GET["tipeee_id"])){
		$tipeee_id = htmlspecialchars($_GET["tipeee_id"]);
		$raw = file_get_contents("https://api.tipeee.com/v2.0/projects/".$tipeee_id);
		$json = json_decode($raw);
		$montant = intval($json->parameters->tipperAmount);
		unset($raw);
		unset($json);
	} else {
		$montant = intval(htmlspecialchars($_GET["montant"]));
	}
	
	$goal = intval(htmlspecialchars($_GET["goal"]));
	if ($goal > 0){
		$facteur_deg = $montant / $goal * 180;
	} else {
		$facteur_deg = 0;
	}
	if ($facteur_deg > 180) $facteur_deg = 180;
	
	if (isset($_GET["couleur"])){
		$couleur = htmlspecialchars($_GET["couleur"]);
	} else {
		$couleur = "9e00b1";
	}

	if (isset($_GET["label"])){
		$montant = htmlspecialchars($_GET["label"]);
	} else {
		$montant = $montant."€";
	}
	
// montant : montant actuel
// goal : montant 100%
// tipeee_id : recupere le montant sur la page tipeee correspondante (remplace montant)
// couleur : couleur du badge en hexa (sans le '#' devant)
// label : remplace le montant en € par un texte


?>
<!DOCTYPE html>
<html lang="en">
<head>
	<META HTTP-EQUIV="refresh" CONTENT="120">
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Badge goal</title>
 	<style type="text/css" media="screen">

@import url(http://fonts.googleapis.com/css?family=Roboto:400,700,300);

:root {
	--pourcentage-goal : calc(<?php echo $facteur_deg ?>deg);
}

body {
  font-family: "Roboto", sans-serif;
  background:transparent;
}

.circle-wrap {
  margin: 50px auto;
  width: 150px;
  height: 150px;
  background: #e6e2e7;
  border-radius: 50%;
}

.circle-wrap .circle .mask,
.circle-wrap .circle .fill {
  width: 150px;
  height: 150px;
  position: absolute;
  border-radius: 50%;
}

.circle-wrap .circle .mask {
  clip: rect(0px, 150px, 150px, 75px);
}

.circle-wrap .circle .mask .fill {
  clip: rect(0px, 75px, 150px, 0px);
  background-color: #<?php echo $couleur ?>; 
}

.circle-wrap .circle .mask.full,
.circle-wrap .circle .fill {
  animation: fill ease-in-out 3s;
  transform: rotate(var(--pourcentage-goal));
}

@keyframes fill {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(var(--pourcentage-goal));
  }
}

.circle-wrap .inside-circle {
  width: 130px;
  height: 130px;
  border-radius: 50%;
  background: #fffffff0;
  line-height: 130px;
  text-align: center;
  margin-top: 10px;
  margin-left: 10px;
  position: absolute;
  z-index: 100;
  font-weight: 700;
  font-size: 2em;
}

	</style>
</head>
<body>	
  <div class="circle-wrap">
    <div class="circle">
      <div class="mask full">
        <div class="fill"></div>
      </div>
      <div class="mask half">
        <div class="fill"></div>
      </div>
      <div class="inside-circle"><?php echo $montant ?></div>
    </div>
  </div>
  <br />
  <p>Variables de l'url :<br /></p>
  <ul>
  <li>montant : montant actuel</li>
  <li>goal : montant 100%</li>
  <li>tipeee_id : recupere le montant sur la page tipeee correspondante (remplace montant)</li>
  <li>couleur : couleur du badge en hexa (sans le '#' devant)</li>
  <li>label : remplace le montant en € par un texte</li>
  </ul>
  <p>Auto-refresh de la page toutes les 2 minutes</p>
  
</body>
</html>