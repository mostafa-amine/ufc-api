<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Unofficial UFC API · Terms of use</title>
<meta name="description" content="Terms for using the hosted Unofficial UFC API: free for apps, analysis, research and learning; not for gambling.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo+Narrow:wght@500;700&family=Archivo:wght@400;500;600;800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{--paper:#F6F5F2; --ink:#101010; --soft:#3A3732; --muted:#55524C; --red:#C41E26}
  *{box-sizing:border-box}
  body{margin:0; background:var(--paper); color:var(--ink); font-family:'Archivo',sans-serif}
  a{color:var(--ink)} a:hover{color:var(--red)}
  main{max-width:760px; margin:0 auto; padding:40px 24px 80px}
  @media(max-width:520px){main{padding:24px 16px 64px}}
  h1{margin:0; font-family:'Archivo Narrow',sans-serif; font-weight:700; font-size:clamp(40px,6vw,64px); line-height:.95; text-transform:uppercase}
  h2{margin:48px 0 0; font-size:22px; font-weight:700}
  p,li{font-size:17px; line-height:1.6; color:var(--soft)}
  p{margin:12px 0 0}
  ul{margin:12px 0 0; padding-left:22px}
  .rule{margin-top:28px; padding:20px 22px; background:var(--ink); color:var(--paper)}
  .rule p{color:var(--paper); margin:0}
  .rule strong{color:#fff}
  .updated{font-family:'IBM Plex Mono',monospace; font-size:13px; color:var(--muted); margin-top:16px}
</style>
</head>
<body>

@include('partials.site-nav')

<main>
  <h1>Terms of use</h1>
  <div class="updated">Last updated 20 November 2026</div>

  <div class="rule">
    <p><strong>Not for gambling.</strong> You may not use this API, or any data you get from it, for betting or gambling of any kind.</p>
  </div>

  <h2>What you can use it for</h2>
  <p>Anything else that is legal, free of charge. For example:</p>
  <ul>
    <li>Apps, websites and fan projects</li>
    <li>Fight analysis, breakdowns and journalism</li>
    <li>Research and teaching</li>
    <li>Learning to code with real sports data</li>
  </ul>

  <h2>What you can't use it for</h2>
  <ul>
    <li>Sportsbooks, betting sites and betting apps</li>
    <li>Setting or comparing odds</li>
    <li>Betting tips, picks or "best bet" services, paid or free</li>
    <li>Betting models and prediction markets</li>
    <li>Fantasy contests where people pay to enter or win money</li>
  </ul>

  <h2>If a key is used for gambling</h2>
  <p>Getting or using a key means you accept these terms. We may refuse a key, or switch one off, if we believe it is used for gambling. We don't have to warn you first.</p>

  <h2>The data</h2>
  <p>The data comes from ufcstats.com and is provided as is, with no promise that it is complete, correct or always available. This project is not affiliated with the UFC or Zuffa LLC.</p>

  <h2>The code</h2>
  <p>These terms cover the hosted API at this address. The source code on <a href="https://github.com/mostafa-amine/ufc-api" target="_blank" rel="noopener">GitHub</a> keeps its own MIT license.</p>
</main>

</body>
</html>
