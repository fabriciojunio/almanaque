<?php

// Diz ao PHPStan como montar o EntityManager, para ele conferir DQL e
// repositório de verdade em vez de aceitar qualquer string como consulta.

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel('test', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
