<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('page-data')
    <script>window.__AUTH__ = @json(auth()->user()?->only(['id', 'name', 'email']));</script>
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased">
    <div id="app"></div>
</body>
</html>