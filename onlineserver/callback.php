<?php

session_start();

require 'openid.php';

try{

$openid=new LightOpenID($_SERVER['HTTP_HOST']);

if($openid->validate()){

$id=$openid->identity;

preg_match('/(\d+)$/',$id,$matches);

$_SESSION['steamid']=$matches[1];

header("Location: loading.html");

exit;

}

}catch(Exception $e){

die($e->getMessage());

}