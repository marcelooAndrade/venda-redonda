@props([])

{{-- Tabela sempre em contêiner com rolagem própria, para o corpo da página
     nunca rolar na horizontal. --}}
<div class="overflow-x-auto">
    <table {{ $attributes->merge(['class' => 'w-full border-collapse text-sm']) }}>
        {{ $slot }}
    </table>
</div>
