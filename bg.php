<?php 

	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1');
	error_reporting(E_ALL);

	$montant = 0;
	$erreur = false;
	$split = 0.5;
	
	ini_set('default_socket_timeout', 30);
		
	if (isset($_GET['debug'])){
		$debug = true;
	} else {
		$debug = false;
	}

	if (isset($_GET['tipeee_id'])){
		$tipeee_id = htmlspecialchars($_GET['tipeee_id']);
		$raw = @file_get_contents('https://api.tipeee.com/v2.0/projects/'.$tipeee_id);
		if($raw === false){
			$erreur = true;
		}else{
			$json = json_decode($raw);
			if ($debug){
				echo "tipeee = ".intval($json->parameters->tipperAmount)."\n";
			}
			$montant = $montant + intval($json->parameters->tipperAmount);
			unset($json);
		}
		unset($raw);
	}

	if (isset($_GET['utip_id'])){
		$utip_id = htmlspecialchars($_GET['utip_id']);
		$raw = @file_get_contents('https://www.utip.io/creator/profile/stats/'.$utip_id.'/earned');
		if($raw === false){
			$erreur = true;
		}else{
			$json = json_decode($raw);
			if ($debug){
				echo "utip = ".intval(intval($json->stats->amountEarned) / 100)."\n";
			}
			$montant = $montant + intval(intval($json->stats->amountEarned) / 100);
			unset($json);
		}
		unset($raw);
	}
	
	if (isset($_GET['twitch_id'])){
		$twitch_id = htmlspecialchars($_GET['twitch_id']);
		$raw = @file_get_contents('https://twitchtracker.com/'.$twitch_id.'/subscribers');
		if($raw === false){
			$erreur = true;
		}else{
			$dom = new DOMDocument;
			@$dom->loadHTML($raw);
			$classname="g-x-s-value to-number";
			$finder = new DomXPath($dom);
			if ($debug){
				echo "tier 1 = ".intval(intval($finder->query("//div[contains(@class, '$classname')]")->item(3)->nodeValue)*3.99*$split)."\n";
				echo "tier 2 = ".intval(intval($finder->query("//div[contains(@class, '$classname')]")->item(4)->nodeValue)*7.99*$split)."\n";
				echo "tier 3 = ".intval(intval($finder->query("//div[contains(@class, '$classname')]")->item(5)->nodeValue)*19.99*$split)."\n";
			}
			$montant = $montant + intval(intval($finder->query("//div[contains(@class, '$classname')]")->item(3)->nodeValue)*3.99*$split);   //Tier 1
			$montant = $montant + intval(intval($finder->query("//div[contains(@class, '$classname')]")->item(4)->nodeValue)*7.99*$split);   //Tier 2
			$montant = $montant + intval(intval($finder->query("//div[contains(@class, '$classname')]")->item(5)->nodeValue)*19.99*$split);   //Tier 3
			unset($dom);
			unset($finder);
		}
		unset($raw);
	}
		
	if (isset($_GET['montant'])){		
		$montant = intval(htmlspecialchars($_GET['montant']));
	}

	if (isset($_GET['ajout'])){
		$montant = $montant + intval(htmlspecialchars($_GET['ajout']));
	}
	
	if (isset($_GET['goal'])){
		$goal = intval(htmlspecialchars($_GET['goal']));
	}else{
		$goal = 0;
	}
	if ($goal > 0){
		$facteur_deg = $montant / $goal * 180;
	} else {
		$facteur_deg = 0;
	}
	if ($facteur_deg > 180) $facteur_deg = 180;
	
	if (isset($_GET['couleur'])){
		$couleur = htmlspecialchars($_GET['couleur']);
	} else {
		$couleur = '9e00b1';
	}

	if (isset($_GET['pourcentage'])){
		$pourcentage = intval(htmlspecialchars($_GET['pourcentage']));
	} else {
		$pourcentage = 0;
	}

	if ($pourcentage === 1){
		if ($goal > 0){
			$montant = intval($montant / $goal * 100).'%';
		} else {
			$montant = '0%';
		}
	} else if (isset($_GET['label'])){
			$montant = htmlspecialchars($_GET['label']);
		} else {
			$montant = $montant.'€';
	}

	if (isset($_GET['type'])){
		$type = intval(htmlspecialchars($_GET['type']));
	} else {
		$type = 0;
	}

	if (isset($_GET['notext'])){
		$notext = intval(htmlspecialchars($_GET['notext']));
	} else {
		$notext = 0;
	}

	if ($erreur){
		unset($montant);
		$montant="ERR";
	}
	
// montant : montant actuel (si defini remplace tipeee/utip)
// goal : montant 100%
// tipeee_id : recupere le montant sur la page tipeee correspondante (additionne avec utip/twitch si defini)
// utip_id : recupere le montant sur la page utip correspondante (additionne avec tipeee/twitch si defini)
// twitch_id : recupere le montant sur la page twitchtracker correspondante (additionne avec tipeee/utip si defini)
// couleur : couleur du badge en hexa (sans le '#' devant)
// label : remplace le montant en € par un texte
// Type : change l'apparence
// Pourcentage : si 1, remplace le montant par un pourcentage
// notext : masque les explications
// ajout : pour ajouter un montant manuel en plus


?>
<!DOCTYPE html>
<html lang="en">
<head>
	<META HTTP-EQUIV="refresh" CONTENT="300">
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
  background: <?php if ($type === 1){echo 'transparent';} else {echo '#e6e2e7';} ?>;
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
  background: <?php if ($type === 1){echo '#34495e;color: #ecf0f1';} else {echo '#fffffff0';} ?>;
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
  <?php if ($notext != 1){ ?>
  <br />
  <p>Variables de l'url :<br /></p>
  <ul>
  <li>montant : montant actuel (si defini remplace tipeee/utip)</li>
  <li>goal : montant objectif</li>
  <li>tipeee_id : recupere le montant sur la page tipeee correspondante (additionne avec utip/twitch si defini)</li>
  <li>utip_id : recupere le montant sur la page uTip correspondante (additionne avec tipeee/twitch si defini)</li>
  <li>twitch_id : recupere le montant des subs twitchs (via le site <a href="https://twitchtracker.com">Twich Tracker</a>) du streamer correspondant (additionne avec tipeee/uTip si defini)</li>
  <li>ajout : ajoute un montant supplementaire manuellement</li>
  <li>pourcentage : si definit a 1, remplace le montant par un pourcentage</li>
  <li>couleur : couleur du badge en hexa (sans le '#' devant)</li>
  <li>label : remplace le montant en € par un texte</li>
  <li>type : 0 ou 1 pour changer l'apparence (par défaut 0)</li>
  <li>notext : si definit a 1, masque les explications</li>
  </ul>
  <p>Exemple : <a href="bg.php?&montant=69&goal=100&couleur=FF4D3C">/bg.php?&montant=69&goal=100&couleur=FF4D3C</a></p>
  <p>Auto-refresh de la page toutes les 5 minutes</p>
  <p>Repository : <a href="https://github.com/KhaosFarbauti/Badge-Goal">https://github.com/KhaosFarbauti/Badge-Goal</a></p>
  <?php } ?>
</body>
</html>