@extends('layouts.hris-auth')
@section('title','Single Sign-In')
@section('content')
<form method="POST" action="{{ route('login.store') }}">@csrf<div class="row g-3"><x-hris.field name="email" type="email" :value="old('email')" class="col-12" required autocomplete="email" autofocus/><x-hris.field name="password" type="password" class="col-12" required autocomplete="current-password"/></div><div class="form-check my-3"><input type="checkbox" name="remember" id="remember" class="form-check-input"><label class="form-check-label" for="remember">Remember me</label></div><button type="submit" class="btn btn-primary w-100" data-test="login-button">Sign in</button></form><a href="{{ route('password.request') }}" class="d-block text-center mt-3 small">Forgot password / set up password</a><p class="small text-body-secondary text-center mt-3 mb-0">Accounts are provisioned by HR. Contact HR if you need access.</p>
@endsection
@push('scripts')
@if(\Laravel\Fortify\Features::enabled(\Laravel\Fortify\Features::passkeys()))
@vite('resources/js/passkeys.js')
<script>
window.addEventListener('passkeys:ready',()=>{if(!window.Passkeys?.isSupported())return;const form=document.querySelector('form'),button=document.createElement('button'),error=document.createElement('p');button.type='button';button.className='btn btn-outline-primary w-100 mt-3';button.textContent='Sign in with a passkey';error.className='small text-danger mt-2';error.setAttribute('role','alert');form.after(button,error);button.onclick=async()=>{button.disabled=true;error.textContent='';try{const response=await window.Passkeys.verify({routes:{options:@json(route('passkey.login-options')),submit:@json(route('passkey.login'))}});location.href=response.redirect||'/dashboard'}catch(e){if(e.constructor?.name!=='UserCancelledError')error.textContent='Passkey sign-in failed. Please try again or use your password.'}finally{button.disabled=false}}});
</script>
@endif
@endpush
