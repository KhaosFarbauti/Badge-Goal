<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

ini_set('default_socket_timeout', 30);

/* --------------------------------------------------------------------------
   CONFIGURATION
-------------------------------------------------------------------------- */

const SPLIT  = 0.5;
const TIER1  = 4.99;
const TIER2  = 7.99;
const TIER3  = 19.99;
const BITS   = 0.0159;

const CURL_CONNECT_TIMEOUT = 3;
const CURL_TIMEOUT         = 5;

/* --------------------------------------------------------------------------
   FONCTIONS UTILITAIRES
-------------------------------------------------------------------------- */

/**
 * Récupère un paramètre GET filtré, avec une valeur par défaut.
 */
function get_param(string $name, int $filter = FILTER_UNSAFE_RAW, mixed $default = null): mixed
{
    $value = filter_input(INPUT_GET, $name, $filter);
    return ($value !== null && $value !== false) ? $value : $default;
}

/**
 * Effectue une requête HTTP via file_get_contents et décode le JSON.
 * Positionne $errorFlag à true en cas d'échec.
 */
function safe_get_json(string $url, bool &$errorFlag): ?object
{
    $raw = @file_get_contents($url);
    if ($raw === false) {
        $errorFlag = true;
        return null;
    }
    $decoded = json_decode($raw);
    if ($decoded === null) {
        $errorFlag = true;
        return null;
    }
    return $decoded;
}

/**
 * Effectue une requête HTTP via cURL et retourne le corps brut.
 * Positionne $errorFlag à true en cas d'échec.
 */
function safe_curl(string $url, bool &$errorFlag): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => CURL_CONNECT_TIMEOUT,
        CURLOPT_TIMEOUT        => CURL_TIMEOUT,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $err !== '') {
        $errorFlag = true;
        return null;
    }
    return $raw;
}

/**
 * Valide une couleur hexadécimale (3 ou 6 caractères, sans '#').
 * Retourne la couleur normalisée en 6 caractères, ou la valeur par défaut.
 */
function validate_hex_color(string $color, string $default = '9e00b1'): string
{
    $color = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $color));
    if (preg_match('/^[0-9a-f]{6}$/', $color)) {
        return $color;
    }
    if (preg_match('/^([0-9a-f])([0-9a-f])([0-9a-f])$/', $color, $m)) {
        return $m[1].$m[1].$m[2].$m[2].$m[3].$m[3];
    }
    return $default;
}

/**
 * Valide une date au format Y-m-d.
 * Retourne la date validée ou la valeur par défaut.
 */
function validate_date(string $date, string $default): string
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return ($d && $d->format('Y-m-d') === $date) ? $date : $default;
}

/* --------------------------------------------------------------------------
   COLLECTEURS DE MONTANTS
-------------------------------------------------------------------------- */

/**
 * Récupère le montant cumulé depuis Tipeee.
 */
function fetch_tipeee(string $id, bool &$errorFlag): float
{
    $json = safe_get_json("https://api.tipeee.com/v2.0/projects/$id", $errorFlag);
    if ($json && isset($json->parameters->tipperAmount)) {
        return floatval($json->parameters->tipperAmount);
    }
    return 0.0;
}

/**
 * Récupère le montant estimé des abonnements Twitch via TwitchTracker.
 * Prend le max entre le calcul "actifs" et le calcul "par palier".
 */
function fetch_twitch(string $id, bool &$errorFlag): float
{
    $raw = safe_curl("https://twitchtracker.com/$id/subscribers", $errorFlag);
    if (!$raw) {
        return 0.0;
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($raw);
    $xpath = new DOMXPath($dom);

    // Abonnés actifs (estimation globale)
    $activeNode = $xpath->query("//span[contains(@class,'to-number')]")->item(0);
    $active     = $activeNode ? intval($activeNode->nodeValue) : 0;
    $byActive   = intval($active * TIER1 * SPLIT);

    // Détail par palier
    $tierNodes = $xpath->query("//div[contains(@class,'g-x-s-value') and contains(@class,'to-number')]");
    $t1 = $tierNodes->item(3) ? intval($tierNodes->item(3)->nodeValue) : 0;
    $t2 = $tierNodes->item(4) ? intval($tierNodes->item(4)->nodeValue) : 0;
    $t3 = $tierNodes->item(5) ? intval($tierNodes->item(5)->nodeValue) : 0;
    $byTier = intval($t1 * TIER1 * SPLIT + $t2 * TIER2 * SPLIT + $t3 * TIER3 * SPLIT);

    return floatval(max($byActive, $byTier));
}

/**
 * Récupère le montant (abonnements + dons + cheers) depuis TipeeeStream.
 */
function fetch_tipeeestream(string $id, string $token, string $dateDepart, bool &$errorFlag): float
{
    $total = 0.0;

    // Abonnements Twitch
    $json = safe_get_json("https://api.tipeeestream.com/v1.0/events/forever.json?apiKey=$token", $errorFlag);
    if ($json && isset($json->datas->details->twitch->subscribers)) {
        $total += floatval($json->datas->details->twitch->subscribers) * TIER1 * SPLIT;
    }

    // Dons et cheers depuis $dateDepart
    $eventTypes = ['donation', 'cheer'];
    foreach ($eventTypes as $type) {
        $url  = "https://www.tipeeestream.com/v2.0/users/$id/events.json"
              . "?access_token=$token&limit=1000&offset=0&type[]=$type&start=$dateDepart";
        $json = safe_get_json($url, $errorFlag);

        if ($json && isset($json->datas->events)) {
            foreach ($json->datas->events as $event) {
                if ($type === 'donation') {
                    $total += floatval($event->parameters->amount ?? 0);
                } else {
                    $total += floatval($event->parameters->cheersSpend ?? 0) * BITS;
                }
            }
        }
    }

    return $total;
}

/* --------------------------------------------------------------------------
   LECTURE DES PARAMÈTRES
-------------------------------------------------------------------------- */

$debug = isset($_GET['debug']);

$tipeee_id          = get_param('tipeee_id');
$twitch_id          = get_param('twitch_id');
$tipeeestream_id    = get_param('tipeeestream_id');
$tipeeestream_token = get_param('tipeeestream_token');

$goal        = (int) get_param('goal', FILTER_VALIDATE_INT, 0);
$couleur     = validate_hex_color((string) get_param('couleur', FILTER_UNSAFE_RAW, ''));
$type        = (int) get_param('type', FILTER_VALIDATE_INT, 0);
$notext      = isset($_GET['notext']);
$date_depart = validate_date(
    (string) get_param('date_depart', FILTER_UNSAFE_RAW, ''),
    date('Y-01-01')
);

/* --------------------------------------------------------------------------
   COLLECTE DES MONTANTS
-------------------------------------------------------------------------- */

$erreur  = false;
$montant = 0.0;

if ($tipeee_id) {
    $value = fetch_tipeee($tipeee_id, $erreur);
    if ($debug) echo "tipeee = $value\n";
    $montant += $value;
}

if ($twitch_id) {
    $value = fetch_twitch($twitch_id, $erreur);
    if ($debug) echo "twitch = $value\n";
    $montant += $value;
}

if ($tipeeestream_token && $tipeeestream_id) {
    $value = fetch_tipeeestream($tipeeestream_id, $tipeeestream_token, $date_depart, $erreur);
    if ($debug) echo "tipeeestream = $value\n";
    $montant += $value;
}

/* --------------------------------------------------------------------------
   CALCUL FINAL
-------------------------------------------------------------------------- */

$montant_override = get_param('montant', FILTER_VALIDATE_INT, null);
if ($montant_override !== null) {
    $montant = (float) $montant_override;
}

$montant += (float) get_param('ajout', FILTER_VALIDATE_INT, 0);

$facteur_deg = ($goal > 0) ? min(180.0, ($montant / $goal) * 180.0) : 0.0;

if (isset($_GET['pourcentage'])) {
    $pct     = ($goal > 0) ? intval($montant / $goal * 100) : 0;
    $label   = ($pct >= 100) ? 'FAIT\u00a0!' : $pct . '%';
} else {
    $raw_label = (string) get_param('label', FILTER_UNSAFE_RAW, intval($montant) . '€');
    $label     = htmlspecialchars($raw_label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

if ($erreur) {
    $label = '~' . $label;
}

/* --------------------------------------------------------------------------
   RENDU HTML
-------------------------------------------------------------------------- */

$bg_circle  = ($type === 1) ? 'transparent'                 : '#e6e2e7';
$bg_inside  = ($type === 1) ? '#34495e' : '#fffffff0';
$text_color = ($type === 1) ? '#ecf0f1' : 'inherit';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="refresh" content="300">
  <title>Badge goal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --angle-goal: <?php echo number_format($facteur_deg, 4, '.', ''); ?>deg;
      --color-fill: #<?php echo htmlspecialchars($couleur, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: "Roboto", sans-serif;
      background: transparent;
    }

    .circle-wrap {
      margin: 50px auto;
      width: 150px;
      height: 150px;
      background: <?php echo $bg_circle; ?>;
      border-radius: 50%;
      position: relative;
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
      background-color: var(--color-fill);
    }

    .circle-wrap .circle .mask.full,
    .circle-wrap .circle .fill {
      animation: fill ease-in-out 3s both;
      transform: rotate(var(--angle-goal));
    }

    @keyframes fill {
      from { transform: rotate(0deg); }
      to   { transform: rotate(var(--angle-goal)); }
    }

    .circle-wrap .inside-circle {
      width: 130px;
      height: 130px;
      border-radius: 50%;
      background: <?php echo $bg_inside; ?>;
      color: <?php echo $text_color; ?>;
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
      <div class="mask full"><div class="fill"></div></div>
      <div class="mask half"><div class="fill"></div></div>
      <div class="inside-circle"><?php echo $label; ?></div>
    </div>
  </div>

  <?php if (!$notext) : ?>
  <br>
  <p>Variables de l'url&nbsp;:</p>
  <ul>
    <li><code>montant</code>&nbsp;: montant actuel (si défini, remplace tipeee/twitch/… mais conserve «&nbsp;ajout&nbsp;»)</li>
    <li><code>goal</code>&nbsp;: montant objectif</li>
    <li><code>tipeee_id</code>&nbsp;: récupère le montant sur la page Tipeee correspondante</li>
    <li><code>twitch_id</code>&nbsp;: récupère le montant des subs Twitch (via <a href="https://twitchtracker.com">TwitchTracker</a>)</li>
    <li><code>tipeeestream_id</code>&nbsp;: identifiant TipeeeStream (requis avec <code>tipeeestream_token</code>)</li>
    <li><code>tipeeestream_token</code>&nbsp;: token d'authentification TipeeeStream</li>
    <li><code>ajout</code>&nbsp;: ajoute un montant supplémentaire manuellement</li>
    <li><code>date_depart</code>&nbsp;: date de début pour les événements TipeeeStream (format&nbsp;: YYYY-MM-DD, défaut&nbsp;: 1er janvier de l'année courante)</li>
    <li><code>pourcentage</code>&nbsp;: si défini, affiche un pourcentage à la place du montant</li>
    <li><code>couleur</code>&nbsp;: couleur du badge en hexadécimal (sans «&nbsp;#&nbsp;», ex.&nbsp;: FF4D3C)</li>
    <li><code>label</code>&nbsp;: remplace le montant en € par un texte libre</li>
    <li><code>type</code>&nbsp;: <code>0</code> (clair) ou <code>1</code> (sombre)</li>
    <li><code>notext</code>&nbsp;: si défini, masque cette aide</li>
    <li><code>debug</code>&nbsp;: si défini, affiche les valeurs intermédiaires</li>
  </ul>
  <p>Exemple&nbsp;: <a href="bg.php?montant=69&amp;goal=100&amp;couleur=FF4D3C">bg.php?montant=69&amp;goal=100&amp;couleur=FF4D3C</a></p>
  <p>Auto-refresh toutes les 5 minutes.</p>
  <p>Repository&nbsp;: <a href="https://github.com/KhaosFarbauti/Badge-Goal">https://github.com/KhaosFarbauti/Badge-Goal</a></p>
  <?php endif; ?>
</body>
</html>
