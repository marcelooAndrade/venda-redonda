@props(['status'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 px-2.5 py-1 overline '.$status->classesBadge()]) }}>
    <span class="size-1.5 shrink-0 bg-current" aria-hidden="true"></span>
    {{ $status->rotulo() }}
</span>
