@props(['status'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 etiqueta '.$status->classesBadge()]) }}>
    <span class="size-1.5 shrink-0 rounded-full bg-current" aria-hidden="true"></span>
    {{ $status->rotulo() }}
</span>
