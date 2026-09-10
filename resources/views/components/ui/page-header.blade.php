@props(['title', 'eyebrow' => null, 'description' => null])

<header {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-4 border-b border-graphite-200 pb-4']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="etiqueta text-graphite-500">{{ $eyebrow }}</p>
        @endif
        <h1 class="display-title mt-1 text-3xl text-graphite-900">{{ $title }}</h1>
        @if ($description)
            <p class="mt-2 max-w-2xl text-sm text-graphite-600">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
