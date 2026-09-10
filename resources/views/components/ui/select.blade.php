@props([])

<select {{ $attributes->merge([
    'class' => 'min-h-[38px] w-full border border-graphite-300 bg-white px-3 text-sm text-graphite-900 '
        .'focus:border-graphite-900 focus:outline focus:outline-2 focus:outline-offset-2 '
        .'focus:outline-primary-600 disabled:bg-graphite-50',
]) }}>
    {{ $slot }}
</select>
