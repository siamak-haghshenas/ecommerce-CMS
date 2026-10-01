<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
    <head>
        @include('partials.spa-head')

        @vite('resources/js/admin/app.ts')
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
