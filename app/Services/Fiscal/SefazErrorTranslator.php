<?php

namespace App\Services\Fiscal;

/**
 * Traduz a rejeição da SEFAZ em orientação prática.
 *
 * O xMotivo diz o que está errado, não o que fazer. Um operador de
 * faturamento lendo "Rejeicao: Duplicidade de NF-e com diferenca na chave
 * de acesso" não sabe se corrige a nota ou chama o suporte.
 */
class SefazErrorTranslator
{
    /** @var array<string, string> */
    private const DICAS = [
        '204' => 'Já existe uma NF-e autorizada com esta mesma chave. Confira se a nota não foi transmitida antes.',
        '206' => 'Este número de nota já foi usado nesta série. A numeração pode estar dessincronizada com a SEFAZ.',
        '217' => 'A SEFAZ não encontrou esta NF-e. Se você esperava que ela existisse, ela não chegou a ser recebida.',
        '225' => 'O XML não passou na validação estrutural. Normalmente falta um campo obrigatório ou um valor está fora do formato.',
        '228' => 'A data de emissão está muito distante. Confira o relógio do servidor e a data informada na nota.',
        '229' => 'Inscrição Estadual do emitente inválida. Confira o cadastro do emitente.',
        '236' => 'A chave de acesso tem dígito verificador ou composição inválida. Isso costuma indicar divergência entre CNPJ, série ou número.',
        '239' => 'A versão do leiaute não é aceita. Confira a configuração do schema em config/fiscal.php.',
        '297' => 'A assinatura não confere com o certificado do emitente. Confira se o certificado ativo é o do CNPJ correto.',
        '301' => 'O emitente está irregular perante o fisco. A nota foi denegada e não pode ser corrigida.',
        '302' => 'O destinatário está irregular perante o fisco. A nota foi denegada e não pode ser corrigida.',
        '509' => 'Inscrição Estadual do destinatário inválida. Confira o cadastro, ou marque o destinatário como isento.',
        '510' => 'O destinatário não pode ser contribuinte isento com Inscrição Estadual preenchida. Ajuste o indicador de IE no cadastro.',
        '539' => 'Já existe nota com este número, mas com chave diferente. Não retransmita: confira na SEFAZ qual delas vale.',
        '598' => 'A NF-e foi emitida em contingência sem justificativa ou sem data. Preencha os dois antes de reenviar.',
        '610' => 'O total da nota não bate com a soma dos itens. Recalcule os totais antes de reenviar.',
        '656' => 'Consumo indevido: houve consultas demais em pouco tempo. Aguarde uma hora antes de tentar de novo.',
        '999' => 'Erro no processamento da SEFAZ. Não é problema da nota: tente novamente em alguns minutos.',
    ];

    public function dica(string $cStat): ?string
    {
        return self::DICAS[$cStat] ?? null;
    }

    public function mensagemCompleta(string $cStat, string $xMotivo): string
    {
        $dica = $this->dica($cStat);

        return $dica === null
            ? "Rejeição {$cStat}: {$xMotivo}"
            : "Rejeição {$cStat}: {$xMotivo}\n\n{$dica}";
    }
}
