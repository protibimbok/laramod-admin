<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }}</title>

        {{-- One call, so what the entries share is preloaded and styled once. The admin's own entry is the last one. --}}
        @viteReactRefresh
        @vite(['resources/css/app.css', ...$entries])

        @foreach ($urls as $url)
            <script type="module" src="{{ $url }}"></script>
        @endforeach
    </head>
    <body>
        <div id="admin" data-boot="{{ json_encode($boot) }}"></div>
    </body>
</html>
