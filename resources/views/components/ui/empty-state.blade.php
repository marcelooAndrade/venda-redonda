@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'border border-dashed border-graphite-300 bg-graphite-50 px-6 py-10 text-center']) }}>
    <p class="display-title text-lg text-graphite-700">{{ $title }}</p>
    @if ($description)
        <p class="mx-auto mt-2 max-w-md text-sm text-graphite-500">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-5 flex justify-center">{{ $action }}</div>
    @endisset
</div>
