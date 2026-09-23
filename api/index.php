<?php

declare(strict_types=1);

/*
 * Ponto de entrada da publicação sem servidor (Vercel).
 *
 * Fica fora de uma pasta chamada api de propósito: a Vercel trata esse nome
 * como convenção e procura um arquivo por rota ali dentro, então /api/saude
 * respondia 404 antes de chegar na aplicação.
 *
 * O ponto de entrada de verdade continua sendo public/index.php, usado pelo
 * Nginx e pelo servidor embutido. Este arquivo existe porque na Vercel toda
 * requisição entra por uma função, e ela precisa de um arquivo próprio.
 *
 * As duas diferenças do ambiente: o disco é somente leitura fora de /tmp, e
 * a instância morre a qualquer momento. Por isso o cache do framework é
 * gerado na construção, o log vai para a saída de erro e a sessão e o
 * limitador de tentativas ficam no banco.
 */

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
