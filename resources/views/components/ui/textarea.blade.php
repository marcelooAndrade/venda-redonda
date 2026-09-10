@props(['rows' => 4])

<textarea rows="{{ $rows }}" {{ $attributes->merge([
    'class' => 'w-full border border-graphite-300 bg-white p-3 text-sm text-graphite-900 '
        .'placeholder:text-graphite-400 focus:border-graphite-900 focus:outline focus:outline-2 '
        .'focus:outline-offset-2 focus:outline-primary-600',
]) }}>{{ $slot }}</textarea>
