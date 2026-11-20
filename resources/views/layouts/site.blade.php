<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} · Unofficial UFC API</title>
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo+Narrow:wght@500;700&family=Archivo:wght@400;500;600;800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{--paper:#F6F5F2; --ink:#101010; --muted:#55524C; --soft:#3A3732; --rule:#D6D2CA; --track:#E6E3DD; --red:#C41E26; --blue:#1F4FD1; --ok:#1E6B34}
  *{box-sizing:border-box}
  body{margin:0; background:var(--paper); color:var(--ink); font-family:'Archivo',sans-serif}
  a{color:var(--ink)} a:hover{color:var(--red)}
  button,input{font:inherit}
  .page{max-width:1000px; margin:0 auto; padding:32px 24px 96px}
  @media(max-width:520px){.page{padding:16px 16px 72px}}
  h1{margin:0; font-family:'Archivo Narrow',sans-serif; font-weight:700; font-size:clamp(40px,6vw,64px); line-height:.95; text-transform:uppercase}
  h2{margin:0; font-family:'Archivo Narrow',sans-serif; font-weight:700; font-size:28px; text-transform:uppercase}
  .lede{margin:12px 0 0; font-size:17px; line-height:1.55; color:var(--soft)}
  label{display:block; font-weight:600; font-size:15px}
  .field{margin-top:20px}
  .input{display:block; width:100%; min-height:48px; margin-top:6px; padding:0 14px; border:2px solid var(--ink); background:#fff; color:var(--ink); font-size:16px; border-radius:0}
  .input:focus{outline:3px solid var(--blue); outline-offset:2px}
  .input[aria-invalid="true"]{border-color:var(--red)}
  .error{margin-top:6px; color:var(--red); font-size:14px; font-weight:600}
  .check{display:flex; gap:12px; align-items:flex-start; margin-top:20px; font-weight:400; font-size:15px; line-height:1.5; color:var(--soft)}
  .check input{width:22px; height:22px; margin:1px 0 0; accent-color:var(--red); flex:0 0 auto}
  .btn{display:inline-flex; align-items:center; justify-content:center; min-height:48px; padding:0 24px; border:2px solid var(--ink); background:var(--ink); color:var(--paper); font-weight:600; font-size:16px; text-decoration:none; cursor:pointer}
  .btn:hover{background:var(--red); border-color:var(--red); color:#fff}
  .btn.red{background:var(--red); border-color:var(--red); color:#fff} .btn.red:hover{background:var(--ink); border-color:var(--ink)}
  .btn.ghost{background:transparent; color:var(--ink)} .btn.ghost:hover{background:var(--ink); color:var(--paper); border-color:var(--ink)}
  .btn:focus-visible,.link-btn:focus-visible{outline:3px solid var(--blue); outline-offset:2px}
  .link-btn{background:none; border:0; padding:0; min-height:44px; color:var(--ink); text-decoration:underline; cursor:pointer; font-size:15px}
  .link-btn:hover{color:var(--red)}
  .mono{font-family:'IBM Plex Mono',monospace}
</style>
@stack('head')
</head>
<body>
@include('partials.site-nav')
<main class="page">
@yield('content')
</main>
@stack('scripts')
</body>
</html>
