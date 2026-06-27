<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Unofficial UFC API — API Reference</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Sora:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  :root{--red:#e4121f;--red-h:#ff3742;--blue:#2f6bff;--ink:#f3efe6;--muted:#8b8b95;--line:rgba(255,255,255,.09)}
  *{box-sizing:border-box}
  html,body{margin:0;background:#0a0a0c;height:100%}
  body{display:flex;flex-direction:column;overflow:hidden}
  .topbar{display:flex;align-items:center;justify-content:space-between;height:60px;flex:0 0 60px;
    padding:0 22px;background:rgba(10,10,12,.92);border-bottom:1px solid var(--line);z-index:10}
  .brand{display:flex;align-items:center;gap:11px;color:var(--ink);text-decoration:none;
    font-family:'Anton',sans-serif;letter-spacing:.5px;font-size:18px}
  .brand .mark{width:12px;height:23px;border-radius:2px;
    background:linear-gradient(var(--red),var(--red) 50%,var(--blue) 50%,var(--blue));box-shadow:0 0 20px rgba(228,18,31,.5)}
  .brand small{font-family:'Sora',sans-serif;font-weight:600;font-size:9px;letter-spacing:.22em;color:var(--muted);align-self:flex-start;margin-top:2px}
  .top-links{display:flex;align-items:center;gap:18px;font-family:'Sora',sans-serif;font-size:13px;font-weight:500}
  .top-links a{color:var(--muted);text-decoration:none}
  .top-links a:hover{color:var(--ink)}
  .top-links .key{color:#fff;background:var(--red);padding:8px 15px;border-radius:6px;box-shadow:0 8px 26px -10px rgba(228,18,31,.7)}
  rapi-doc{flex:1 1 auto;width:100%}
  /* slim red scrollbars to match */
  rapi-doc::part(section-navbar){border-right:1px solid var(--line)}
</style>
</head>
<body>
<div class="topbar">
  <a href="/" class="brand"><span class="mark"></span>UFC&middot;API <small>DOCS</small></a>
  <div class="top-links">
    <a href="/">← Home</a>
    <a href="https://github.com/" target="_blank" rel="noopener">GitHub</a>
    <a href="/docs/openapi.yaml">OpenAPI</a>
  </div>
</div>

<rapi-doc
  spec-url="/docs/openapi.yaml"
  theme="dark"
  bg-color="#0a0a0c"
  text-color="#d8d5cc"
  header-color="#0a0a0c"
  primary-color="#ff3742"
  nav-bg-color="#0c0c0f"
  nav-text-color="#9a9aa3"
  nav-hover-text-color="#f3efe6"
  nav-hover-bg-color="rgba(255,255,255,.04)"
  nav-accent-color="#ff3742"
  nav-accent-text-color="#ffffff"
  regular-font="'Sora', -apple-system, sans-serif"
  mono-font="'JetBrains Mono', monospace"
  font-size="large"
  render-style="read"
  schema-style="table"
  schema-expand-level="2"
  default-schema-tab="schema"
  show-header="false"
  show-info="true"
  allow-spec-url-load="false"
  allow-spec-file-load="false"
  allow-server-selection="true"
  allow-authentication="true"
  persist-auth="true"
  show-method-in-nav-bar="as-colored-text"
  use-path-in-nav-bar="true"
  fill-request-fields-with-example="true"
>
  <div slot="auth" style="font-family:'Sora',sans-serif;font-size:13px;color:#8b8b95;padding:6px 0">
    Get a free key from <code style="color:#ff3742">POST /v1/register</code>, then send it as a Bearer token.
  </div>
</rapi-doc>

<script type="module" src="https://unpkg.com/rapidoc@9.3.4/dist/rapidoc-min.js"></script>
</body>
</html>
