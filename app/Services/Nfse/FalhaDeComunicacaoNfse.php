<?php

namespace App\Services\Nfse;

use RuntimeException;

/**
 * Não deu para falar com o provedor, ou ele respondeu algo que não é uma
 * resposta: rede, timeout, HTTP fora de 2xx, PDF que não é PDF.
 *
 * É distinta da rejeição de propósito: rejeição é o provedor dizendo não;
 * isto é não saber o que o provedor disse.
 */
class FalhaDeComunicacaoNfse extends RuntimeException {}
