@props(['type' => 'text', 'numeric' => false])

<input type="{{ $type }}"
    {{ $attributes->merge([
        'class' => 'min-h-[38px] w-full border border-graphite-300 bg-white px-3 text-sm text-graphite-900 '
            .'placeholder:text-graphite-400 focus:border-graphite-900 focus:outline focus:outline-2 '
            .'focus:outline-offset-2 focus:outline-primary-600 disabled:bg-graphite-50 disabled:text-graphite-500 '
            .($numeric ? 'text-right [font-variant-numeric:tabular-nums]' : ''),
    ]) }}>
