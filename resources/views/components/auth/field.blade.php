@props(['name', 'label', 'type' => 'text', 'value' => '', 'error' => '', 'autocomplete' => null, 'required' => true, 'autofocus' => false])

{{--
  One input. `label` is a real <label> (screen-reader only) because the design uses placeholders.
  type="password" automatically gets the show/hide eye button and a Caps-Lock hint.
--}}
<div class="au-field" @if ($type === 'password') x-data="{ show: false }" @endif>
    <label for="{{ $name }}" class="sr-only">{{ $label }}</label>
    <input
        id="{{ $name }}" name="{{ $name }}"
        type="{{ $type }}"
        @if ($type === 'password') x-bind:type="show ? 'text' : 'password'" x-on:keyup="capsCheck($event)" x-on:keydown="capsCheck($event)" x-on:blur="caps = false" @endif
        value="{{ $type === 'password' ? '' : $value }}"
        placeholder="{{ $label }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($required) required @endif
        @if ($autofocus) autofocus @endif
        class="au-input {{ $type === 'password' ? 'au-has-btn' : '' }}"
        @if ($error) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
    >
    @if ($type === 'password')
        <button type="button" class="au-eye" x-on:click="show = !show" x-bind:aria-label="show ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'" x-bind:aria-pressed="show">
            <svg x-show="!show" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg x-show="show" x-cloak width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18"/><path d="M10.6 5.1A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.2M6.5 6.6C3.7 8.4 2 12 2 12s3.6 7 10 7a9.9 9.9 0 0 0 4.3-1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
        </button>
    @endif
    @if ($error)
        <p id="{{ $name }}-error" class="au-err" role="alert">{{ $error }}</p>
    @endif
    @if ($type === 'password')
        <p class="au-caps" x-show="caps" x-cloak role="status">{{ __('Caps Lock is on') }}</p>
    @endif
</div>
