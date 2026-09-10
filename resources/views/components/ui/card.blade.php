@props(['title' => null, 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'border border-graphite-200 bg-white']) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-graphite-200 px-4 py-3">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="display-title text-xl text-graphite-900">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-graphite-500">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="p-4">{{ $slot }}</div>
</section>
