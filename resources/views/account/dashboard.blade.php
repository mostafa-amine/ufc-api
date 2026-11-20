@extends('layouts.site', ['title' => 'Your API keys'])

@push('head')
<style>
  .head{display:flex; flex-wrap:wrap; gap:16px 24px; justify-content:space-between; align-items:flex-end}
  .who{font-size:15px; color:var(--soft); display:flex; flex-wrap:wrap; gap:4px 16px; align-items:center}
  .flash{margin-top:28px; padding:14px 18px; border:2px solid var(--ok); color:var(--ok); background:#fff; font-weight:600}
  .new-key{margin-top:28px; background:var(--ink); color:var(--paper); padding:24px}
  .new-key strong{font-size:18px}
  .new-key p{margin:6px 0 0; color:#CFCAC0; line-height:1.5}
  .key-row{display:flex; flex-wrap:wrap; gap:10px; margin-top:16px}
  .key-value{flex:1 1 320px; min-width:0; min-height:48px; display:flex; align-items:center; padding:0 14px; background:#000; border:1px solid #3A3732; font-family:'IBM Plex Mono',monospace; font-size:15px; color:#fff; overflow-wrap:anywhere; user-select:all}
  .new-key .btn{background:var(--paper); color:var(--ink); border-color:var(--paper)}
  .new-key .btn:hover{background:var(--red); border-color:var(--red); color:#fff}
  section.block{margin-top:56px}
  .make{display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; margin-top:16px}
  .make .grow{flex:1 1 280px; min-width:0}
  .make .input{margin-top:6px}
  .keys{margin-top:20px; border-top:2px solid var(--ink)}
  .key{display:flex; flex-wrap:wrap; gap:8px 24px; align-items:center; justify-content:space-between; padding:16px 0; border-bottom:1px solid var(--rule)}
  .key .name{font-weight:600; font-size:17px; overflow-wrap:anywhere}
  .key .when{font-size:14px; color:var(--muted); margin-top:4px}
  .key .del{color:var(--red)}
  .empty{padding:24px 0; color:var(--soft); border-bottom:1px solid var(--rule)}
  .count{font-size:14px; color:var(--muted); margin-top:8px}
  pre.use{margin:16px 0 0; padding:18px; background:var(--ink); color:#CFCAC0; font-family:'IBM Plex Mono',monospace; font-size:14px; line-height:1.7; overflow-x:auto}
  @media(max-width:520px){pre.use{font-size:13px; white-space:pre-wrap; overflow-wrap:anywhere} .new-key{padding:18px}}
</style>
@endpush

@section('content')
<div class="head">
  <div>
    <h1>Your API keys</h1>
    <div class="who">
      <span>Logged in as <strong>{{ $user->email }}</strong></span>
      <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="link-btn">Log out</button></form>
    </div>
  </div>
  <a href="/docs" class="btn ghost">Read the docs</a>
</div>

@if (session('status'))
  <div class="flash" role="status">{{ session('status') }}</div>
@endif

@if ($newKey)
  <div class="new-key" role="status">
    <strong>Your new key “{{ $newKey['name'] }}”</strong>
    <p>Copy it now and keep it somewhere safe. You won't be able to see it again.</p>
    <div class="key-row">
      <div class="key-value" id="new-key-value">{{ $newKey['value'] }}</div>
      <button type="button" class="btn" data-copy="#new-key-value">Copy</button>
    </div>
  </div>
@endif

<section class="block">
  <h2>Make a key</h2>
  @if ($user->isBlocked())
    <p class="lede">This account was blocked for breaking the <a href="/terms">terms of use</a>, so it can't make keys.</p>
  @else
    <form method="POST" action="{{ route('keys.store') }}" class="make" novalidate>
      @csrf
      <div class="grow">
        <label for="name">Name it after where you'll use it</label>
        <input class="input" id="name" name="name" type="text" maxlength="60" placeholder="my fight stats app" value="{{ old('name') }}" required @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
      </div>
      <button type="submit" class="btn red">Make key</button>
    </form>
    @error('name')<div class="error" id="name-error">{{ $message }}</div>@enderror
  @endif
</section>

<section class="block">
  <h2>Your keys</h2>
  <div class="keys">
    @forelse ($keys as $key)
      <div class="key">
        <div>
          <div class="name">{{ $key->name }}</div>
          <div class="when">Made {{ $key->created_at->diffForHumans() }} · {{ $key->last_used_at ? 'Last used '.$key->last_used_at->diffForHumans() : 'Never used' }}</div>
        </div>
        <form method="POST" action="{{ route('keys.destroy', $key->id) }}" data-confirm="Delete “{{ $key->name }}”? Anything using it will stop working.">
          @csrf @method('DELETE')
          <button type="submit" class="link-btn del" aria-label="Delete {{ $key->name }}">Delete</button>
        </form>
      </div>
    @empty
      <div class="empty">No keys yet. Make one above.</div>
    @endforelse
  </div>
  <div class="count">{{ $keys->count() }} of {{ \App\Models\User::MAX_KEYS }} keys</div>
</section>

<section class="block">
  <h2>Use it</h2>
  <p class="lede">Send the key with every request.</p>
<pre class="use">curl "{{ url('/v1/fighters') }}?search=gane" \
  -H "Authorization: Bearer YOUR_KEY"</pre>
</section>
@endsection

@push('scripts')
<script>
  document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
    const text = document.querySelector(button.dataset.copy).textContent.trim();
    try { await navigator.clipboard.writeText(text); button.textContent = 'Copied'; }
    catch { getSelection().selectAllChildren(document.querySelector(button.dataset.copy)); button.textContent = 'Press Ctrl+C'; }
  }));
  document.querySelectorAll('form[data-confirm]').forEach((form) => form.addEventListener('submit', (e) => {
    if (!confirm(form.dataset.confirm)) e.preventDefault();
  }));
</script>
@endpush
