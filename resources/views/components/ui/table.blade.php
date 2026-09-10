@props([])

{{-- Tabela sempre em contêiner com rolagem própria, para o corpo da página
     nunca rolar na horizontal. --}}
<div class="overflow-x-auto">
    <table {{ $attributes->merge(['class' => 'w-full border-collapse text-sm [&_tbody_tr]:transition-colors [&_tbody_tr:hover]:bg-graphite-50']) }}>
        {{ $slot }}
    </table>
</div>
