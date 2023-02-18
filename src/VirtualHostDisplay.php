<?php

namespace De\Idrinth\WebRoot;

use PDO;
use Twig\Environment;

class VirtualHostDisplay
{
    private PDO $database;
    private Environment $twig;
    public function __construct(PDO $database, Environment $twig)
    {
        $this->database = $database;
        $this->twig = $twig;
    }
    public function display(): string
    {
        $stmt = $this->database->prepare('SELECT virtualhost.name,domain.domain
FROM virtualhost
INNER JOIN domain ON domain.aid=virtualhost.domain
WHERE NOT virtualhost.hidden');
        $stmt->execute();
        return $this->twig->render('web.twig', ['hosts' => array_map(function(array $data) {
            return trim($data['name'] . '.' .$data['domain'], '.');
        }, $stmt->fetchAll(PDO::FETCH_ASSOC))]);
    }
}
