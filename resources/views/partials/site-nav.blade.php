{{-- Top nav shared by every public page, so they read as one site. --}}
<style>
  .site-nav{max-width:1240px; margin:0 auto; padding:22px 24px; display:flex; flex-wrap:wrap; gap:16px; justify-content:space-between; align-items:center; font-family:'Archivo',sans-serif}
  .site-nav a{color:#101010} .site-nav a:hover{color:#C41E26}
  .site-nav .brand{font-weight:800; font-size:20px; text-decoration:none}
  .site-nav .brand span{font-weight:400; font-size:14px; color:#55524C}
  .site-nav .nav-links{display:flex; gap:28px; font-size:15px; font-weight:500; align-items:center; flex-wrap:wrap}
  .site-nav .key-btn{display:inline-flex; align-items:center; min-height:44px; padding:0 18px; background:#101010; color:#F6F5F2; text-decoration:none}
  .site-nav .key-btn:hover{color:#F6F5F2; background:#C41E26}
</style>
<nav class="site-nav">
  <a href="/" class="brand">UFC API <span>unofficial · open source</span></a>
  <div class="nav-links">
    <a href="/docs">Docs</a>
    <a href="https://github.com/mostafa-amine/ufc-api" target="_blank" rel="noopener">GitHub</a>
    <a href="/docs" class="key-btn">Get a free key</a>
  </div>
</nav>
