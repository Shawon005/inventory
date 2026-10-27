<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="test-only-token"><title>@yield('title')</title><link rel="stylesheet" href="{{ asset('css/tailwind/app.css') }}"><link rel="stylesheet" href="{{ asset('css/vendor.css') }}">@yield('css')<link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body class="tw-bg-gray-100 tw-text-gray-900" style="font-family:Arial,sans-serif;padding:16px"><main>@yield('content')</main><script src="{{ asset('js/vendor.js') }}"></script>
@if(request()->is('b2b', 'b2b/*'))
<script>
// Reproduce app.js's shared ready-time initialization before module scripts.
$(function () { $('.select2').select2(); });
</script>
@endif
@yield('javascript')</body></html>
