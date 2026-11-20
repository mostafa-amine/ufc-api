@extends('layouts.site', ['title' => 'Log in'])

@push('head')
<style>
  .auth{max-width:440px; margin:24px auto 0}
  .auth form{margin-top:28px}
  .auth .btn{width:100%; margin-top:28px}
  .switch{margin-top:20px; font-size:15px; color:var(--soft)}
</style>
@endpush

@section('content')
<div class="auth">
  <h1>Log in</h1>
  <p class="lede">Manage your API keys.</p>

  <form method="POST" action="{{ route('login') }}" novalidate>
    @csrf
    <div class="field">
      <label for="email">Email</label>
      <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
      @error('email')<div class="error" id="email-error">{{ $message }}</div>@enderror
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
    </div>
    <label class="check" for="remember">
      <input id="remember" name="remember" type="checkbox" value="1">
      <span>Keep me logged in on this device</span>
    </label>
    <button type="submit" class="btn">Log in</button>
  </form>
  <p class="switch">No account yet? <a href="{{ route('register') }}">Get a free key</a></p>
</div>
@endsection
