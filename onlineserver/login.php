<?php

require 'openid.php';

try {

    $openid = new LightOpenID($_SERVER['HTTP_HOST']);

    if (!$openid->mode) {

        $openid->identity = 'https://steamcommunity.com/openid';

        // WAJIB sama dengan domain website
        $openid->realm = 'https://retrosteam.gamer.gd/';

        // callback
        $openid->returnUrl = 'https://retrosteam.gamer.gd/callback.php';

        header('Location: ' . $openid->authUrl());
        exit;
    }

} catch (Exception $e) {

    die($e->getMessage());

}