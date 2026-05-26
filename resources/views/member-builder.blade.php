<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Member Builder — {{ $produto['name'] ?? config('app.name') }}</title>
    @php
        $mbFavicon = data_get($produto, 'member_area_config.logos.favicon')
            ?: data_get($produto, 'member_area_config.pwa.favicon');
        if (is_string($mbFavicon) && $mbFavicon !== '' && ! str_starts_with($mbFavicon, 'http')) {
            $mbFavicon = str_starts_with($mbFavicon, '/') ? url($mbFavicon) : asset($mbFavicon);
        }
    @endphp
    @if(!empty($mbFavicon))
    <link rel="icon" href="{{ $mbFavicon }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ $mbFavicon }}">
    @else
    @php
        $mbFallbackFavicon = config('getfy.favicon_url') ?: config('getfy.pwa_icon_192') ?: config('getfy.app_logo_icon');
    @endphp
    @if(!empty($mbFallbackFavicon))
    <link rel="icon" href="{{ $mbFallbackFavicon }}" type="image/png">
    @endif
    @endif
    @vite(['resources/css/app.css', 'resources/js/member-builder.js'])
</head>
<body class="bg-zinc-100 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <div id="member-builder-app"></div>
    @php
        $memberBuilderData = [
            'produto' => $produto,
            'tenant_products' => $tenant_products ?? [],
            'app_url' => $app_url ?? rtrim(config('app.url'), '/'),
            'dns_target_host' => $dns_target_host ?? null,
            'dns_target_ip' => $dns_target_ip ?? null,
            'upload_limits' => $upload_limits ?? [
                'image_max_mb' => 10,
                'badge_max_mb' => 5,
                'pdf_max_mb' => 50,
            ],
        ];
    @endphp
    <script>
        window.__MEMBER_BUILDER__ = @json($memberBuilderData);
    </script>
</body>
</html>
