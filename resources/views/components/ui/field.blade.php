@props(['label' => null, 'for' => null, 'hint' => null, 'error' => null, 'required' => false])

<div {{ $attributes->merge(['class' => 'grid gap-1.5']) }}>
    @if ($label)
        <label @if($for) for="{{ $for }}" @endif class="text-xs font-medium text-graphite-600">
            {{ $label }}@if($required)<span class="text-primary-600" aria-hidden="true"> *</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($error)
        <p class="text-xs text-danger-700">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-xs text-graphite-500">{{ $hint }}</p>
    @endif
</div>
