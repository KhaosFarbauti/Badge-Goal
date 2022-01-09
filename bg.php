<?php 

	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1');
	error_reporting(E_ALL);

	ini_set('default_socket_timeout', 30);
	
	$erreur = false;

	$split = 0.5;

/***********  RECUPERATION DES PARAMETRES  *******************************************************************/	
// debug : si defini, affiche le detail des montants
// goal : montant 100%
// tipeee_id : recupere le montant sur la page tipeee correspondante (additionne avec les autres si definis)
// utip_id : recupere le montant sur la page utip correspondante (additionne avec les autres si definis)
// twitch_id : recupere le montant sur la page twitchtracker correspondante (additionne avec les autres si definis)
// tipeeestream_id : recupere le montant des donations sur la page tipeeestream correspondante (additionne avec les autres si definis)
// tipeeestream_token : token authentification necessaire pour tipeeestream
// twitch_id : recupere le montant sur la page twitchtracker correspondante (additionne avec les autres si definis)
// montant : montant actuel (si defini remplace tipeee/utip)
// ajout : pour ajouter un montant manuel en plus
// couleur : couleur du badge en hexa (sans le '#' devant)
// type : change l'apparence
// label : remplace le montant en € par le label
// pourcentage : remplace le montant par un pourcentage
// notext : masque les explications

	$debug = (isset($_GET['debug'])) ? true : false;

	$tipeee_id = (isset($_GET['tipeee_id'])) ? htmlspecialchars($_GET['tipeee_id']) : false;
	$utip_id = (isset($_GET['utip_id'])) ? htmlspecialchars($_GET['utip_id']) : false;
	$twitch_id = (isset($_GET['twitch_id'])) ? htmlspecialchars($_GET['twitch_id']) : false;
	$tipeeestream_id = (isset($_GET['tipeeestream_id'])) ? htmlspecialchars($_GET['tipeeestream_id']) : false;
	$tipeeestream_token = (isset($_GET['tipeeestream_token'])) ? htmlspecialchars($_GET['tipeeestream_token']) : false;

	$montant = (isset($_GET['montant'])) ? intval(htmlspecialchars($_GET['montant'])) : 0;
	$montant = (isset($_GET['ajout'])) ? $montant + intval(htmlspecialchars($_GET['ajout'])) : $montant;
	$goal = (isset($_GET['goal'])) ? intval(htmlspecialchars($_GET['goal'])) : 0;

	$couleur = (isset($_GET['couleur'])) ? substr(htmlspecialchars($_GET['couleur']),0,6) : '9e00b1';
	$type = (isset($_GET['type'])) ? intval(htmlspecialchars($_GET['type'])) : 0; 

	$notext = (isset($_GET['notext'])) ? true : false;
	
/*************************************************************************************************************/

	if ($tipeee_id){
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

	if ($utip_id){
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
	
	if ($twitch_id){
		$target = 'https://twitchtracker.com/'.$twitch_id.'/subscribers';

		$user_agent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36';
		$ckfile = tempnam (".", "targetwebpagecookie.txt");
		$ch = curl_init($target);
		curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
		curl_setopt($ch, CURLOPT_URL, $target);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
		curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
		curl_setopt($ch, CURLOPT_COOKIESESSION, TRUE);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $ckfile);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_ENCODING, "");
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		$raw = curl_exec($ch);
		curl_close($ch);
		unlink($ckfile);

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
	
	if ($tipeeestream_token && $tipeeestream_id){
		$raw = @file_get_contents('https://www.tipeeestream.com/v2.0/users/'.$tipeeestream_id.'/events.json?access_token='.$tipeeestream_token.'&limit=500&offset=0&type[]=donation&start='.date("Y-m-01"));
		if($raw === false){
			$erreur = true;
		}else{
			$json = json_decode($raw);
			$tipeeestream_dons = 0;
			foreach ($json->datas->events as $events){
				$tipeeestream_dons += $events->parameters->amount;
			}	
			unset($events);
			if ($debug){
				echo "tipeeestream (dons) = ".intval($tipeeestream_dons)."\n";
			}
			$montant = $montant + intval($tipeeestream_dons);
			unset($json);
			unset($tipeeestream_dons);
		}
		unset($raw);
	}

	$facteur_deg = ($goal > 0) ? $montant / $goal * 180 : 0;
	if ($facteur_deg > 180) $facteur_deg = 180;
	
	if (isset($_GET['pourcentage'])){
		$montant = ($goal > 0) ? intval($montant / $goal * 100) : 0;
		$montant = ($montant > 100) ? "FAIT !" : $montant.'%';
	}else{
		$montant = (isset($_GET['label'])) ? htmlspecialchars($_GET['label']) : $montant.'€';
	}

	if ($erreur){
		$montant="~".$montant;
	}
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
  <?php if (!$notext){ ?>
  <br />
  <p>Variables de l'url :<br /></p>
  <ul>
  <li>montant : montant actuel (si defini remplace tipeee/utip)</li>
  <li>goal : montant objectif</li>
  <li>tipeee_id : recupere le montant sur la page tipeee correspondante (additionne avec les autres si definis)</li>
  <li>utip_id : recupere le montant sur la page uTip correspondante (additionne avec les autres si definis)</li>
  <li>twitch_id : recupere le montant des subs twitchs (via le site <a href="https://twitchtracker.com">Twich Tracker</a>) du streamer correspondant (additionne avec les autres si definis)</li>
  <li>tipeeestream_id : recupere le montant des donations sur la page tipeeestream correspondante (additionne avec les autres si definis)</li>
  <li>tipeeestream_token : token authentification necessaire pour tipeeestream</li>
  <li>ajout : ajoute un montant supplementaire manuellement</li>
  <li>pourcentage : si defini, remplace le montant par un pourcentage</li>
  <li>couleur : couleur du badge en hexa (sans le '#' devant)</li>
  <li>label : remplace le montant en € par un texte</li>
  <li>type : 0 ou 1 pour changer l'apparence (par défaut 0)</li>
  <li>notext : si defini, masque les explications</li>
  </ul>
  <p>Exemple : <a href="bg.php?&montant=69&goal=100&couleur=FF4D3C">/bg.php?&montant=69&goal=100&couleur=FF4D3C</a></p>
  <p>Auto-refresh de la page toutes les 5 minutes</p>
  <p>Repository : <a href="https://github.com/KhaosFarbauti/Badge-Goal">https://github.com/KhaosFarbauti/Badge-Goal</a></p>
  <?php } ?>
</body>
</html>