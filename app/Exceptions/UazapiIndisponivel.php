<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Sem configuração da uazapi, ou a uazapi recusou/falhou a chamada. O
 * controller converte isso num 503 para o cliente da API, em vez de deixar
 * a exceção crua vazar como 500.
 */
class UazapiIndisponivel extends RuntimeException {}
