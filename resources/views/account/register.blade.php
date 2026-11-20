@extends('layouts.site', ['title' => 'Get a free key'])

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
  <h1>Get a free key</h1>
  <p class="lede">Make an account, then make as many as {{ \App\Models\User::MAX_KEYS }} keys from your dashboard. Free, 60 requests a minute.</p>

  <form method="POST" action="{{ route('register') }}" novalidate>
    @csrf
    <div class="field">
      <label for="email">Email</label>
      <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
      @error('email')<div class="error" id="email-error">{{ $message }}</div>@enderror
    </div>
    <div class="field">
      <label for="password">Password <span style="font-weight:400; color:var(--muted)">(8 characters or more)</span></label>
      <input class="input" id="password" name="password" type="password" autocomplete="new-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
      @error('password')<div class="error" id="password-error">{{ $message }}</div>@enderror
    </div>
    <div class="field">
      <label for="password_confirmation">Password again</label>
      <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
    </div>
    <label class="check" for="terms">
      <input id="terms" name="terms" type="checkbox" value="1" @checked(old('terms')) @error('terms') aria-invalid="true" aria-describedby="terms-error" @enderror>
      <span>I agree to the <a href="/terms" target="_blank">terms of use</a>, and I won't use the API or its data for gambling.</span>
    </label>
    @error('terms')<div class="error" id="terms-error">{{ $message }}</div>@enderror
    <button type="submit" class="btn red">Make my account</button>
  </form>
  <p class="switch">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
</div>
@endsection
