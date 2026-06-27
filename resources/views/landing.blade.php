<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Unofficial UFC API — every strike, every round, on the record</title>
<meta name="description" content="A hosted, open-source REST API with the full depth of ufcstats.com: round-by-round stats, significant-strike target/position breakdown, and judge scorecards mapped to the right fighter.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Sora:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#0a0a0c; --bg2:#101014; --panel:#141419; --ink:#f3efe6; --muted:#8b8b95;
    --red:#e4121f; --red-h:#ff3742; --blue:#2f6bff; --blue-h:#5b8bff;
    --line:rgba(255,255,255,.09); --line-2:rgba(255,255,255,.045);
    --gold:#d9b15a;
    --display:'Anton',sans-serif; --body:'Sora',sans-serif; --mono:'JetBrains Mono',monospace;
  }
  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{
    margin:0; background:var(--bg); color:var(--ink); font-family:var(--body);
    -webkit-font-smoothing:antialiased; line-height:1.55; overflow-x:hidden;
  }
  /* atmosphere */
  body::before{
    content:""; position:fixed; inset:0; z-index:-2; pointer-events:none;
    background:
      radial-gradient(60vw 60vw at 8% -10%, rgba(228,18,31,.22), transparent 60%),
      radial-gradient(55vw 55vw at 100% 110%, rgba(47,107,255,.18), transparent 60%),
      linear-gradient(180deg,#08080a,#0a0a0c 40%,#08080a);
  }
  body::after{
    content:""; position:fixed; inset:0; z-index:-1; pointer-events:none; opacity:.05;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  }
  .grid-lines{position:fixed; inset:0; z-index:-1; pointer-events:none; opacity:.5;
    background-image:linear-gradient(var(--line-2) 1px,transparent 1px),linear-gradient(90deg,var(--line-2) 1px,transparent 1px);
    background-size:64px 64px; mask-image:radial-gradient(circle at 50% 30%,#000,transparent 80%);}
  a{color:inherit; text-decoration:none}
  .wrap{max-width:1140px; margin:0 auto; padding:0 24px}

  /* nav */
  nav{position:sticky; top:0; z-index:50; backdrop-filter:blur(12px);
    background:rgba(10,10,12,.6); border-bottom:1px solid var(--line)}
  .nav-in{display:flex; align-items:center; justify-content:space-between; height:66px}
  .brand{display:flex; align-items:center; gap:12px; font-family:var(--display); letter-spacing:.5px; font-size:20px}
  .brand .mark{width:14px; height:26px; border-radius:2px;
    background:linear-gradient(var(--red),var(--red) 50%,var(--blue) 50%,var(--blue)); box-shadow:0 0 24px rgba(228,18,31,.5)}
  .brand small{font-family:var(--body); font-weight:600; font-size:10px; letter-spacing:.22em; color:var(--muted); align-self:flex-start; margin-top:2px}
  .nav-links{display:flex; align-items:center; gap:28px; font-size:14px; font-weight:500; color:var(--muted)}
  .nav-links a:hover{color:var(--ink)}
  .nav-links .btn{color:var(--ink)}
  @media(max-width:760px){.nav-links a:not(.btn){display:none}}

  .btn{display:inline-flex; align-items:center; gap:9px; font-weight:600; font-size:14px;
    padding:11px 20px; border-radius:7px; border:1px solid transparent; cursor:pointer; transition:.18s ease}
  .btn-red{background:var(--red); color:#fff; box-shadow:0 8px 30px -8px rgba(228,18,31,.7)}
  .btn-red:hover{background:var(--red-h); transform:translateY(-1px)}
  .btn-ghost{border-color:var(--line); color:var(--ink); background:rgba(255,255,255,.02)}
  .btn-ghost:hover{border-color:rgba(255,255,255,.28); background:rgba(255,255,255,.05)}

  /* hero */
  .hero{position:relative; padding:96px 0 60px}
  .kicker{display:inline-flex; align-items:center; gap:10px; font-size:11px; font-weight:700;
    letter-spacing:.26em; text-transform:uppercase; color:var(--muted); margin-bottom:26px}
  .kicker .dot{width:7px; height:7px; border-radius:50%; background:var(--red); box-shadow:0 0 12px var(--red); animation:pulse 2.2s infinite}
  @keyframes pulse{0%,100%{opacity:1}50%{opacity:.35}}
  h1{font-family:var(--display); font-weight:400; text-transform:uppercase; letter-spacing:.5px;
    font-size:clamp(46px,8.5vw,116px); line-height:.92; margin:0 0 26px}
  h1 .blue{color:var(--blue)} h1 .red{color:var(--red)}
  h1 .stroke{color:transparent; -webkit-text-stroke:1.4px rgba(243,239,230,.55)}
  .lede{max-width:620px; font-size:clamp(16px,2vw,19px); color:#c9c8cf; margin:0 0 34px}
  .lede b{color:var(--ink); font-weight:600}
  .cta-row{display:flex; flex-wrap:wrap; gap:14px; align-items:center}
  .cta-note{font-family:var(--mono); font-size:12px; color:var(--muted)}

  .hero-grid{display:grid; grid-template-columns:1.15fr .85fr; gap:48px; align-items:center}
  @media(max-width:900px){.hero-grid{grid-template-columns:1fr; gap:36px}}

  /* code card */
  .code{background:linear-gradient(180deg,#121217,#0d0d11); border:1px solid var(--line);
    border-radius:14px; overflow:hidden; box-shadow:0 40px 80px -40px rgba(0,0,0,.9); font-family:var(--mono)}
  .code-bar{display:flex; align-items:center; gap:8px; padding:13px 16px; border-bottom:1px solid var(--line); background:rgba(255,255,255,.02)}
  .code-bar .d{width:11px; height:11px; border-radius:50%}
  .code-bar .d.r{background:#ff5f57}.code-bar .d.y{background:#febc2e}.code-bar .d.g{background:#28c840}
  .code-bar span{margin-left:8px; font-size:12px; color:var(--muted)}
  .code pre{margin:0; padding:18px 20px; font-size:12.5px; line-height:1.75; overflow-x:auto}
  .c-key{color:#7fd1ff}.c-str{color:#9fe08f}.c-num{color:var(--gold)}.c-cmd{color:#fff}
  .c-mut{color:#5d5d68}.c-red{color:var(--red-h)}.c-blue{color:var(--blue-h)}

  /* tale of the tape */
  .tape{border-top:1px solid var(--line); border-bottom:1px solid var(--line); margin-top:54px;
    background:rgba(255,255,255,.015)}
  .tape-in{display:grid; grid-template-columns:repeat(4,1fr)}
  .tape .cell{padding:34px 24px; text-align:center; border-right:1px solid var(--line); position:relative}
  .tape .cell:last-child{border-right:0}
  .tape .num{font-family:var(--display); font-size:clamp(34px,5vw,60px); line-height:1}
  .tape .cell:nth-child(odd) .num{color:var(--red)} .tape .cell:nth-child(even) .num{color:var(--blue)}
  .tape .lab{font-size:11px; letter-spacing:.2em; text-transform:uppercase; color:var(--muted); margin-top:10px}
  @media(max-width:680px){.tape-in{grid-template-columns:repeat(2,1fr)} .tape .cell:nth-child(2){border-right:0}}

  /* sections */
  section.block{padding:90px 0}
  .eyebrow{font-size:11px; font-weight:700; letter-spacing:.26em; text-transform:uppercase; color:var(--red); margin-bottom:16px}
  h2{font-family:var(--display); font-weight:400; text-transform:uppercase; letter-spacing:.5px;
    font-size:clamp(30px,4.6vw,54px); line-height:.98; margin:0 0 18px}
  .sub{color:var(--muted); max-width:560px; margin:0 0 46px; font-size:16px}

  .feat{display:grid; grid-template-columns:repeat(3,1fr); gap:18px}
  @media(max-width:900px){.feat{grid-template-columns:1fr 1fr}}
  @media(max-width:600px){.feat{grid-template-columns:1fr}}
  .card{position:relative; padding:26px 24px 28px; border:1px solid var(--line); border-radius:13px;
    background:linear-gradient(180deg,rgba(255,255,255,.025),rgba(255,255,255,0)); transition:.2s ease; overflow:hidden}
  .card:hover{border-color:rgba(255,255,255,.22); transform:translateY(-3px); background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.01))}
  .card .idx{font-family:var(--mono); font-size:12px; color:var(--muted)}
  .card h3{font-size:18px; margin:16px 0 8px; font-weight:700}
  .card p{margin:0; color:var(--muted); font-size:14.5px}
  .card.flag{border-color:rgba(228,18,31,.45); background:linear-gradient(180deg,rgba(228,18,31,.08),rgba(228,18,31,.01))}
  .card.flag::after{content:"MOST APIS GET THIS WRONG"; position:absolute; top:14px; right:-34px; transform:rotate(45deg);
    background:var(--red); color:#fff; font-size:9px; font-weight:700; letter-spacing:.12em; padding:4px 40px}

  /* scorecard showcase */
  .show{display:grid; grid-template-columns:1fr 1fr; gap:48px; align-items:center}
  @media(max-width:840px){.show{grid-template-columns:1fr; gap:36px}}
  .scoreboard{border:1px solid var(--line); border-radius:14px; overflow:hidden; background:var(--panel); font-family:var(--mono)}
  .sb-head{display:grid; grid-template-columns:1fr 64px 64px; background:rgba(255,255,255,.03); border-bottom:1px solid var(--line)}
  .sb-head>div{padding:13px 16px; font-size:11px; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); text-align:center}
  .sb-head>div:first-child{text-align:left}
  .sb-row{display:grid; grid-template-columns:1fr 64px 64px; border-bottom:1px solid var(--line-2)}
  .sb-row:last-child{border-bottom:0}
  .sb-row>div{padding:14px 16px; text-align:center; font-size:14px}
  .sb-row .judge{text-align:left; color:#cfcfd6; font-family:var(--body)}
  .sb-row .red{color:var(--red-h); font-weight:700; background:rgba(228,18,31,.07)}
  .sb-row .blue{color:var(--blue-h); font-weight:700; background:rgba(47,107,255,.07)}
  .sb-foot{display:flex; justify-content:space-between; padding:13px 16px; font-size:12px; color:var(--muted); border-top:1px solid var(--line)}
  .corner-tag{display:inline-flex; align-items:center; gap:7px; font-size:11px; letter-spacing:.1em; text-transform:uppercase}
  .corner-tag .sq{width:9px;height:9px;border-radius:2px}

  /* endpoints */
  .ep{border:1px solid var(--line); border-radius:13px; overflow:hidden; background:rgba(255,255,255,.012)}
  .ep-row{display:flex; align-items:center; gap:18px; padding:16px 22px; border-bottom:1px solid var(--line-2); transition:.15s}
  .ep-row:last-child{border-bottom:0}
  .ep-row:hover{background:rgba(255,255,255,.03)}
  .verb{font-family:var(--mono); font-size:11px; font-weight:700; padding:4px 9px; border-radius:5px; min-width:52px; text-align:center}
  .verb.get{color:#9fe08f; background:rgba(120,224,120,.12); border:1px solid rgba(120,224,120,.25)}
  .verb.post{color:var(--gold); background:rgba(217,177,90,.12); border:1px solid rgba(217,177,90,.28)}
  .ep-path{font-family:var(--mono); font-size:14px; color:var(--ink)}
  .ep-desc{margin-left:auto; color:var(--muted); font-size:13px; text-align:right}
  @media(max-width:640px){.ep-desc{display:none}}

  /* footer */
  footer{border-top:1px solid var(--line); padding:54px 0 40px; margin-top:40px}
  .foot-in{display:flex; flex-wrap:wrap; gap:24px; justify-content:space-between; align-items:center}
  .disclaimer{color:var(--muted); font-size:12.5px; max-width:560px; line-height:1.7}
  .foot-links{display:flex; gap:24px; font-size:14px; color:var(--muted)}
  .foot-links a:hover{color:var(--ink)}

  /* load anim */
  .rise{opacity:0; transform:translateY(18px); animation:rise .8s cubic-bezier(.2,.7,.2,1) forwards}
  @keyframes rise{to{opacity:1; transform:none}}
</style>
</head>
<body>
<div class="grid-lines"></div>

<nav>
  <div class="wrap nav-in">
    <a href="/" class="brand"><span class="mark"></span>UFC&middot;API <small>UNOFFICIAL</small></a>
    <div class="nav-links">
      <a href="#features">Features</a>
      <a href="#endpoints">Endpoints</a>
      <a href="https://github.com/" target="_blank" rel="noopener">GitHub</a>
      <a href="/docs" class="btn btn-ghost">Read the docs</a>
    </div>
  </div>
</nav>

<header class="hero">
  <div class="wrap hero-grid">
    <div>
      <span class="kicker rise"><span class="dot"></span>Powered by ufcstats &middot; Open source</span>
      <h1 class="rise" style="animation-delay:.05s">
        Every <span class="red">strike</span>.<br>
        Every <span class="stroke">round</span>.<br>
        On the <span class="blue">record</span>.
      </h1>
      <p class="lede rise" style="animation-delay:.12s">
        A hosted, open-source REST API with the <b>full depth</b> of ufcstats —
        round-by-round stats, significant-strike <b>target &amp; position</b> breakdowns,
        and judge scorecards mapped to the <b>right fighter</b>.
      </p>
      <div class="cta-row rise" style="animation-delay:.18s">
        <a href="/docs" class="btn btn-red">Explore the API →</a>
        <a href="#response" class="btn btn-ghost">See a real response</a>
      </div>
    </div>

    <div class="code rise" style="animation-delay:.24s" aria-hidden="true">
      <div class="code-bar"><span class="d r"></span><span class="d y"></span><span class="d g"></span><span>request.sh</span></div>
<pre><span class="c-mut"># every round, every strike target</span>
<span class="c-cmd">curl</span> <span class="c-str">https://api/v1/fights/{id}</span> \
  <span class="c-mut">-H</span> <span class="c-str">"Authorization: Bearer KEY"</span>

<span class="c-key">"method"</span>: <span class="c-str">"Decision - Unanimous"</span>,
<span class="c-key">"scorecards"</span>: [
  { <span class="c-key">"judge"</span>:<span class="c-str">"D. Lethaby"</span>,
    <span class="c-red">"red"</span>:<span class="c-num">30</span>, <span class="c-blue">"blue"</span>:<span class="c-num">27</span> }
],
<span class="c-key">"stats"</span>: { <span class="c-key">"red"</span>: { <span class="c-key">"total"</span>: {
  <span class="c-key">"sig_str"</span>:{<span class="c-key">"landed"</span>:<span class="c-num">47</span>,<span class="c-key">"pct"</span>:<span class="c-num">48</span>},
  <span class="c-key">"control_time_sec"</span>:<span class="c-num">533</span> }}}</pre>
    </div>
  </div>
</header>

<div class="tape">
  <div class="wrap tape-in">
    <div class="cell"><div class="num">{{ number_format($counts['events']) }}</div><div class="lab">Events</div></div>
    <div class="cell"><div class="num">{{ number_format($counts['fighters']) }}</div><div class="lab">Fighters</div></div>
    <div class="cell"><div class="num">{{ number_format($counts['fights']) }}</div><div class="lab">Fights</div></div>
    <div class="cell"><div class="num">{{ number_format($counts['rounds']) }}</div><div class="lab">Rounds of stats</div></div>
  </div>
</div>

<section class="block" id="features">
  <div class="wrap">
    <div class="eyebrow">The difference</div>
    <h2>Depth nobody else exposes</h2>
    <p class="sub">ufcstats is the ceiling on detail. The gap was never the data — it was turning that depth into a clean, queryable API. So we did.</p>
    <div class="feat">
      <div class="card"><div class="idx">01</div><h3>Round-by-round</h3><p>Every metric per round, not just fight totals. Filter, aggregate, model on it.</p></div>
      <div class="card"><div class="idx">02</div><h3>Strike breakdown</h3><p>Significant strikes split by target — head / body / leg — and position — distance / clinch / ground.</p></div>
      <div class="card flag"><div class="idx">03</div><h3>Scorecards, correct</h3><p>ufcstats lists scores as loser&ndash;winner, not by corner. We map every judge to the right fighter.</p></div>
      <div class="card"><div class="idx">04</div><h3>Control &amp; finishes</h3><p>Control time, knockdowns, reversals, submission attempts, finish method &amp; referee.</p></div>
      <div class="card"><div class="idx">05</div><h3>Stable IDs</h3><p>Every resource keyed on its ufcstats id, so you can always cross-reference the source.</p></div>
      <div class="card"><div class="idx">06</div><h3>Free API key</h3><p>One request to register. OpenAPI docs, Postman collection, predictable JSON envelope.</p></div>
    </div>
  </div>
</section>

@php
  // Real bout data when scraped; otherwise the original static illustration.
  $f = $featured ?? [
    'red_last' => 'Aliskerov', 'blue_last' => 'Ferreira',
    'red_score' => 30, 'blue_score' => 27, 'red_sig' => 47, 'blue_sig' => 19,
    'control' => '8:53', 'method' => 'Decision - Unanimous', 'event' => 'UFC Fight Night',
    'cards' => [
      ['judge' => 'David Lethaby', 'red' => 30, 'blue' => 27],
      ['judge' => 'Vito Paolillo', 'red' => 30, 'blue' => 27],
      ['judge' => 'Clemens Werner', 'red' => 30, 'blue' => 27],
    ],
  ];
  $disparity = $f['red_sig'].' significant strikes to '.$f['blue_sig']
      .($f['control'] ? ', '.$f['control'].' of control' : '');
@endphp
<section class="block" id="response">
  <div class="wrap show">
    <div>
      <div class="eyebrow">Mapped right{{ $featured ? ' · live from the API' : '' }}</div>
      <h2>{{ $f['red_last'] }} {{ $f['red_score'] }},<br>{{ $f['blue_last'] }} {{ $f['blue_score'] }}.</h2>
      <p class="sub" style="margin-bottom:26px">{{ $f['red_last'] }} dominated &mdash; {{ $disparity }} &mdash; and won every card. ufcstats prints that as <span style="font-family:var(--mono);color:var(--ink)">{{ $f['blue_score'] }}&nbsp;&ndash;&nbsp;{{ $f['red_score'] }}</span>. Read it by column and you hand the win to the loser. We resolve scores by the bout winner, so the corners are never flipped.</p>
      <div style="display:flex; gap:22px; flex-wrap:wrap">
        <span class="corner-tag"><span class="sq" style="background:var(--red)"></span>Red corner</span>
        <span class="corner-tag"><span class="sq" style="background:var(--blue)"></span>Blue corner</span>
      </div>
    </div>
    <div class="scoreboard">
      <div class="sb-head"><div>Judge</div><div>Red</div><div>Blue</div></div>
      @foreach($f['cards'] as $card)
      <div class="sb-row"><div class="judge">{{ $card['judge'] }}</div><div class="red">{{ $card['red'] }}</div><div class="blue">{{ $card['blue'] }}</div></div>
      @endforeach
      <div class="sb-foot"><span>{{ $f['method'] }}</span><span>{{ \Illuminate\Support\Str::limit($f['event'], 34) }}</span></div>
    </div>
  </div>
</section>

<section class="block" id="endpoints">
  <div class="wrap">
    <div class="eyebrow">Surface</div>
    <h2>Nine endpoints</h2>
    <p class="sub">Versioned under <span style="font-family:var(--mono);color:var(--ink)">/v1</span>. Filtered, paginated, documented.</p>
    <div class="ep">
      <div class="ep-row"><span class="verb post">POST</span><span class="ep-path">/v1/register</span><span class="ep-desc">Get a free API key</span></div>
      <div class="ep-row"><span class="verb get">GET</span><span class="ep-path">/v1/events</span><span class="ep-desc">List &amp; filter events</span></div>
      <div class="ep-row"><span class="verb get">GET</span><span class="ep-path">/v1/events/{id}</span><span class="ep-desc">Event + its bouts</span></div>
      <div class="ep-row"><span class="verb get">GET</span><span class="ep-path">/v1/fighters</span><span class="ep-desc">Search fighters</span></div>
      <div class="ep-row"><span class="verb get">GET</span><span class="ep-path">/v1/fighters/{id}</span><span class="ep-desc">Profile + career averages</span></div>
      <div class="ep-row"><span class="verb get">GET</span><span class="ep-path">/v1/fighters/{id}/fights</span><span class="ep-desc">Bout history</span></div>
      <div class="ep-row"><span class="verb get">GET</span><span class="ep-path">/v1/fights</span><span class="ep-desc">List &amp; filter fights</span></div>
      <div class="ep-row"><span class="verb get">GET</span><span class="ep-path">/v1/fights/{id}</span><span class="ep-desc">Full fight: scorecards + round-by-round</span></div>
      <div class="ep-row"><span class="verb get">GET</span><span class="ep-path">/v1/health</span><span class="ep-desc">Status + counts (public)</span></div>
    </div>
    <div style="margin-top:34px"><a href="/docs" class="btn btn-red">Open the full docs →</a></div>
  </div>
</section>

<footer>
  <div class="wrap foot-in">
    <p class="disclaimer">
      <strong style="color:var(--ink)">Unofficial UFC API</strong> &middot; MIT licensed.
      Not affiliated with, endorsed by, or sponsored by the UFC or Zuffa LLC.
      All fight data sourced from ufcstats.com and belongs to its respective owners.
    </p>
    <div class="foot-links">
      <a href="/docs">Docs</a>
      <a href="https://github.com/" target="_blank" rel="noopener">GitHub</a>
      <a href="/v1/health">Status</a>
    </div>
  </div>
</footer>
</body>
</html>
