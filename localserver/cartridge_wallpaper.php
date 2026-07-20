<?php
error_reporting(0);

$steamid = isset($_GET['appid']) ? $_GET['appid'] : '3405690';
$appID = $steamid;

function steamImage($appID){
    $data = file_get_contents("https://store.steampowered.com/api/appdetails?appids=$appID");
    $json = json_decode($data, true);
    return $json[$appID]["data"]["header_image"] ?? "";
}
 
$game_title = "Unknown Game";
$api_url = "https://store.steampowered.com/api/appdetails?appids=" . $steamid;
$response = file_get_contents($api_url);
if ($response) {
    $data = json_decode($response, true);
    if (isset($data[$steamid]['success']) && $data[$steamid]['success'] == true) {
        $game_title = $data[$steamid]['data']['name'];
    }
}

$game_image = steamImage($steamid);
if (empty($game_image)) {
    $game_image = "https://images.weserv.nl/?url=cdn.cloudflare.steamstatic.com/steam/apps/{$steamid}/header.jpg";
}

$server_ip = $_SERVER['SERVER_ADDR'] ?? $_SERVER['HTTP_HOST'] ?? '192.168.1.69';
$trigger_url = "http://" . $server_ip . "/index.php?appid=" . $steamid; 
$qrcode_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($trigger_url);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($game_title); ?> - Steam Retro Cartridge</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Courier New', Courier, monospace;
        }

        /* Tampilan Layar (Screen Preview) */
        body {
            background-color: #121212;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* Ukuran disesuaikan presisi Sampoerna Mild (90mm x 55mm) */
        .cartridge {
            background-color: #f7df1e;
            width: 90mm;
            height: 55mm;
            border-radius: 4mm;
            box-shadow: inset 0 1mm 2mm rgba(255,255,255,0.6), 
                        inset 0 -1.5mm 3mm rgba(0,0,0,0.4),
                        0 2mm 5mm rgba(0,0,0,0.4);
            padding: 4.5mm 3.5mm;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            overflow: hidden;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Lekukan plastik bawah */
        .cartridge::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 8%;
            width: 84%;
            height: 5mm;
            background-color: #e3cc19;
            border-radius: 2mm 2mm 0 0;
            box-shadow: inset 0 0.5mm 1.5mm rgba(0,0,0,0.3);
        }

        /* Container Label Tengah */
        .label-container {
            background-color: #f0f0f0;
            border: 0.8mm solid #1a1a1a;
            border-radius: 1.2mm;
            height: 39mm;
            display: flex;
            overflow: hidden;
            box-shadow: 0 1mm 2mm rgba(0,0,0,0.5);
            z-index: 2;
        }

        /* SISI KIRI LABEL */
        .label-left {
            width: 48%;
            padding: 2.5mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background-color: #f0f0f0;
        }

        /* Teks Brand Atas */
        .brand-header {
            border-bottom: 0.4mm solid #333;
            padding-bottom: 0.8mm;
            margin-bottom: 1.5mm;
        }
        .brand-title {
            font-size: 11pt;
            font-weight: bold;
            color: #111;
            letter-spacing: 0.5mm;
            text-transform: uppercase;
        }
        .brand-subtitle {
            font-size: 4.5pt;
            color: #555;
            font-weight: bold;
            margin-top: 0.3mm;
        }

        /* KOTAK HITAM KIRI (Logo Steam & QR) */
        .black-box-left {
            background-color: #0c1015;
            border-radius: 0.8mm;
            height: 19mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 2mm;
            box-shadow: inset 0 0 1.5mm rgba(0,0,0,0.9);
            margin-bottom: 1.5mm;
        }

        .steam-logo-container {
            width: 40%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .steam-logo {
            width: 100%;
            max-width: 9.5mm;
            height: auto;
            object-fit: contain;
        }

        .qr-code-container {
            width: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .qr-code {
            width: 100%;
            height: auto;
            max-width: 13mm;
            max-height: 13mm;
            border: 0.5mm solid #fff;
            background-color: #fff;
            padding: 0.3mm;
        }

        /* Footer Sisi Kiri */
        .label-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 4.5pt;
            color: #222;
            font-weight: bold;
        }

        .badge-green {
            background-color: #009c4f;
            color: white;
            padding: 0.6mm 1.8mm;
            font-size: 6.5pt;
            font-weight: bold;
            border-radius: 0.4mm;
        }

        .copyright-text {
            text-align: right;
            line-height: 1.2;
        }

        /* SISI KANAN LABEL (Kotak Banner Game) */
        .label-right-black {
            width: 52%;
            background-color: #05070a;
            border-left: 0.8mm solid #1a1a1a;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .game-banner {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.95;
        }

        .game-title-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,1) 0%, rgba(0,0,0,0.7) 70%, transparent 100%);
            color: #fff;
            padding: 2.5mm 2mm 2mm 2mm;
            font-size: 7.5pt;
            font-weight: bold;
            text-align: center;
            text-shadow: 0.5mm 0.5mm 1mm rgba(0,0,0,1);
            letter-spacing: 0.2mm;
            text-transform: uppercase;
        }

        /* ------------------------------------------------------------- */
        /* PENGATURAN CETAK (PRINT) KERTAS A4 LANDSCAPE                 */
        /* ------------------------------------------------------------- */
        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm;
            }
            body {
                background: none !important;
                min-height: auto;
                padding: 0;
                display: block;
            }
            .cartridge {
                box-shadow: none !important; /* Dihilangkan agar tepi gunting terlihat bersih */
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <div class="cartridge">
        <div class="label-container">
            
            <!-- Sisi Kiri -->
            <div class="label-left">
                <div class="brand-header">
                    <div class="brand-title">STEAM</div>
                    <div class="brand-subtitle">VALVE COMPUTING SYSTEM™</div>
                </div>

                <div class="black-box-left">
                    <div class="steam-logo-container">
                        <img class="steam-logo" src="https://cdn2.steamgriddb.com/icon/49265d2447bc3bbfe9e76306ce40a31f.ico" alt="Steam Logo">
                    </div>
                    
                    <div class="qr-code-container">
                        <img class="qr-code" src="<?php echo $qrcode_api_url; ?>" alt="QR Code">
                    </div>
                </div>

                <div class="label-footer">
                    <div class="badge-green">APP-<?php echo substr($steamid, 0, 5); ?></div>
                    <div class="copyright-text">
                        © <?php echo date('Y'); ?> Valve Corp.<br>
                        MADE IN ONLINE
                    </div>
                </div>
            </div>

            <!-- Sisi Kanan -->
            <div class="label-right-black">
                <img class="game-banner" src="<?php echo $game_image; ?>" alt="<?php echo htmlspecialchars($game_title); ?>">
                <div class="game-title-overlay">
                    <?php echo htmlspecialchars($game_title); ?>
                </div>
            </div>

        </div>
    </div>

</body>
</html>