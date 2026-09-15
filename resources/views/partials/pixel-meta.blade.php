{{--
    Snippet padrão do pixel do Meta. Só inclui quem chama quando há
    `integracao.meta.pixel_id` configurado: sem token nenhum, o javascript
    do pixel nem entra na página. Ver MetaConversoesGateway, mesma regra do
    lado do servidor.

    $pixelId: id do pixel, sempre presente.
    $evento (opcional): ['nome' => ..., 'id' => ...] para disparar um evento
    além do PageView automático do init, com o mesmo eventID que o evento
    equivalente mandado pela Conversions API — é o que deixa o Meta juntar
    os dois em vez de contar a mesma conversão duas vezes.
--}}
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', {{ \Illuminate\Support\Js::from($pixelId) }});
fbq('track', 'PageView');
@isset($evento)
fbq('track', {{ \Illuminate\Support\Js::from($evento['nome']) }}, {}, {eventID: {{ \Illuminate\Support\Js::from($evento['id']) }}});
@endisset
</script>
<noscript>
    <img height="1" width="1" style="display:none" alt=""
         src="https://www.facebook.com/tr?id={{ urlencode($pixelId) }}&ev=PageView&noscript=1">
</noscript>
