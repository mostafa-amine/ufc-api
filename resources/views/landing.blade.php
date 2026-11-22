<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Unofficial UFC API · every strike, every round, one request</title>
<meta name="description" content="A hosted, open-source REST API with the full depth of ufcstats.com: round-by-round stats, significant-strike target/position breakdown, and judge scorecards mapped to the right fighter.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo+Narrow:wght@500;700&family=Archivo:wght@400;500;600;800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#F6F5F2; --ink:#101010; --muted:#55524C; --soft:#3A3732; --trail:#6B675F;
    --track:#E6E3DD; --rule:#D6D2CA; --red:#C41E26; --blue:#1F4FD1;
    --night-line:#3A3732; --night-text:#CFCAC0; --night-muted:#BDB8AE; --night-blue:#A9BCF5;
    --narrow:'Archivo Narrow',sans-serif; --mono:'IBM Plex Mono',monospace;
  }
  *{box-sizing:border-box}
  body{margin:0; background:var(--paper); color:var(--ink); font-family:'Archivo',sans-serif; overflow-x:hidden}
  a{color:var(--ink)} a:hover{color:var(--red)}
  button{font:inherit}
  pre{margin:0}
  .wrap{max-width:1200px; margin:0 auto; padding:0 24px}
  @media(max-width:520px){.wrap{padding:0 16px}}

  .btn{display:inline-flex; align-items:center; min-height:52px; padding:0 26px; text-decoration:none; font-weight:600; font-size:16px}
  .btn.solid{background:var(--red); color:#fff} .btn.solid:hover{background:var(--ink); color:#fff}
  .btn.ghost{border:2px solid var(--ink)} .btn.ghost:hover{background:var(--ink); color:var(--paper)}

  .hero{display:flex; flex-wrap:wrap; gap:32px 40px; justify-content:space-between; align-items:flex-end; padding-top:48px; padding-bottom:48px}
  .hero-text{flex:1 1 520px; min-width:0}
  .hero h1{margin:0; font-family:var(--narrow); font-weight:700; font-size:clamp(44px,6vw,80px); line-height:.94; text-transform:uppercase}
  .hero p{margin:20px 0 0; font-size:19px; line-height:1.55; color:var(--soft); max-width:560px}
  .ctas{display:flex; flex-wrap:wrap; gap:14px}
  @media(max-width:520px){.hero{padding-top:24px; padding-bottom:32px} .hero p{font-size:17px}}

  .showcase{display:flex; flex-wrap:wrap}
  .card{flex:1 1 440px; min-width:0; background:#fff; border:2px solid var(--ink)}
  .corners{display:flex}
  .corner{flex:1 1 0; min-width:0; color:#fff; padding:28px 20px}
  .corner.red{background:var(--red); text-align:right}
  .corner.blue{background:var(--blue)}
  .corner .tag{font-family:var(--mono); font-size:12px}
  .corner .first{font-size:18px; margin-top:4px; overflow-wrap:break-word}
  .corner .last{font-family:var(--narrow); font-weight:700; font-size:clamp(30px,4vw,44px); line-height:.9; text-transform:uppercase; overflow-wrap:break-word}
  .stats{padding:8px 12px 16px; display:flex; flex-direction:column; gap:4px}
  .stat{display:block; width:100%; text-align:left; padding:14px 12px; cursor:pointer; color:var(--ink); background:transparent; border:2px solid transparent; min-height:44px}
  .stat:hover{border-color:var(--rule)}
  .stat[aria-pressed="true"]{border-color:var(--ink); background:var(--paper)}
  .stat:focus-visible{outline:3px solid var(--blue); outline-offset:2px}
  .stat-head{display:grid; grid-template-columns:72px minmax(0,1fr) 72px; align-items:baseline; gap:8px}
  .num{font-family:var(--narrow); font-weight:700; font-size:30px; color:var(--trail)}
  .num.lead{color:var(--ink)}
  .num.right{text-align:right}
  .label{text-align:center; font-size:14px; font-weight:600; text-transform:uppercase; letter-spacing:.04em}
  .note{display:block; font-family:var(--mono); font-size:12px; font-weight:400; text-transform:none; letter-spacing:0; color:var(--muted); margin-top:4px}
  .verdict{text-align:center}
  .verdict .label{display:block}
  .verdict .scores{display:block; font-family:var(--narrow); font-weight:700; font-size:26px; margin-top:4px}
  .bars{display:flex; gap:4px; margin-top:8px}
  .track{flex:1; display:flex; background:var(--track)}
  .track.red-side{justify-content:flex-end}
  .bar{display:block; height:10px}
  .bar.red{background:var(--red)} .bar.blue{background:var(--blue)}
  .meta{font-family:var(--mono); font-size:12px; color:var(--muted); padding:12px 12px 0; display:flex; flex-wrap:wrap; gap:4px 16px}
  @media(max-width:520px){.corner{padding:20px 14px} .stat-head{grid-template-columns:56px minmax(0,1fr) 56px} .num{font-size:26px}}

  .response{flex:1 1 520px; min-width:0; background:var(--ink); color:var(--paper); font-family:var(--mono); font-size:14px; display:flex; flex-direction:column}
  .response-head{display:flex; flex-wrap:wrap; justify-content:space-between; gap:8px 16px; padding:14px 20px; border-bottom:1px solid var(--night-line); color:var(--night-text)}
  .response-head .verb{color:var(--night-blue)}
  .response-head .ok{color:#8FD19E}
  .lines{padding:16px 0; overflow-x:auto; flex:1}
  .line{white-space:pre; padding:0 20px; line-height:1.75; color:var(--night-text); width:max-content; min-width:100%}
  .line.lit{background:var(--red); color:#fff}
  .response-foot{padding:14px 20px; border-top:1px solid var(--night-line); color:var(--night-muted); font-size:13px; line-height:1.6; font-family:'Archivo',sans-serif}
  /* On a phone the lines wrap instead of hiding their numbers off to the side. */
  @media(max-width:520px){.response{font-size:12px} .line{white-space:pre-wrap; overflow-wrap:anywhere; width:auto; padding:0 14px 0 28px; text-indent:-14px} .response-head{padding:12px 14px}}

  section.part{padding-top:96px}
  section.part h2{margin:0; font-family:var(--narrow); font-weight:700; font-size:clamp(30px,4vw,40px); text-transform:uppercase}
  .endpoints{display:grid; grid-template-columns:repeat(auto-fit,minmax(min(260px,100%),1fr)); gap:20px; margin-top:28px}
  .endpoint{border-top:2px solid var(--ink); padding-top:16px}
  .endpoint code{font-family:var(--mono); font-size:14px}
  .endpoint p{margin:8px 0 0; color:var(--soft); line-height:1.5}
  .extras{display:grid; grid-template-columns:repeat(auto-fit,minmax(min(260px,100%),1fr)); gap:28px; margin-top:28px}
  .extras h3{margin:0; font-size:18px; font-weight:600}
  .extras p{margin:8px 0 0; color:var(--soft); line-height:1.55}

  .closing{margin-top:104px; background:var(--ink); color:var(--paper)}
  .closing-in{display:flex; flex-wrap:wrap; gap:32px; justify-content:space-between; align-items:center; padding-top:64px; padding-bottom:64px}
  .closing pre{font-family:var(--mono); font-size:15px; line-height:1.7; overflow-x:auto; max-width:100%; min-width:0}
  .closing .btn{background:var(--paper); color:var(--ink)} .closing .btn:hover{background:var(--red); color:#fff}
  .fine{padding-bottom:40px; font-size:13px; color:var(--night-muted)}
  @media(max-width:520px){.closing pre{font-size:13px; white-space:pre-wrap; overflow-wrap:anywhere}}
</style>
</head>
<body>

@include('partials.site-nav')

<header class="wrap hero">
  <div class="hero-text">
    @if ($tape)
      <h1>This fight card<br>is one API call</h1>
      <p>Pick a stat to see where it lives in the response. Every UFC fight on ufcstats.com, round by round, with a free key.</p>
    @else
      <h1>Every UFC round,<br>one API call</h1>
      <p>Every UFC fight on ufcstats.com, round by round, with a free key.</p>
    @endif
  </div>
  <div class="ctas">
    <a href="/register" class="btn solid">Get a free key</a>
    <a href="/docs" class="btn ghost">Read the docs</a>
  </div>
</header>

@if ($tape)
<main class="wrap">
  <div class="showcase">
    <div class="card">
      <div class="corners">
        <div class="corner red">
          <div class="tag">Red corner{{ $tape['red']['result'] ? ' · '.$tape['red']['result'] : '' }}</div>
          <div class="first">{{ $tape['red']['first'] }}</div>
          <div class="last">{{ $tape['red']['last'] }}</div>
        </div>
        <div class="corner blue">
          <div class="tag">Blue corner{{ $tape['blue']['result'] ? ' · '.$tape['blue']['result'] : '' }}</div>
          <div class="first">{{ $tape['blue']['first'] }}</div>
          <div class="last">{{ $tape['blue']['last'] }}</div>
        </div>
      </div>
      <div class="stats" role="group" aria-label="Show where a stat is in the response">
        <button type="button" class="stat verdict" data-group="scores" aria-pressed="false">
          <span class="label">{{ $tape['decision'] }}</span>
          <span class="scores">{{ $tape['scores'] }}</span>
        </button>
        @foreach ($tape['rows'] as $row)
        <button type="button" class="stat" data-group="{{ $row['group'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
          <span class="stat-head">
            <span class="num {{ $row['red_leads'] ? 'lead' : '' }}">{{ $row['red'] }}</span>
            <span class="label">{{ $row['label'] }}@if ($row['note'])<span class="note">{{ $row['note'] }}</span>@endif</span>
            <span class="num right {{ $row['blue_leads'] ? 'lead' : '' }}">{{ $row['blue'] }}</span>
          </span>
          <span class="bars" aria-hidden="true">
            <span class="track red-side"><span class="bar red" style="width: {{ $row['red_width'] }}%"></span></span>
            <span class="track"><span class="bar blue" style="width: {{ $row['blue_width'] }}%"></span></span>
          </span>
        </button>
        @endforeach
        <div class="meta">
          @foreach ($tape['meta'] as $item)<span>{{ $item }}</span>@endforeach
        </div>
      </div>
    </div>

    <div class="response">
      <div class="response-head">
        <span><span class="verb">GET</span> /v1/fights/{{ $tape['id'] }}</span>
        <span class="ok">200 OK</span>
      </div>
      <div class="lines" tabindex="0" aria-label="API response for this fight">
        @foreach ($tape['response'] as $line)
          <div class="line {{ $line['group'] === 'sig' ? 'lit' : '' }}" @if ($line['group']) data-line="{{ $line['group'] }}" @endif>{{ $line['text'] }}</div>
        @endforeach
      </div>
      @if ($tape['ufcstats_printed'])
        <div class="response-foot">ufcstats prints this fight as {{ $tape['ufcstats_printed'] }}, loser first. We match each judge's score to the fighter it belongs to.</div>
      @endif
    </div>
  </div>
</main>
@endif

<section class="wrap part">
  <h2>Everything ufcstats has</h2>
  <div class="endpoints">
    <div class="endpoint"><code>/v1/events</code><p>{{ number_format($counts['events']) }} {{ Str::plural('event', $counts['events']) }}, each with its full card.</p></div>
    <div class="endpoint"><code>/v1/fights</code><p>{{ number_format($counts['fights']) }} {{ Str::plural('fight', $counts['fights']) }}. Filter by event, fighter, method, weight or title.</p></div>
    <div class="endpoint"><code>/v1/fighters</code><p>{{ number_format($counts['fighters']) }} {{ Str::plural('fighter', $counts['fighters']) }}, with record and career averages.</p></div>
    <div class="endpoint"><code>/v1/health</code><p>Is it up, how fresh the data is, and the latest card we have. No key needed.</p></div>
  </div>
</section>

<section class="wrap part">
  <h2>What other UFC APIs leave out</h2>
  <div class="extras">
    <div><h3>Round by round</h3><p>Strikes, takedowns and control time for each round, not just the fight total.</p></div>
    <div><h3>Where strikes landed</h3><p>Head, body or leg, and from distance, clinch or the ground.</p></div>
    <div><h3>Scores in the right corner</h3><p>Each judge's score matched to the fighter it belongs to.</p></div>
    <div><h3>Same IDs as ufcstats</h3><p>Check any record against the source.</p></div>
  </div>
</section>

<section class="closing">
  <div class="wrap closing-in">
<pre>curl {{ url('/v1/fighters') }}?search={{ $tape ? rawurlencode(strtolower($tape['red']['last'])) : 'silva' }} \
  -H "Authorization: Bearer $KEY"</pre>
    <a href="/register" class="btn">Get a free key</a>
  </div>
  <div class="wrap fine">Not for gambling: see the <a href="/terms" style="color:inherit">terms of use</a>. Not affiliated with the UFC or Zuffa LLC. Data from ufcstats.com.</div>
</section>

@if ($tape)
<script>
  (() => {
    const buttons = document.querySelectorAll('.stat[data-group]');
    const lines = document.querySelectorAll('.line[data-line]');
    buttons.forEach((button) => button.addEventListener('click', () => {
      const group = button.dataset.group;
      buttons.forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
      lines.forEach((line) => line.classList.toggle('lit', line.dataset.line === group));
      const first = document.querySelector(`.line[data-line="${group}"]`);
      // On a phone the response sits below the card: bring the lit lines into view.
      if (first && window.matchMedia('(max-width: 960px)').matches) {
        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }));
  })();
</script>
@endif
</body>
</html>
