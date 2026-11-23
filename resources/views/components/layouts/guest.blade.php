@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10 text-slate-900 antialiased">
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <p class="text-2xl font-semibold tracking-tight text-indigo-700">{{ config('app.name') }}</p>
            <p class="mt-1 text-sm text-slate-500">Support informatique interne</p>
        </div>

        <x-card>
            {{ $slot }}
        </x-card>
    </div>
</body>
</html>
