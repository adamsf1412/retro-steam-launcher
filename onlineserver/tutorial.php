<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>Steam Famicom Tutorial</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com">

<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
}

body{

font-family:Poppins,sans-serif;

background:
radial-gradient(circle at top,#38105f,#14031f 70%);

color:white;

min-height:100vh;

}

header{

padding:50px 20px 30px;

text-align:center;

}

h1{

font-family:"Press Start 2P";

font-size:48px;

color:#ffd400;

text-shadow:
0 0 15px orange,
0 0 30px orange;

margin-bottom:20px;

}

.subtitle{

color:#d8b8ff;

font-size:18px;

}

.tabs{

display:flex;

justify-content:center;

gap:15px;

margin:35px 0;

}

.tab{

border:none;

padding:15px 35px;

cursor:pointer;

font-size:16px;

font-weight:bold;

border-radius:50px;

background:#2f1747;

color:white;

transition:.3s;

}

.tab:hover{

transform:translateY(-3px);

}

.tab.active{

background:#ffd400;

color:#111;

box-shadow:
0 0 25px #ffd400;

}

.viewer{

width:min(1200px,95%);

margin:auto;

padding-bottom:70px;

}

.image{

display:none;

animation:fade .4s;

}

.image.active{

display:block;

}

.image img{

width:100%;

border-radius:18px;

border:4px solid #7448ff;

box-shadow:
0 0 40px rgba(137,89,255,.5);

}

@keyframes fade{

from{

opacity:0;
transform:translateY(20px);

}

to{

opacity:1;
transform:translateY(0);

}

}

footer{

text-align:center;

padding:30px;

color:#aaa;

}

</style>

</head>

<body>

<header>

<h1>STEAM FAMICOM</h1>

<div class="subtitle">
Tutorial Guide
</div>

</header>

<div class="tabs">

<button class="tab active"
onclick="showTab('en',this)">
English
</button>

<button class="tab"
onclick="showTab('id',this)">
Indonesia
</button>

</div>

<div class="viewer">

<div class="image active" id="en">

<img src="tutoren.png">

</div>

<div class="image" id="id">

<img src="tutorid.png">

</div>

</div>

<footer>

Steam Famicom Launcher Tutorial

</footer>

<script>

function showTab(id,btn){

document.querySelectorAll(".image").forEach(e=>e.classList.remove("active"));

document.querySelectorAll(".tab").forEach(e=>e.classList.remove("active"));

document.getElementById(id).classList.add("active");

btn.classList.add("active");

}

</script>
</body>
</html>