{{-- `padded=false` para quando o filho já traz o próprio recuo,
     como a tabela: recuo duplo faz o conteúdo flutuar dentro do card. --}}
@props(['title' => null, 'subtitle' => null, 'padded' => true])

{{-- `min-w-0` não é enfeite: item de grid nasce com `min-width: auto`,
     e sem isso a tabela larga estoura o card em vez de rolar dentro
     dele, pondo a página inteira para rolar na horizontal. --}}
<section {{ $attributes->merge(['class' => 'min-w-0 rounded-lg border border-graphite-200/70 bg-white shadow-sm']) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-graphite-200/70 px-5 py-3.5">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="display-title text-xl text-graphite-900">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-graphite-500">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['p-5' => $padded])>{{ $slot }}</div>
</section>
