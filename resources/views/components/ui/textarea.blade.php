@props(['rows' => 4])

<textarea rows="{{ $rows }}" {{ $attributes->merge([
    'class' => 'min-w-0 rounded-md border border-graphite-300 bg-white p-3 text-sm text-graphite-900 shadow-xs '
        .'placeholder:text-graphite-400 focus:border-primary-600 focus:outline-none focus:ring-2 '
        .'focus:ring-primary-600/25',
]) }}>{{ $slot }}</textarea>
