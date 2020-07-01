<?php 

	$montant = 0;
	
	ini_set('default_socket_timeout', 20);
	
	if (isset($_GET["tipeee_id"])){
		$tipeee_id = htmlspecialchars($_GET["tipeee_id"]);
		set_error_handler(function() { /* pour catcher le warning */ });
		$raw = file_get_contents("https://api.tipeee.com/v2.0/projects/".$tipeee_id);
		restore_error_handler();
		$json = json_decode($raw);
		$montant = $montant + intval($json->parameters->tipperAmount);
		unset($raw);
		unset($json);
	}
	
	if (isset($_GET["utip_id"])){
		$tipeee_id = htmlspecialchars($_GET["utip_id"]);
		set_error_handler(function() { /* pour catcher le warning */ });
		$raw = file_get_contents("https://www.utip.io/creator/profile/stats/".$utip_id."/earned");
		restore_error_handler();
		$json = json_decode($raw);
		$montant = $montant + intval($json->parameters->amountEarned);
		unset($raw);
		unset($json);
	}
		
	if (isset($_GET["montant"])){		
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
	
	if (isset($_GET["type"])){
		$type = intval(htmlspecialchars($_GET["type"]));
	} else {
		$type = 0;
	}
	
// montant : montant actuel (si defini remplace tipeee/utip)
// goal : montant 100%
// tipeee_id : recupere le montant sur la page tipeee correspondante (additionne avec utip si defini)
// utip_id : recupere le montant sur la page tipeee correspondante (additionne avec tipeee si defini)
// couleur : couleur du badge en hexa (sans le '#' devant)
// label : remplace le montant en € par un texte
// Type : change l'apparence


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
  background: <?php if ($type == 1){echo "transparent";} else {echo "#e6e2e7";} ?>;
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
  background: <?php if ($type == 1){echo "#34495e;\n color: #ecf0f1";} else {echo "#fffffff0";} ?>;
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
  <li>montant : montant actuel (si defini remplace tipeee/utip)</li>
  <li>goal : montant 100%</li>
  <li>tipeee_id : recupere le montant sur la page tipeee correspondante (additionne avec utip si defini)</li>
  <li>utip_id : recupere le montant sur la page uTip correspondante (additionne avec tipeee si defini)</li>
  <li>couleur : couleur du badge en hexa (sans le '#' devant)</li>
  <li>label : remplace le montant en € par un texte</li>
  <li>type : 0 ou 1 pour changer l'apparence (par défaut 0)</li>
  </ul>
  <p>Auto-refresh de la page toutes les 2 minutes</p>
  <p>Repository : <a href="https://bitbucket.org/khaos_farbauti/badge-goal/src/master/">https://bitbucket.org/khaos_farbauti/badge-goal/src/master/</a></p>
  
</body>
</html>