<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Unofficial UFC API · every strike, every round, on the record</title>
<meta name="description" content="A hosted, open-source REST API with the full depth of ufcstats.com: round-by-round stats, significant-strike target/position breakdown, and judge scorecards mapped to the right fighter.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo+Narrow:wght@500;700&family=Archivo:wght@400;500;600;800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#F6F5F2; --ink:#101010; --muted:#55524C; --soft:#3A3732; --trail:#7A766E;
    --track:#E6E3DD; --rule:#D6D2CA; --red:#C41E26; --blue:#1F4FD1; --night-muted:#BDB8AE; --night-text:#CFCAC0;
    --narrow:'Archivo Narrow',sans-serif; --mono:'IBM Plex Mono',monospace;
    --ease:cubic-bezier(.2,.8,.2,1);
  }
  *{box-sizing:border-box}
  body{margin:0; background:var(--paper); color:var(--ink); font-family:'Archivo',sans-serif; overflow-x:hidden}
  a{color:var(--ink)} a:hover{color:var(--red)}
  button{font:inherit}

  nav{max-width:1240px; margin:0 auto; padding:22px 24px; display:flex; flex-wrap:wrap; gap:16px; justify-content:space-between; align-items:center}
  .brand{font-weight:800; font-size:20px; text-decoration:none}
  .brand span{font-weight:400; font-size:14px; color:var(--muted)}
  .nav-links{display:flex; gap:28px; font-size:15px; font-weight:500; align-items:center; flex-wrap:wrap}
  .key-btn{display:inline-flex; align-items:center; min-height:44px; padding:0 18px; background:var(--ink); color:var(--paper); text-decoration:none}
  .key-btn:hover{color:var(--paper); background:var(--red)}

  .corners{position:relative; display:flex; flex-wrap:wrap}
  .corner{flex:1 1 360px; color:#fff; padding:72px 40px 84px; display:flex; flex-direction:column; justify-content:flex-end; gap:6px}
  .corner.red{background:var(--red); align-items:flex-end; text-align:right}
  .corner.blue{background:var(--blue)}
  .corner .tag{font-family:var(--mono); font-size:13px; letter-spacing:.1em; text-transform:uppercase}
  .corner .first{font-size:26px; font-weight:500}
  .corner .last{font-family:var(--narrow); font-weight:700; font-size:clamp(48px,7.5vw,112px); line-height:.88; text-transform:uppercase; letter-spacing:-.01em; overflow-wrap:break-word}
  .verdict{position:absolute; left:50%; bottom:-34px; transform:translateX(-50%); background:var(--ink); color:var(--paper); padding:16px 28px; text-align:center; white-space:nowrap}
  .verdict .kind{font-family:var(--mono); font-size:12px; letter-spacing:.1em; text-transform:uppercase; color:var(--night-muted)}
  .verdict .scores{font-family:var(--narrow); font-weight:700; font-size:30px; margin-top:2px}
  @media(max-width:520px){.corner{padding:48px 16px 72px} .verdict{padding:12px 16px} .verdict .scores{font-size:22px}}

  .body{max-width:1040px; margin:0 auto; padding:72px 24px 0}
  @media(max-width:520px){.body{padding:72px 16px 0}}
  .meta{display:flex; flex-wrap:wrap; justify-content:center; gap:8px 22px; font-family:var(--mono); font-size:13px; color:var(--muted)}
  .periods{display:flex; justify-content:center; gap:6px; margin-top:40px; flex-wrap:wrap}
  .periods button{min-height:44px; padding:0 18px; border:2px solid var(--ink); background:transparent; color:var(--ink); font-weight:600; font-size:14px; cursor:pointer}
  .periods button[aria-pressed="true"]{background:var(--ink); color:var(--paper)}

  .rows{display:flex; flex-direction:column; gap:24px; margin-top:36px}
  .row-head{display:grid; grid-template-columns:minmax(64px,120px) minmax(0,1fr) minmax(64px,120px); align-items:baseline; gap:8px}
  .num{font-family:var(--narrow); font-weight:700; font-size:clamp(28px,5vw,40px); color:var(--trail)}
  .num.lead{color:var(--ink)}
  .num.blue-side{text-align:right}
  .row-label{text-align:center; font-size:14px; font-weight:600; text-transform:uppercase; letter-spacing:.06em}
  .row-note{display:block; font-family:var(--mono); font-weight:400; font-size:12px; text-transform:none; letter-spacing:0; color:var(--muted); margin-top:4px}
  .bars{display:flex; gap:4px; margin-top:8px}
  .track{flex:1; background:var(--track); display:flex}
  .track.red-side{justify-content:flex-end}
  .bar{height:16px; transition:width .7s var(--ease)}
  .bar.red{background:var(--red)} .bar.blue{background:var(--blue)}

  section.part{margin-top:72px; padding-top:36px; border-top:2px solid var(--ink)}
  section.part h2{margin:0; font-family:var(--narrow); font-weight:700; font-size:34px; text-transform:uppercase}
  section.part p.lede{margin:8px 0 28px; font-size:16px; color:var(--soft)}
  .maps{display:grid; grid-template-columns:repeat(auto-fit,minmax(min(300px,100%),1fr)); gap:40px}
  .map-title{font-size:14px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; margin-bottom:12px}
  .stack{display:flex; height:28px; gap:2px}
  .seg{min-width:0; overflow:hidden; font-family:var(--mono); font-size:12px; display:flex; align-items:center; padding:0 8px; white-space:nowrap; transition:flex-grow .7s var(--ease)}
  .seg.red.s0{background:#C41E26; color:#fff} .seg.red.s1{background:#E0767B} .seg.red.s2{background:#F2C2C4}
  .seg.blue.s0{background:#1F4FD1; color:#fff} .seg.blue.s1{background:#7F9BE7} .seg.blue.s2{background:#C6D3F5}
  .seg.s1,.seg.s2{color:var(--ink)}
  .legend{display:flex; gap:14px; flex-wrap:wrap; margin:6px 0 14px; font-family:var(--mono); font-size:12px; color:var(--soft)}
  .legend b{font-weight:500; color:var(--ink)}

  .round-chart{display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:16px}
  .round-chart button{display:block; width:100%; padding:16px; border:2px solid var(--rule); background:transparent; cursor:pointer; text-align:left; color:var(--ink)}
  .round-chart button[aria-pressed="true"]{border-color:var(--ink); background:#fff}
  .cols{display:flex; align-items:flex-end; justify-content:center; gap:10px; height:140px}
  .col{display:block; width:36px; transition:height .7s}
  .col.red{background:var(--red)} .col.blue{background:var(--blue)}
  .round-foot{display:flex; justify-content:space-between; margin-top:10px; font-family:var(--mono); font-size:13px}
  .round-foot .r{color:var(--red)} .round-foot .b{color:var(--blue)} .round-foot .n{font-weight:600}

  .closing{margin-top:88px; background:var(--ink); color:var(--paper)}
  .closing-in{max-width:1040px; margin:0 auto; padding:72px 24px 80px; display:grid; grid-template-columns:repeat(auto-fit,minmax(min(320px,100%),1fr)); gap:48px; align-items:start}
  .closing h2{margin:0; font-size:40px; line-height:1.1; font-weight:800; letter-spacing:-.01em}
  .closing p{margin:18px 0 0; font-size:18px; line-height:1.55; color:var(--night-text)}
  .ctas{display:flex; flex-wrap:wrap; gap:14px; margin-top:28px}
  .ctas a{display:inline-flex; align-items:center; min-height:48px; padding:0 24px; text-decoration:none; font-weight:600}
  .ctas .solid{background:var(--paper); color:var(--ink)}
  .ctas .ghost{border:2px solid var(--paper); color:var(--paper)}
  .ctas a:hover{background:var(--red); border-color:var(--red); color:#fff}
  .closing pre{margin:0; padding:22px; border:1px solid var(--soft); font-family:var(--mono); font-size:14px; line-height:1.7; overflow-x:auto; color:var(--paper)}
  .fine{max-width:1040px; margin:0 auto; padding:0 24px 40px; font-size:13px; color:var(--night-muted)}
</style>
</head>
<body>

<nav>
  <a href="/" class="brand">UFC API <span>unofficial · open source</span></a>
  <div class="nav-links">
    <a href="/docs">Docs</a>
    <a href="https://github.com/mostafa-amine/ufc-api" target="_blank" rel="noopener">GitHub</a>
    <a href="/docs" class="key-btn">Get a free key</a>
  </div>
</nav>

@if ($tape)
@php($full = $tape['periods'][0])
<header class="corners">
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
  <div class="verdict">
    <div class="kind">{{ $tape['decision'] }}</div>
    <div class="scores">{{ $tape['scores'] }}</div>
  </div>
</header>

<main class="body">
  <div class="meta">
    @foreach ($tape['meta'] as $item)<span>{{ $item }}</span>@endforeach
  </div>

  <div class="periods" role="group" aria-label="Show stats for">
    @foreach ($tape['periods'] as $i => $period)
      <button type="button" data-period="{{ $i }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}">{{ $period['label'] }}</button>
    @endforeach
  </div>

  <div class="rows">
    @foreach ($full['rows'] as $i => $row)
    <div data-row="{{ $i }}">
      <div class="row-head">
        <span class="num red-side {{ $row['red_leads'] ? 'lead' : '' }}">{{ $row['red'] }}</span>
        <span class="row-label">{{ $row['label'] }}<span class="row-note">{{ $row['note'] }}</span></span>
        <span class="num blue-side {{ $row['blue_leads'] ? 'lead' : '' }}">{{ $row['blue'] }}</span>
      </div>
      <div class="bars">
        <div class="track red-side"><div class="bar red" style="width: {{ $row['red_width'] }}%"></div></div>
        <div class="track"><div class="bar blue" style="width: {{ $row['blue_width'] }}%"></div></div>
      </div>
    </div>
    @endforeach
  </div>

  <section class="part">
    <h2>Where the strikes landed</h2>
    <p class="lede">Significant strikes landed, <span data-period-phrase>{{ $full['phrase'] }}</span>.</p>
    <div class="maps">
      @foreach ($full['maps'] as $m => $map)
      <div>
        <div class="map-title">{{ $map['title'] }}</div>
        @foreach ($map['corners'] as $c => $corner)
        <div data-map="{{ $m }}" data-corner="{{ $c }}">
          <div class="stack">
            @foreach ($corner['parts'] as $s => $part)
              <div class="seg {{ $corner['corner'] }} s{{ $s }}" style="flex: {{ $part['value'] ?: 0.0001 }} 1 0">{{ $part['short'] }}</div>
            @endforeach
          </div>
          <div class="legend">
            <b>{{ $corner['name'] }}</b>
            @foreach ($corner['parts'] as $part)<span>{{ $part['label'] }} {{ $part['value'] }}</span>@endforeach
          </div>
        </div>
        @endforeach
      </div>
      @endforeach
    </div>
  </section>

  <section class="part">
    <h2>Round by round</h2>
    <p class="lede">Significant strikes landed. Pick a round to see its numbers above.</p>
    <div class="round-chart">
      @foreach ($tape['rounds'] as $round)
      <button type="button" data-period="{{ $round['n'] }}" aria-pressed="false">
        <span class="cols">
          <span class="col red" style="height: {{ $round['red_height'] }}px"></span>
          <span class="col blue" style="height: {{ $round['blue_height'] }}px"></span>
        </span>
        <span class="round-foot"><span class="r">{{ $round['red'] }}</span><span class="n">Round {{ $round['n'] }}</span><span class="b">{{ $round['blue'] }}</span></span>
      </button>
      @endforeach
    </div>
  </section>
</main>
@endif

<section class="closing">
  <div class="closing-in">
    <div>
      <h2>Every number on this page came from one request.</h2>
      <p>Round-by-round stats, strike targets and positions, and judge scores mapped to the right corner.@if ($tape['ufcstats_printed'] ?? null) ufcstats prints this fight as {{ $tape['ufcstats_printed'] }}; we don't.@endif</p>
      <div class="ctas">
        <a href="/docs" class="solid">Get a free key</a>
        <a href="/docs" class="ghost">Read the docs</a>
      </div>
    </div>
@if ($tape)
@php($req = $tape['request'])
<pre>curl {{ url('/v1/fights/'.$tape['id']) }} \
  -H "Authorization: Bearer $KEY"

{
  "method": {{ json_encode($req['method']) }},
  "scorecards": [
    { "judge": {{ json_encode($req['judge']) }},
      "red": {{ json_encode($req['judge_red']) }}, "blue": {{ json_encode($req['judge_blue']) }} }, …
  ],
  "stats": { "red": {
    "total": { "control_time_sec": {{ json_encode($req['control_time_sec']) }}, … },
    "rounds": [
      { "sig_str": { "landed": {{ json_encode($req['round_one_sig']) }}, … },
        "targets": { "head": …, "body": …, "leg": … },
        … }, …
    ] }, … }
}</pre>
@else
{{-- No fight to quote yet, so show the request shape without any numbers. --}}
<pre>curl {{ url('/v1/fights') }}/{id} \
  -H "Authorization: Bearer $KEY"</pre>
@endif
  </div>
  <div class="fine">Not affiliated with the UFC or Zuffa LLC. Data from ufcstats.com.</div>
</section>

@if ($tape)
<script>
  (() => {
    const periods = @json($tape['periods']);
    const set = (i) => {
      const p = periods[i];
      document.querySelectorAll('[data-period]').forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.period) === i)));
      document.querySelector('[data-period-phrase]').textContent = p.phrase;
      p.rows.forEach((row, r) => {
        const el = document.querySelector(`[data-row="${r}"]`);
        const [red, blue] = el.querySelectorAll('.num');
        red.textContent = row.red; red.classList.toggle('lead', row.red_leads);
        blue.textContent = row.blue; blue.classList.toggle('lead', row.blue_leads);
        el.querySelector('.row-note').textContent = row.note;
        el.querySelector('.bar.red').style.width = row.red_width + '%';
        el.querySelector('.bar.blue').style.width = row.blue_width + '%';
      });
      p.maps.forEach((map, m) => map.corners.forEach((corner, c) => {
        const el = document.querySelector(`[data-map="${m}"][data-corner="${c}"]`);
        const segs = el.querySelectorAll('.seg');
        const legend = el.querySelectorAll('.legend span');
        corner.parts.forEach((part, s) => {
          segs[s].style.flex = `${part.value || 0.0001} 1 0`;
          segs[s].textContent = part.short;
          legend[s].textContent = `${part.label} ${part.value}`;
        });
      }));
    };
    document.querySelectorAll('[data-period]').forEach((b) => b.addEventListener('click', () => set(Number(b.dataset.period))));
  })();
</script>
@endif
</body>
</html>
