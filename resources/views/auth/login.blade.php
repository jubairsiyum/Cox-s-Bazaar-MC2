{{--
  Login page. Must stay light: no DB / auth() calls here (only the form + session errors).
  Backend contract (standard Laravel): POST route('login') with email, password, remember (+ @csrf).
  Adapt `route('login')` / `route('password.request')` / input names to the project's auth stack
  (Breeze, Fortify, Jetstream, custom). Do NOT change the visual structure.
--}}
<x-auth.layout :title="__('Login')">
    <section class="au-card au-enter" style="--i:0" aria-labelledby="au-title">
        <header class="au-head">
            <a href="{{ url('/') }}" class="au-logo" aria-label="{{ config('app.name', "Cox's Bazaar MC2") }}"><x-auth.logo /></a>
            {{-- Dark-mode toggle: uses the project's existing Alpine theme store. Remove if the project has none. --}}
            <button type="button" class="au-iconbtn" x-data x-on:click="$store.theme && $store.theme.toggle()" aria-label="{{ __('Toggle dark mode') }}">
                <svg class="dark:hidden" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                <svg class="hidden dark:block" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            </button>
        </header>

        <div class="au-grid2">
            <div class="au-formcol au-enter" style="--i:2">
                <h1 id="au-title" class="au-title">{{ __('Login to your account') }}</h1>
                <p class="au-sub">{{ __('Welcome back! Track every shipment in one place.') }}</p>

                @if (session('status'))
                    <p class="au-alert" role="status">{{ session('status') }}</p>
                @endif

                <form method="POST" action="{{ route('login') }}" class="au-form" data-error="{{ $errors->any() ? 'true' : 'false' }}"
                      x-data="loginForm" x-on:submit="submit($event)">
                    @csrf
                    <x-auth.field name="email" type="email" :label="__('Your Email')" :value="old('email')" :error="$errors->first('email')" autocomplete="username" :autofocus="true" />
                    <x-auth.field name="password" type="password" :label="__('Password')" :error="$errors->first('password')" autocomplete="current-password" />

                    <div class="au-row">
                        <label class="au-check"><input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}> {{ __('Remember me') }}</label>
                        @if (Route::has('password.request'))
                            <a class="au-link" href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
                        @endif
                    </div>

                    <button type="submit" class="au-btn" x-bind:disabled="busy" x-bind:aria-busy="busy">
                        <span class="au-spinner" x-show="busy" x-cloak aria-hidden="true"></span>
                        <span x-text="busy ? '{{ __('Signing in…') }}' : '{{ __('Log in') }}'">{{ __('Log in') }}</span>
                    </button>
                </form>
            </div>

            <div class="au-illus au-par" style="--dx:-3;--dy:-2" aria-hidden="true">
                <x-auth.illustration />
            </div>
        </div>

        <footer class="au-foot au-enter" style="--i:5">
            <span>© {{ now()->year }} {{ config('app.name', "Cox's Bazaar MC2") }}. {{ __('All rights reserved.') }}</span>
            <span class="flex gap-5"><a href="#">{{ __('Privacy Policy') }}</a><a href="#">{{ __('Terms of Service') }}</a></span>
        </footer>
    </section>
</x-auth.layout>
