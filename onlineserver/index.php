<?php
error_reporting(0);
session_start();

// ==========================================
// KONSUMEN / KONFIGURASI API STEAM
// ==========================================
define('STEAM_API_KEY', '73BD05D1FC479396D065828B004EF335');
define('STEAM_SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER["REQUEST_URI"], '?'));

// ==========================================
// 1. PROSES AUTH STEAM OPENID (LOGIN / LOGOUT)
// ==========================================
if (isset($_GET['login'])) {
    $params = [
        'openid.ns'         => 'http://specs.openid.net/auth/2.0',
        'openid.mode'       => 'checkid_setup',
        'openid.return_to'  => STEAM_SITE_URL,
        'openid.realm'      => 'http://' . $_SERVER['HTTP_HOST'],
        'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
        'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
    ];
    header('Location: https://steamcommunity.com/openid/login?' . http_build_query($params));
    exit;
}

if (isset($_GET['logout'])) {
    unset($_SESSION['steamid']);
    header('Location: ' . STEAM_SITE_URL);
    exit;
}

if (isset($_GET['openid_mode']) && $_GET['openid_mode'] === 'id_res') {
    $uri = 'https://steamcommunity.com/openid/login?' . http_build_query($_GET);
    $opts = ['http' => ['method' => 'POST', 'header' => "Content-type: application/x-www-form-urlencoded\r\n", 'content' => 'openid.mode=check_authentication']];
    $context = stream_context_create($opts);
    $result = @file_get_contents($uri, false, $context);
    
    if (preg_match("/is_valid:true\s/i", $result)) {
        preg_match('/^https:\/\/steamcommunity\.com\/openid\/id\/(7656119[0-9]{10})$/', $_GET['openid_claimed_id'], $matches);
        if (!empty($matches[1])) {
            $_SESSION['steamid'] = $matches[1];
        }
    }
    header('Location: ' . STEAM_SITE_URL);
    exit;
}

function getSteamAppData($appID){
    // 1. Cek cache session
    if (isset($_SESSION['steam_cache'][$appID])) {
        return $_SESSION['steam_cache'][$appID];
    }

    // 2. Ambil dari API jika belum ada
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n",
            "timeout" => 3
        ]
    ];
    $context = stream_context_create($opts);
    $data = @file_get_contents("https://store.steampowered.com/api/appdetails?appids=$appID", false, $context);
    if ($data === false) return [];
    
    $json = json_decode($data, true);
    $result = $json[$appID]["data"] ?? [];
    
    // 3. Simpan ke session cache
    if (!empty($result)) {
        $_SESSION['steam_cache'][$appID] = $result;
    }
    
    return $result;
}

// ==========================================
// 2. AMBIL PLAYLIST
// ==========================================
$raw_playlist = [];

if (isset($_SESSION['steamid']) && STEAM_API_KEY !== 'MASUKKAN_STEAM_API_KEY_ANDA_DI_SINI') {
    $api_url = "https://api.steampowered.com/IPlayerService/GetOwnedGames/v0001/?" .
        http_build_query([
            'key' => STEAM_API_KEY,
            'steamid' => $_SESSION['steamid'],
            'format' => 'json',
            'include_appinfo' => true,
            'include_played_free_games' => true
        ]);

    $response = @file_get_contents($api_url);
    if ($response) {
        $user_games = json_decode($response, true);
        if (!empty($user_games['response']['games'])) {
            $games = $user_games['response']['games'];
            usort($games, function($a, $b){
                $aRecent = $a['playtime_2weeks'] ?? 0;
                $bRecent = $b['playtime_2weeks'] ?? 0;
                if ($aRecent == $bRecent) {
                    return ($b['playtime_forever'] ?? 0) <=> ($a['playtime_forever'] ?? 0);
                }
                return $bRecent <=> $aRecent;
            });
            foreach ($games as $g) {
                $raw_playlist[] = ["appid" => (string)$g['appid']];
            }
        }
    }
}

if (empty($raw_playlist)) {
    $raw_playlist = [
        ["appid" => "730"],     // CS2
        ["appid" => "244210"],   // Assetto Corsa
        ["appid" => "271590"],   // GTA V
        ["appid" => "359550"],  // Rainbow Six Siege
    ];
}

$steamid = isset($_GET['appid']) ? $_GET['appid'] : $raw_playlist[0]['appid'];

if (isset($_GET['run']) && $_GET['run'] == 'true') {
    $launch_url = "steam://run/" . $steamid;
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(["status" => "success", "url" => $launch_url]);
        exit;
    } else {
        header("Location: " . $launch_url);
        exit;
    }
} 

if (isset($_GET['get_img']) && $_GET['get_img'] == 'true') {
    $appData = getSteamAppData($steamid);
    $video_url = "";
    if (!empty($appData["movies"])) {
        $movie = $appData["movies"][0];
        $video_url = $movie["dash_h264"] ?? $movie["hls_h264"] ?? $movie["dash_av1"] ?? $movie["mp4"]["480"] ?? $movie["mp4"]["max"] ?? "";
    }
    
    $header_img = $appData["header_image"] ?? $appData["capsule_image"] ?? "";
    $capsule_img = $appData["capsule_image"] ?? $appData["header_image"] ?? "";
    $game_name = $appData["name"] ?? "Game " . $steamid;
    
    echo json_encode([
        "video" => $video_url, 
        "header_image" => $header_img, 
        "capsule_image" => $capsule_img, 
        "name" => $game_name
    ]);
    exit;
}

$games_list = [];
if (isset($_SESSION['steamid'])) {
    $font_styles = ["font-pixel", "font-teko", "font-retro"];
    foreach ($raw_playlist as $index => $item) {
        $appData = getSteamAppData($item['appid']);
        $games_list[] = [
            "appid" => $item['appid'],
            "title" => $appData["name"] ?? ("Game " . $item['appid']),
            "img"   => $appData["capsule_image"] ?? $appData["header_image"] ?? "",
            "font"  => $font_styles[$index % count($font_styles)]
        ];
    }
}

$current_index = 0;
foreach ($raw_playlist as $idx => $game) {
    if ($game['appid'] == $steamid) { $current_index = $idx; break; }
}
?>
<?php
$lang = $_GET['lang'] ?? 'id';

if ($lang === 'en') {
    $title = "Steam Famicom | Retro Launcher for Steam PC";
    $desc = "Transform your Steam library into a nostalgic retro console experience. Steam Famicom lets you browse and launch your Steam games like classic Famicom cartridges.";
    $ogDesc = "Bring the nostalgia of classic Famicom gaming to your Steam library. Browse your games as cartridges and launch them with a unique retro experience.";
    $twitterDesc = "Experience your Steam library with a classic retro Famicom-style launcher.";
    $htmlLang = "en";
} else {
    $title = "Steam Famicom | Retro Launcher untuk Steam PC";
    $desc = "Ubah pengalaman bermain game PC kamu. Steam Famicom adalah launcher retro yang membawa nostalgia konsol klasik ke koleksi game Steam kamu.";
    $ogDesc = "Mainkan game Steam-mu dengan gaya retro Famicom! Launcher cantik yang mengubah library PC menjadi pengalaman klasik.";
    $twitterDesc = "Mainkan game Steam-mu dengan gaya retro Famicom!";
    $htmlLang = "id";
}
?>
<!DOCTYPE html>
<html lang="<?= $htmlLang ?>">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= htmlspecialchars($title) ?></title>

<meta name="title" content="<?= htmlspecialchars($title) ?>">
<meta name="description" content="<?= htmlspecialchars($desc) ?>">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:url" content="https://retrosteam.gamer.gd/">
<meta property="og:title" content="<?= htmlspecialchars($title) ?>">
<meta property="og:description" content="<?= htmlspecialchars($ogDesc) ?>">
<meta property="og:image" content="https://retrosteam.gamer.gd/assets/preview.png">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="https://retrosteam.gamer.gd/">
<meta name="twitter:title" content="<?= htmlspecialchars($title) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($twitterDesc) ?>">
<meta name="twitter:image" content="https://retrosteam.gamer.gd/assets/preview.png">

<link rel="alternate" hreflang="id" href="https://retrosteam.gamer.gd/">
<link rel="alternate" hreflang="en" href="https://retrosteam.gamer.gd/?lang=en">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Teko:wght@600&display=swap" rel="stylesheet">
<script src="https://cdn.dashjs.org/latest/dash.all.min.js"></script>

  <style>
    * {
      -webkit-user-drag: none;
      user-select: none;
      -webkit-user-select: none;
      box-sizing: border-box;
    }

    body {
      margin: 0; padding: 0;
      background: linear-gradient(135deg, #15002b 0%, #050014 100%);
      display: flex; flex-direction: column; justify-content: flex-start;
      align-items: center; min-height: 100vh; font-family: sans-serif;
      overflow-x: hidden; perspective: 1200px;
      position: relative;
    }

    img, video {
      pointer-events: none;
    }

    .cubes-bg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; overflow: hidden; z-index: 0; pointer-events: none; }
    .cube { position: absolute; bottom: -100px; width: 40px; height: 40px; background: rgba(255, 255, 255, 0.04); border: 2px solid rgba(0, 225, 255, 0.15); box-shadow: 0 0 15px rgba(0, 225, 255, 0.15); animation: riseUp 12s linear infinite; border-radius: 4px; }
    @keyframes riseUp { 0% { transform: translateY(0) rotate(0deg); opacity: 0; } 10% { opacity: 0.7; } 90% { opacity: 0.4; } 100% { transform: translateY(-120vh) rotate(720deg); opacity: 0; } }

    #lightningCanvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 5; pointer-events: none; display: none; }

    /* === RAK BOX GAME === */
    .famicom-rack-section { z-index: 20; margin-top: 20px; display: flex; flex-direction: column; align-items: center; }
    .famicom-rack-container { border-bottom: 18px solid #8b0000; box-shadow: 0 20px 35px rgba(0, 0, 0, 0.9); background-color: #16181e; border-radius: 8px 8px 0 0; padding: 25px 20px 0 20px; }
    .famicom-rack { display: flex; align-items: flex-end; gap: 10px; max-width: 600px; overflow-x: auto; padding-bottom: 10px; }
    .game-box { width: 87px; height: 250px; background-color: #c2c000; border-radius: 4px 4px 0 0; display: flex; flex-direction: column; align-items: center; justify-content: space-between; cursor: pointer; padding: 8px 0; transition: transform 0.25s ease, filter 0.25s ease; box-shadow: inset -4px 0 8px rgba(0, 0, 0, 0.4), inset 2px 0 4px rgba(255, 255, 255, 0.3), -3px 0 6px rgba(0,0,0,0.5); position: relative; overflow: hidden; text-decoration: none; color: inherit; flex-shrink: 0; }
    .game-box:hover { transform: translateY(-16px); filter: brightness(1.15); }
    .box-art { position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%) rotate(-90deg); width: auto; height: auto; z-index: 0; pointer-events: none; }

    .pagination-container { margin-top: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 15px; }
    .page-btn { background-color: #8b0000; color: #fff; border: none; padding: 8px 16px; font-family: 'Press Start 2P', cursive; font-size: 10px; cursor: pointer; border-radius: 4px; transition: background 0.2s; }
    .page-btn:disabled { background-color: #333; cursor: not-allowed; color: #777; }
    .page-btn:not(:disabled):hover { background-color: #b30000; }
    .page-info { font-family: 'Press Start 2P', cursive; font-size: 11px; color: #c2c000; }

    /* === KASET FAMICOM === */
    .cartridge-zone { flex: 1; display: flex; justify-content: center; align-items: center; padding-top: 5px; position: relative; width: 100%; z-index: 10; margin-bottom: 20px; }
    .famicom-cartridge { 
      width: 700px; height: 440px; background-color: #dedc00; 
      border-radius: 24px 24px 12px 12px; 
      box-shadow: inset 0 15px 20px rgba(255,255,255,0.5), inset 0 -15px 20px rgba(0,0,0,0.3), 0 25px 50px rgba(0,0,0,0.5); 
      padding: 25px; position: relative; border: 4px solid #c2c000; 
      transform-style: preserve-3d; transition: transform 0.1s ease-out, box-shadow 0.2s ease, filter 0.2s; 
      z-index: 10; cursor: grab; 
      touch-action: none;
    }
    .famicom-cartridge:active { cursor: grabbing; }
    .famicom-cartridge::before { content: ''; position: absolute; top: 12px; left: 12px; right: 12px; bottom: 80px; border-radius: 16px; border: 3px solid #b2b000; pointer-events: none; box-shadow: inset 0 4px 6px rgba(0,0,0,0.15); }
    .famicom-cartridge::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 45px; background-color: transparent; border-left: 58px solid #050014; border-right: 58px solid #050014; border-top: 45px solid transparent; pointer-events: none; z-index: 5; }
    
    .famicom-cartridge.charging { 
      animation: magnetGlow 0.15s infinite alternate; 
    }
    @keyframes magnetGlow { 
      0% { box-shadow: 0 0 25px #00f0ff, 0 0 50px #ff00ff, inset 0 0 20px rgba(255,255,255,0.9); filter: brightness(1.1); } 
      100% { box-shadow: 0 0 50px #00f0ff, 0 0 90px #ff00ff, inset 0 0 35px rgba(255,255,255,1); filter: brightness(1.25); } 
    }

    .label-container { width: 100%; height: 295px; background-color: #e5e5e5; border-radius: 12px; border: 4px solid #222; display: flex; position: relative; overflow: hidden; box-shadow: inset 0 5px 10px rgba(0,0,0,0.2); pointer-events: none; }
    .label-left { width: 50%; height: 100%; padding: 12px 15px; display: flex; flex-direction: column; justify-content: space-between; border-right: 4px solid #222; background-color: #e5e5e5; }
    .brand-logo-container { display: flex; align-items: center; gap: 10px; }
    .brand-logo { font-family: 'Arial Black', Impact, sans-serif; font-size: 16px; font-weight: 900; color: #222; border: 3px solid #222; border-radius: 20px; padding: 1px 12px; text-transform: uppercase; }
    .brand-sub { font-size: 13px; font-weight: bold; color: #555; letter-spacing: 2px; }
    .jp-title { font-size: 18px; font-weight: 900; color: #222; margin-top: 3px; }
    
    .label-left-capsule-box { width: 100%; height: 125px; border-radius: 6px; margin: 4px 0; display: flex; align-items: center; justify-content: center; border: 2px solid #222; overflow: hidden; background-color: #000; }
    .capsule-image { width: 100%; height: 100%; object-fit: cover; }

    .label-footer { display: flex; align-items: flex-end; justify-content: space-between; }
    .green-badge { background-color: #008f43; color: #fff; font-weight: bold; font-size: 15px; padding: 3px 10px; border-radius: 2px; }
    .copyright-text { font-size: 9px; color: #222; text-align: right; font-weight: bold; line-height: 1.3; }

    .label-right-screen { width: 50%; height: 100%; background-color: #050505; position: relative; overflow: hidden; display: flex; flex-direction: column; }
    .steam-cover { width: 100%; height: 80%; object-fit: cover; pointer-events: none; background-color: #000; }
    .game-title-bar { width: 100%; height: 20%; background-color: #111; color: #fff; display: flex; align-items: center; justify-content: center; padding: 0 10px; font-weight: bold; font-size: 15px; text-align: center; border-top: 2px solid #222; text-transform: uppercase; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cartridge-feet { position: absolute; bottom: -35px; left: 58px; right: 58px; height: 40px; background-color: #c2c000; border-radius: 0 0 12px 12px; border: 4px solid #a3a100; border-top: none; z-index: 1; pointer-events: none; }

    /* === CONSOLE DOCK === */
    .footer-console-dock { width: 820px; height: 65px; background-color: #dcdad4; border-radius: 8px 8px 0 0; border: 4px solid #b5b3ad; border-bottom: none; box-shadow: 0 -10px 35px rgba(0,0,0,0.6); padding: 6px 12px 0 12px; z-index: 20; position: relative; display: flex; align-items: center; justify-content: space-between; }
    .footer-console-dock.dock-slam { animation: slamVibe 0.15s ease-out; }
    @keyframes slamVibe { 0% { transform: translateY(0); } 50% { transform: translateY(8px); } 100% { transform: translateY(0); } }

    .console-left-controls { display: flex; align-items: center; gap: 12px; z-index: 30; }
    .console-led { width: 10px; height: 10px; background-color: #ff3b30; border-radius: 50%; box-shadow: 0 0 6px #ff3b30; transition: background-color 0.2s, box-shadow 0.2s; }
    .footer-console-dock.active-led .console-led { background-color: #4cd964; box-shadow: 0 0 10px #4cd964, 0 0 20px #4cd964; }

    .console-login-btn { 
      display: flex; align-items: center; justify-content: center; gap: 4px; 
      background: linear-gradient(135deg, #1b2838 0%, #2a475e 100%); 
      color: #66c0f4; border: 2px solid #171a21; 
      width: 32px; height: 32px; padding: 0;
      border-radius: 50%; cursor: pointer; text-decoration: none; 
      transition: all 0.1s ease; 
      box-shadow: inset 0 2px 3px rgba(255,255,255,0.2), inset 0 -3px 4px rgba(0,0,0,0.6), 0 3px 5px rgba(0,0,0,0.4); 
    }
    .console-login-btn.logged-in { 
      background: linear-gradient(135deg, #00b050 0%, #006633 100%); 
      color: #fff; border-color: #00331a; 
    }
    .tutorial-btn {
      background: linear-gradient(135deg, #ff7b00 0%, #cc5500 100%);
      color: #fff; border-color: #662200; font-family: Arial, sans-serif; font-weight: bold; font-size: 16px;
    }
    .fullscreen-btn {
        background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
        color: #fff; 
        border-color: #0f172a;
        outline: none;
    }
    .fullscreen-btn:hover {
        filter: brightness(1.2);
    }

    .port-hole { flex: 1; height: 100%; background-color: #2a0508; border-radius: 4px 4px 0 0; border: 4px solid #661116; border-bottom: none; box-shadow: inset 0 8px 10px rgba(0,0,0,0.9); position: relative; overflow: hidden; margin-left: 12px; }
    .port-hole::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(0,240,255,0.4), rgba(255,0,255,0.4), transparent); transform: translateX(-100%); pointer-events: none; }
    .footer-console-dock.charging-port .port-hole::after { animation: portLaser 0.4s linear infinite; }
    @keyframes portLaser { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }

    @media (max-width: 768px) {
        .famicom-rack-container { padding: 10px 10px 0 10px; }
        .famicom-rack { gap: 5px; }
        .game-box { width: 32px; height: 110px; }
        .cartridge-zone { padding-top: 5px; }
        .famicom-cartridge { width: 350px; height: 230px; padding: 12px; border-radius: 12px 12px 6px 6px; border: 2px solid #c2c000; }
        .famicom-cartridge::before { top: 6px; left: 6px; right: 6px; bottom: 40px; border-radius: 8px; border: 2px solid #b2b000; }
        .famicom-cartridge::after { height: 25px; border-left: 30px solid #050014; border-right: 30px solid #050014; border-top: 25px solid transparent; }
        .label-container { height: 155px; border: 2px solid #222; border-radius: 6px; }
        .label-left { padding: 6px 8px; border-right: 2px solid #222; }
        .brand-logo { font-size: 9px; border: 1.5px solid #222; padding: 1px 6px; border-radius: 10px; }
        .brand-sub { font-size: 8px; letter-spacing: 1px; }
        .jp-title { font-size: 9px; margin-top: 2px; }
        .label-left-capsule-box { height: 65px; margin: 4px 0; border: 1px solid #222; }
        .green-badge { font-size: 8px; padding: 2px 6px; }
        .copyright-text { font-size: 5px; }
        .game-title-bar { font-size: 9px; padding: 0 4px; border-top: 1px solid #222; }
        .cartridge-feet { bottom: -20px; left: 30px; right: 30px; height: 22px; border: 2px solid #a3a100; border-top: none; }
        .footer-console-dock { width: 100%; max-width: 380px; height: 45px; padding: 4px 6px 0 6px; border: 2px solid #b5b3ad; border-bottom: none; }
        .console-login-btn { width: 26px; height: 26px; }
        .port-hole { border: 2px solid #661116; border-bottom: none; margin-left: 6px; }
        .console-led { width: 6px; height: 6px; }
    }
  </style>
</head>
<body>

    <div class="cubes-bg" id="cubes-container"></div>
    <canvas id="lightningCanvas"></canvas>

    <?php if (isset($_SESSION['steamid'])): ?>
    <div class="famicom-rack-section">
      <div class="famicom-rack-container">
        <div class="famicom-rack" id="rack"></div>
      </div>
	  
      <div class="pagination-container">
        <button class="page-btn" id="prevBtn" onclick="changePage(-1)">PREV</button>
        <span class="page-info" id="pageInfo">PAGE 1/1</span>
        <button class="page-btn" id="nextBtn" onclick="changePage(1)">NEXT</button>
      </div>
    </div>
    <?php endif; ?>

    <!-- KASET FAMICOM -->
    <div class="cartridge-zone">
        <div class="famicom-cartridge" id="kaset-body">
            <div class="label-container">
                <div class="label-left">
                    <div>
                        <div class="brand-logo-container">
                            <div class="brand-logo">Steam®</div>
                            <div class="brand-sub">VALVE</div>
                        </div>
                        <div class="jp-title">STEAM DECK SYSTEM</div>
                    </div>
                    
                    <div class="label-left-capsule-box">
                        <img class="capsule-image" id="left-capsule-img" src="" alt="Capsule Art">
                    </div>
                    
                    <div class="label-footer">
                        <div class="green-badge" id="badge-steamid"><?=$steamid?></div>
                        <div class="copyright-text">
                            © VALVE CORP.<br>
                            STEAM API
                        </div>
                    </div>
                </div>
                
                <div class="label-right-screen">
                    <img class="steam-cover" id="game-cover-img" src="" style="display: block;" />
                    <video class="steam-cover" id="game-cover" loop muted playsinline style="display: none;"></video>
                    <div class="game-title-bar" id="game-title">Loading...</div>
                </div>
            </div>
            <div class="cartridge-feet"></div>
        </div>
    </div>
<a href="http://s01.flagcounter.com/more/CQQ"><img src="https://s01.flagcounter.com/count/CQQ/bg_FFFFFF/txt_000000/border_CCCCCC/columns_6/maxflags_6/viewers_3/labels_1/pageviews_0/flags_0/percent_0/" alt="Flag Counter" border="0"></a>
    <!-- DOCK CONSOLE -->
    <div class="footer-console-dock" id="console-port">
        <div class="console-left-controls">
            <div class="console-led"></div>
            <?php if (isset($_SESSION['steamid'])): ?>
                <a href="?logout=true" class="console-login-btn logged-in" title="Logout dari Steam">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.11 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/>
                    </svg>
                </a>
            <?php else: ?>
                <a href="login.php" class="console-login-btn" title="Login via Steam">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/83/Steam_icon_logo.svg/960px-Steam_icon_logo.svg.png" style="width:100%;">
                </a>
            <?php endif; ?>

            <button id="btn-fullscreen" class="console-login-btn fullscreen-btn" title="Layar Penuh">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
                </svg>
            </button>

            <a href="tutorial.php" target="_BLANK" class="console-login-btn tutorial-btn" title="Halaman Tutorial">?</a>
        </div>
        <div class="port-hole"></div>
    </div>

    <script>
    const playlist = <?php echo json_encode($raw_playlist); ?>;
    const gamesData = <?php echo json_encode($games_list); ?>;
    let currentIndex = <?php echo $current_index; ?>;
    let isDocked = false; 
    let currentTravelDistance = 0; 

    let currentPage = 1;
    const itemsPerPage = 6;

    function renderRack() {
      const rack = document.getElementById("rack");
      if (!rack) return;
      rack.innerHTML = "";

      const start = (currentPage - 1) * itemsPerPage;
      const end = start + itemsPerPage;
      const currentGames = gamesData.slice(start, end);

      currentGames.forEach(game => {
        const box = document.createElement("a");
        box.className = "game-box";
        box.href = "javascript:void(0);";
        box.onclick = () => selectGameByAppId(game.appid);
        box.innerHTML = `<img class="box-art" src="${game.img}" alt="">`;
        rack.appendChild(box);
      });

      const totalPages = Math.ceil(gamesData.length / itemsPerPage) || 1;
      document.getElementById("pageInfo").innerText = `PAGE ${currentPage}/${totalPages}`;
      document.getElementById("prevBtn").disabled = currentPage === 1;
      document.getElementById("nextBtn").disabled = currentPage === totalPages;
    }

    function changePage(direction) {
      currentPage += direction;
      renderRack();
    }

    function selectGameByAppId(appid) {
      const index = playlist.findIndex(g => g.appid === appid);
      if (index !== -1) {
        currentIndex = index;
        updateActiveGameUI(playlist[currentIndex]);
      }
    }

    renderRack();

    const videoElement = document.getElementById('game-cover');
    const imgElement = document.getElementById('game-cover-img');
    const player = dashjs.MediaPlayer().create();

    const cubesContainer = document.getElementById('cubes-container');
    for (let i = 0; i < 20; i++) {
        const cube = document.createElement('div');
        cube.classList.add('cube');
        const size = Math.floor(Math.random() * 50) + 15;
        cube.style.width = cube.style.height = `${size}px`;
        cube.style.left = `${Math.random() * 100}%`;
        cube.style.animationDelay = `${Math.random() * 12}s`;
        cube.style.animationDuration = `${Math.random() * 8 + 8}s`;
        const hue = Math.random() > 0.5 ? 190 : 290;
        cube.style.borderColor = `rgba(${hue === 190 ? '0, 225, 255' : '230, 0, 255'}, 0.2)`;
        cube.style.boxShadow = `0 0 15px rgba(${hue === 190 ? '0, 225, 255' : '230, 0, 255'}, 0.1)`;
        cubesContainer.appendChild(cube);
    }

    document.addEventListener('contextmenu', e => e.preventDefault(), false);

    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    let electricalNode = null;
    let mainGain = null;

    function startElectricitySound() {
        if (audioCtx.state === 'suspended') audioCtx.resume();
        if (electricalNode) return;
        electricalNode = audioCtx.createOscillator();
        mainGain = audioCtx.createGain();
        electricalNode.type = 'sawtooth';
        electricalNode.frequency.setValueAtTime(55, audioCtx.currentTime); 
        setInterval(() => { if(electricalNode) electricalNode.frequency.setValueAtTime(Math.random() * 40 + 50, audioCtx.currentTime); }, 50);
        mainGain.gain.setValueAtTime(0.015, audioCtx.currentTime);
        electricalNode.connect(mainGain); mainGain.connect(audioCtx.destination);
        electricalNode.start();
    }

    function stopElectricitySound() {
        if (electricalNode) { try { electricalNode.stop(); } catch(e){} electricalNode = null; }
    }

    function playRetroSound(type) {
        if (audioCtx.state === 'suspended') audioCtx.resume();
        const osc = audioCtx.createOscillator();
        const soundGain = audioCtx.createGain();
        osc.connect(soundGain); soundGain.connect(audioCtx.destination);
        const now = audioCtx.currentTime;

        if (type === 'run') {
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(120, now);
            osc.frequency.setValueAtTime(600, now + 0.08);
            soundGain.gain.setValueAtTime(0.06, now);
            soundGain.gain.linearRampToValueAtTime(0, now + 0.3);
            osc.start(now); osc.stop(now + 0.3);
        } else if (type === 'swap') {
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(400, now);
            osc.frequency.linearRampToValueAtTime(200, now + 0.15);
            soundGain.gain.setValueAtTime(0.03, now);
            soundGain.gain.linearRampToValueAtTime(0, now + 0.15);
            osc.start(now); osc.stop(now + 0.15);
        }
    }

    const cartridge = document.getElementById('kaset-body');
    const consolePort = document.getElementById('console-port');
    const canvas = document.getElementById('lightningCanvas');
    const ctxCanvas = canvas.getContext('2d');

    function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    let startX = 0, startY = 0;
    let currentY = 0;
    let isDragging = false;
    let hasTriggered = false;

    function drawLightningBolt(x1, y1, x2, y2, segments, branch) {
        ctxCanvas.beginPath(); ctxCanvas.moveTo(x1, y1);
        let curX = x1, curY = y1;
        for (let i = 0; i < segments; i++) {
            let t = i / segments;
            let targetX = x1 + (x2 - x1) * t; let targetY = y1 + (y2 - y1) * t;
            let jitter = (Math.random() - 0.5) * 25;
            curX = targetX + (y2 - y1 !== 0 ? jitter : 0);
            curY = targetY + (x2 - x1 !== 0 ? jitter : 0);
            ctxCanvas.lineTo(curX, curY);
            if (branch && Math.random() < 0.15 && i < segments - 1) {
                drawLightningBolt(curX, curY, curX + (Math.random() - 0.5) * 60, curY + (Math.random() - 0.5) * 60, 3, false);
            }
        }
        ctxCanvas.lineTo(x2, y2);
        ctxCanvas.strokeStyle = 'rgba(0, 191, 255, ' + (0.4 + Math.random() * 0.6) + ')';
        ctxCanvas.lineWidth = branch ? 2.5 : 1; ctxCanvas.shadowBlur = 12; ctxCanvas.shadowColor = '#00bfff';
        ctxCanvas.stroke();
    }

    function animateElectricity() {
        if (!isDragging || hasTriggered) { canvas.style.display = 'none'; return; }
        ctxCanvas.clearRect(0, 0, canvas.width, canvas.height);
        canvas.style.display = 'block';

        const cardRect = cartridge.getBoundingClientRect();
        const slotTop = window.innerHeight - 40;
        const cardBottomY = cardRect.bottom - 10;

        drawLightningBolt(window.innerWidth/2 - 80, slotTop, cardRect.left + 30, cardBottomY, 6, true);
        drawLightningBolt(window.innerWidth/2 + 80, slotTop, cardRect.right - 30, cardBottomY, 6, true);
        
        requestAnimationFrame(animateElectricity);
    }

    // ==========================================
    // SISTEM DRAG TERINTEGRASI (POINTER EVENTS)
    // ==========================================
    cartridge.addEventListener('pointerdown', e => {
        if (e.button !== undefined && e.button !== 0) return;
        
        isDragging = true;
        hasTriggered = false;
        cartridge.setPointerCapture(e.pointerId);

        if (isDocked) {
            isDocked = false; 
            videoElement.pause();
            videoElement.style.display = 'none';
            imgElement.style.display = 'block';
            consolePort.classList.remove('active-led');
            startY = e.clientY - currentTravelDistance; 
            startX = e.clientX;
        } else {
            startX = e.clientX;
            startY = e.clientY;
        }

        cartridge.classList.add('charging');
        consolePort.classList.add('charging-port');
        cartridge.style.transition = 'none'; 
        currentY = 0;
        startElectricitySound();
        animateElectricity();
    });

    cartridge.addEventListener('pointermove', e => {
        if (!isDragging) return;
        let diffX = e.clientX - startX;
        let diffY = e.clientY - startY;
        currentY = diffY;

        let shiftX = Math.min(Math.max(diffX, -200), 200);
        let shiftY = Math.min(Math.max(diffY, -350), window.innerHeight);
        
        let tiltX = Math.min(Math.max(diffY / 10, -20), 20);
        let tiltY = Math.min(Math.max(-diffX / 10, -15), 15);

        cartridge.style.transform = `rotateX(${tiltX}deg) rotateY(${tiltY}deg) translateY(${shiftY}px) translateX(${shiftX}px)`;
    });

    const handleRelease = e => {
        if (!isDragging) return;
        isDragging = false;
        hasTriggered = true;
        try { cartridge.releasePointerCapture(e.pointerId); } catch(err){}

        cartridge.classList.remove('charging');
        consolePort.classList.remove('charging-port');
        stopElectricitySound();
        ctxCanvas.clearRect(0, 0, canvas.width, canvas.height);
        canvas.style.display = 'none';

        let totalDragX = e.clientX - startX;
        let totalDragY = e.clientY - startY;

        if (Math.abs(totalDragX) > 120 && Math.abs(totalDragY) < 100) {
            cartridge.style.transition = 'transform 0.2s ease-in, opacity 0.2s';
            if (totalDragX > 0) {
                cartridge.style.transform = 'translateX(100vw) rotateY(30deg)';
                currentIndex = (currentIndex - 1 + playlist.length) % playlist.length;
            } else {
                cartridge.style.transform = 'translateX(-100vw) rotateY(-30deg)';
                currentIndex = (currentIndex + 1) % playlist.length;
            }
            playRetroSound('swap');
            videoElement.pause();
            videoElement.style.display = 'none';
            imgElement.style.display = 'block';

            setTimeout(() => {
                try {
                    updateActiveGameUI(playlist[currentIndex]);
                } catch (err) {
                    console.error("Gagal memuat data game:", err);
                }
                cartridge.style.transition = 'none';
                cartridge.style.transform = totalDragX > 0 ? 'translateX(-100vw)' : 'translateX(100vw)';
                setTimeout(() => {
                    cartridge.style.transition = 'transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                    cartridge.style.transform = 'rotateX(0deg) rotateY(0deg) translateY(0px) translateX(0px)';
                    setTimeout(() => { cartridge.style.transition = 'transform 0.1s ease-out'; }, 400);
                }, 50);
            }, 200);

        } else if (totalDragY > window.innerHeight * 0.18) {
            cartridge.style.transition = 'transform 0.25s cubic-bezier(0.6, 0, 0.4, 1)';
            const portRect = consolePort.getBoundingClientRect();
            const kasetRect = cartridge.getBoundingClientRect();
            currentTravelDistance = portRect.top - kasetRect.top + 20;

            cartridge.style.transform = `rotateX(12deg) translateY(${currentTravelDistance}px) scale(0.92)`;
            isDocked = true; 
            
            setTimeout(() => {
                playRetroSound('run');
                consolePort.classList.add('dock-slam', 'active-led');
                
                // MAINkan VIDEO HANYA SAAT KASET DIDEKATKAN / DICOLOK KE KONSOL
                const currentData = gameCache[playlist[currentIndex].appid];
                if (currentData && currentData.video && currentData.video.trim() !== "") {
                    imgElement.style.display = 'none';
                    videoElement.style.display = 'block';
                    videoElement.muted = true;
                    videoElement.currentTime = 0;
                    videoElement.play().catch(err => { 
                        console.log("Autoplay error:", err);
                        videoElement.style.display = 'none';
                        imgElement.style.display = 'block';
                    });
                }
                
                fetch(`?appid=${playlist[currentIndex].appid}&run=true`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(data => { if (data.url) window.location.href = data.url; })
                .catch(err => { window.location.href = `steam://run/${playlist[currentIndex].appid}`; });

                setTimeout(() => { consolePort.classList.remove('dock-slam'); }, 150);
            }, 220);

        } else {
            isDocked = false;
            videoElement.pause();
            videoElement.style.display = 'none';
            imgElement.style.display = 'block';
            cartridge.style.transition = 'transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            cartridge.style.transform = 'rotateX(0deg) rotateY(0deg) translateY(0px) translateX(0px)';
            setTimeout(() => { cartridge.style.transition = 'transform 0.1s ease-out'; }, 400);
        }
    };

    cartridge.addEventListener('pointerup', handleRelease);
    cartridge.addEventListener('pointercancel', handleRelease);

    // ==========================================
    // CACHING LOCALSTORAGE BROWSER
    // ==========================================
    const gameCache = JSON.parse(localStorage.getItem('steamGameCache')) || {};

    function updateActiveGameUI(game) {
        const gameTitleEl = document.getElementById('game-title');
        const badgeSteamIdEl = document.getElementById('badge-steamid');
        const leftCapsuleImg = document.getElementById('left-capsule-img');

        badgeSteamIdEl.innerText = game.appid;
        gameTitleEl.innerText = "Loading...";

        const applyDataToUI = (data) => {
            if (data.name) gameTitleEl.innerText = data.name;
            if (data.capsule_image) leftCapsuleImg.src = data.capsule_image;

            // Selalu tampilkan gambar cover terlebih dahulu saat kaset belum dicolok
            if (data.header_image) {
                imgElement.src = data.header_image;
            }
            imgElement.style.display = "block";
            videoElement.style.display = "none";

            const fallbackToImage = () => {
                videoElement.style.display = "none";
                imgElement.style.display = "block";
            };

            if (data.video && data.video.trim() !== "") {
                player.off(dashjs.MediaPlayer.events.ERROR);
                player.on(dashjs.MediaPlayer.events.ERROR, fallbackToImage);
                videoElement.onerror = fallbackToImage;

                try {
                    // Set parameter autoplay ke false agar tidak langsung jalan
                    player.initialize(videoElement, data.video, false);
                } catch (e) {
                    fallbackToImage();
                }
            }
        };

        if (gameCache[game.appid]) {
            applyDataToUI(gameCache[game.appid]);
            window.history.pushState({}, '', `?appid=${game.appid}`);
            return;
        }

        fetch(`?appid=${game.appid}&get_img=true`)
            .then(res => res.json())
            .then(data => {
                gameCache[game.appid] = data;
                localStorage.setItem('steamGameCache', JSON.stringify(gameCache));
                applyDataToUI(data);
            })
            .catch(err => { console.log("Gagal memuat detail game."); });

        window.history.pushState({}, '', `?appid=${game.appid}`);
    }

    updateActiveGameUI(playlist[currentIndex]);

    // ==========================================
    // FITUR FULLSCREEN
    // ==========================================
    const fullscreenBtn = document.getElementById('btn-fullscreen');
    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', () => {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    console.log(`Gagal masuk ke mode fullscreen: ${err.message}`);
                });
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        });
    }
    </script>
	
</body>
</html>