@props(['variant' => 'info', 'title' => null])

@php
    $variants = [
        'info' => 'border-steel-300 bg-steel-50 text-steel-800',
        'success' => 'border-success-300 bg-success-50 text-success-800',
        'warning' => 'border-ember-300 bg-ember-50 text-ember-800',
        'danger' => 'border-danger-300 bg-danger-50 text-danger-800',
    ];
@endphp

<div role="alert" {{ $attributes->merge(['class' => 'border-l-2 border p-3 text-sm '.($variants[$variant] ?? $variants['info'])]) }}>
    @if ($title)
        <p class="mb-1 font-semibold">{{ $title }}</p>
    @endif
    {{ $slot }}
</div>
