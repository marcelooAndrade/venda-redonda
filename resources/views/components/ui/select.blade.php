@props([])

<select {{ $attributes->merge([
    'class' => 'min-h-[38px] min-w-0 rounded-md border border-graphite-300 bg-white px-3 text-sm text-graphite-900 shadow-xs '
        .'focus:border-primary-600 focus:outline-none focus:ring-2 '
        .'focus:ring-primary-600/25 disabled:bg-graphite-50',
]) }}>
    {{ $slot }}
</select>
