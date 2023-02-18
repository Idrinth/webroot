<?php

use De\Idrinth\WebRoot\VirtualHostGenerator;
use Dotenv\Dotenv;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require_once dirname(__DIR__) . '/src/autoload.php';

Dotenv::createImmutable(dirname(__DIR__));

(new VirtualHostGenerator(
    new PDO('mysql:dbname=' . $_ENV['DB_DATABASE'] . ';host=' . $_ENV['DB_HOST'], $_ENV['DB_PASSWORD'], $_ENV['DB_PASSWORD']),
    new Environment(new FilesystemLoader(dirname(__DIR__) . '/templates'))
))->create();