@props(['ambiente'])

@if ($ambiente === \App\Enums\Fiscal\Ambiente::Homologacao)
    {{-- Ambiente errado é a falha mais cara de um emissor fiscal. O aviso ocupa
         a largura toda e some por completo em produção, para não virar ruído. --}}
    <div role="status"
         {{ $attributes->merge(['class' => 'bg-ember-400 px-4 py-1.5 text-center text-graphite-900']) }}>
        <span class="overline">
            Ambiente de homologação &middot; as notas emitidas aqui não têm valor fiscal
        </span>
    </div>
@endif
