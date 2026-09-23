<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,

        // Nome de teste é frase, não identificador de código: o relatório da
        // bateria é lido por gente, e "nao_resolve_sem_dizer_o_que_era" se lê
        // melhor que "naoResolveSemDizerOQueEra".
        'php_unit_method_casing' => false,

        // Ordena as importações e tira o que sobrou.
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
    ])
    ->setFinder($finder)
;
