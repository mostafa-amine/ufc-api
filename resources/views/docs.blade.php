<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Unofficial UFC API · API Reference</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo+Narrow:wght@500;700&family=Archivo:wght@400;500;600;800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  /* Light only, matching the landing page; RapiDoc gets the same colours below. */
  :root{--paper:#F6F5F2; --ink:#101010; --muted:#55524C; --rule:#D6D2CA; --red:#C41E26; --blue:#1F4FD1}
  *{box-sizing:border-box}
  html,body{margin:0; background:var(--paper); height:100%}
  body{display:flex; flex-direction:column; overflow:hidden; color:var(--ink)}
  .site-nav{flex:0 0 auto; width:100%}
  .nav-wrap{border-bottom:2px solid var(--ink)}
  /* RapiDoc's own sizes are 13-15px and mixed; one reading size everywhere instead.
     Rules on the host beat the :host defaults inside its shadow DOM, and the
     variables inherit into every nested RapiDoc component. */
  rapi-doc{flex:1 1 auto; width:100%; --font-size-regular:16px; --font-size-small:16px; --font-size-mono:15px}
  /* Wider than RapiDoc's 260px so the 16px nav paths fit; the search button sizes to its text. */
  @media (min-width:768px){ rapi-doc::part(section-navbar){width:380px; min-width:380px} }
  rapi-doc::part(btn-search){width:auto}
  rapi-doc::part(section-navbar){border-right:1px solid var(--rule)}
  /* The nav filter is a search box, not code. */
  rapi-doc::part(textbox-nav-filter){font-family:'Archivo',sans-serif}
  /* RapiDoc prints "SELECTED: <url>" as one code-font text node; it is redrawn in the servers slot below. */
  rapi-doc::part(label-selected-server){display:none}
</style>
</head>
<body>
<div class="nav-wrap">@include('partials.site-nav')</div>

<rapi-doc
  spec-url="/docs/openapi.yaml"
  theme="light"
  bg-color="#F6F5F2"
  text-color="#101010"
  header-color="#F6F5F2"
  primary-color="#C41E26"
  nav-bg-color="#EFEDE8"
  nav-text-color="#3A3732"
  nav-hover-text-color="#101010"
  nav-hover-bg-color="#E6E3DD"
  nav-accent-color="#1F4FD1"
  nav-accent-text-color="#ffffff"
  regular-font="'Archivo', -apple-system, sans-serif"
  mono-font="'IBM Plex Mono', monospace"
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
  <div slot="overview" style="font-family:'Archivo',sans-serif;font-size:16px;margin:8px 0 4px">
    <a href="/docs/openapi.yaml" style="color:#C41E26;font-weight:600">OpenAPI file</a>
  </div>
  <div slot="servers" id="selected-server" style="font-family:'Archivo',sans-serif;font-size:16px;font-weight:700;color:#C41E26;margin:-4px 0 12px">SELECTED: <code style="font-family:'IBM Plex Mono',monospace;font-size:15px"></code></div>
  <div slot="auth" style="font-family:'Archivo',sans-serif;font-size:16px;line-height:1.5;color:#3A3732;padding:6px 0">
    Get a free key from <code style="color:#C41E26">POST /v1/register</code>, then send it as a Bearer token.
  </div>
</rapi-doc>

<script>
  // Keep the redrawn "SELECTED:" line in step with the server RapiDoc has chosen.
  (() => {
    const doc = document.querySelector('rapi-doc');
    const url = document.querySelector('#selected-server code');
    const show = (server) => { url.textContent = server ? (server.computedUrl || server.url) : 'none'; };
    doc.addEventListener('spec-loaded', () => show(doc.selectedServer));
    doc.addEventListener('api-server-change', (e) => show(e.detail.selectedServer));
  })();
</script>
<script type="module" src="https://unpkg.com/rapidoc@9.3.4/dist/rapidoc-min.js"></script>
</body>
</html>
