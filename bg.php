<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

ini_set('default_socket_timeout', 30);

$erreur = false;

/* --------------------------------------------------------------------------
   CONFIG
-------------------------------------------------------------------------- */

$split = 0.5;
$tier1 = 4.99;
$tier2 = 7.99;
$tier3 = 19.99;
$bits  = 0.0159;

/* --------------------------------------------------------------------------
   FONCTIONS UTILES
-------------------------------------------------------------------------- */

function get_param($name, $filter = FILTER_UNSAFE_RAW, $default = null) {
    $value = filter_input(INPUT_GET, $name, $filter);
    return $value !== null ? $value : $default;
}

function safe_get_json($url, &$errorFlag) {
    $raw = @file_get_contents($url);
    if ($raw === false) {
        $errorFlag = true;
        return null;
    }
    return json_decode($raw);
}

function safe_curl($url, &$errorFlag) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $raw = curl_exec($ch);
    if ($raw === false) {
        $errorFlag = true;
        curl_close($ch);
        return null;
    }

    curl_close($ch);
    return $raw;
}

/* --------------------------------------------------------------------------
   PARAMÈTRES
-------------------------------------------------------------------------- */

$debug = isset($_GET['debug']);

$tipeee_id         = get_param('tipeee_id');
$utip_id           = get_param('utip_id');
$twitch_id         = get_param('twitch_id');
$tipeeestream_id   = get_param('tipeeestream_id');
$tipeeestream_token= get_param('tipeeestream_token');

$goal              = get_param('goal', FILTER_VALIDATE_INT, 0);
$couleur           = substr(get_param('couleur', FILTER_UNSAFE_RAW, '9e00b1'), 0, 6);
$type              = get_param('type', FILTER_VALIDATE_INT, 0);
$notext            = isset($_GET['notext']);
$date_depart       = get_param('date_depart', FILTER_UNSAFE_RAW, date("Y-01-01"));

$montant = 0;

/* --------------------------------------------------------------------------
   TIPEEE
-------------------------------------------------------------------------- */

if ($tipeee_id) {
    $json = safe_get_json("https://api.tipeee.com/v2.0/projects/$tipeee_id", $erreur);
    if ($json && isset($json->parameters->tipperAmount)) {
        $value = floatval($json->parameters->tipperAmount);
        if ($debug) echo "tipeee = $value\n";
        $montant += $value;
    }
}

/* --------------------------------------------------------------------------
   TWITCHTRACKER
-------------------------------------------------------------------------- */

if ($twitch_id) {
    $raw = safe_curl("https://twitchtracker.com/$twitch_id/subscribers", $erreur);

    if ($raw) {
        $dom = new DOMDocument;
        @$dom->loadHTML($raw);
        $finder = new DomXPath($dom);

        // Active subs
        $activeNode = $finder->query("//span[contains(@class, 'to-number')]")->item(0);
        $active = $activeNode ? intval($activeNode->nodeValue) : 0;

        $choixa = intval($active * $tier1 * $split);

        // Tiers
        $tierNodes = $finder->query("//div[contains(@class, 'g-x-s-value') and contains(@class, 'to-number')]");
        $t1 = $tierNodes->item(3) ? intval($tierNodes->item(3)->nodeValue) : 0;
        $t2 = $tierNodes->item(4) ? intval($tierNodes->item(4)->nodeValue) : 0;
        $t3 = $tierNodes->item(5) ? intval($tierNodes->item(5)->nodeValue) : 0;

        $choixb = intval($t1*$tier1*$split + $t2*$tier2*$split + $t3*$tier3*$split);

        if ($debug) {
            echo "twitchtracker current active = $choixa\n";
            echo "twitchtracker tier 1 = ".($t1*$tier1*$split)."\n";
            echo "twitchtracker tier 2 = ".($t2*$tier2*$split)."\n";
            echo "twitchtracker tier 3 = ".($t3*$tier3*$split)."\n";
        }

        $montant += max($choixa, $choixb);
    }
}

/* --------------------------------------------------------------------------
   TIPEEESTREAM
-------------------------------------------------------------------------- */

if ($tipeeestream_token) {

    // Subs
    $json = safe_get_json("https://api.tipeeestream.com/v1.0/events/forever.json?apiKey=$tipeeestream_token", $erreur);
    if ($json && isset($json->datas->details->twitch->subscribers)) {
        $sub = floatval($json->datas->details->twitch->subscribers * $tier1 * $split);
        if ($debug) echo "tipeeestream (sub) = $sub\n";
        $montant += $sub;
    }

    // Donations + Cheers
    $types = [
        "donation" => function($e){ return $e->parameters->amount ?? 0; },
        "cheer"    => function($e){ return ($e->parameters->cheersSpend ?? 0) * 0.0159; }
    ];

    foreach ($types as $typeName => $extractor) {
        $url = "https://www.tipeeestream.com/v2.0/users/$tipeeestream_id/events.json?access_token=$tipeeestream_token&limit=1000&offset=0&type[]=$typeName&start=$date_depart";
        $json = safe_get_json($url, $erreur);

        if ($json && isset($json->datas->events)) {
            $sum = 0;
            foreach ($json->datas->events as $e) {
                $sum += $extractor($e);
            }
            if ($debug) echo "tipeeestream ($typeName) = $sum\n";
            $montant += $sum;
        }
    }
}

/* --------------------------------------------------------------------------
   CALCUL FINAL
-------------------------------------------------------------------------- */

$montant = get_param('montant', FILTER_VALIDATE_INT, $montant);
$montant += get_param('ajout', FILTER_VALIDATE_INT, 0);

$facteur_deg = ($goal > 0) ? min(180, ($montant / $goal) * 180) : 0;

if (isset($_GET['pourcentage'])) {
    $montant = ($goal > 0) ? intval($montant / $goal * 100) : 0;
    $montant = ($montant > 100) ? "FAIT !" : $montant . '%';
} else {
    $montant = get_param('label', FILTER_UNSAFE_RAW, $montant . '€');
}

if ($erreur) {
    $montant = "~" . $montant;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<META HTTP-EQUIV="refresh" CONTENT="300">
<title>Badge goal</title>

<style>
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
  <li>montant : montant actuel (si defini remplace tipeee/utip/twitch/... mais conserve "ajout")</li>
  <li>goal : montant objectif</li>
  <li>tipeee_id : recupere le montant sur la page tipeee correspondante (additionne avec les autres si definis)</li>
  <li>twitch_id : recupere le montant des subs twitchs (via le site <a href="https://twitchtracker.com">Twich Tracker</a>) du streamer correspondant (additionne avec les autres si definis)</li>
  <li>tipeeestream_id : recupere le montant des donations et subs sur la page tipeeestream correspondante (additionne avec les autres si definis)</li>
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