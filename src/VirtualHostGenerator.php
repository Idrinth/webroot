<?php

namespace De\Idrinth\WebRoot;

use PDO;
use Twig\Environment;

class VirtualHostGenerator
{
    private PDO $database;
    private Environment $twig;
    public function __construct(PDO $database, Environment $twig)
    {
        $this->database = $database;
        $this->twig = $twig;
    }
    private function certificate(string $vhost, string $admin): bool
    {
        $from = "/etc/letsencrypt/live/$vhost";
        if (!is_file("$from/cert.pem") || filemtime("$from/cert.pem") < time() - 60*24*60*60) {
            exec(
                "certbot certonly"
                . " --non-interactive"
                . " --expand"
                . " --quiet "
                . "--standalone "
                . "--domains=$vhost"
                . " --agree-tos"
                . " --email $admin"
            );
            if(!is_file("$from/cert.pem")) {
                return false;
            }
        }
        return true;
    }
    private function buildHostList(\PDOStatement $statement, array &$virtualhosts, string $ip): void
    {
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $vhost = trim($row['name'] . '.' . $row['domain'], '.');
            echo "Handling $vhost\n";
            if (gethostbyname($vhost . '.') !== $ip) {
                continue;
            }
            if (!$this->certificate($vhost, $row['admin'])) {
                continue;
            }
            $aliases = [];
            echo "  Handling Alias www.$vhost\n";
            if (gethostbyname("www.$vhost.") !== $ip && $this->certificate("www.$vhost", $row['admin'])) {
                $aliases[] = "www.$vhost";
            }
            $stmt = $this->database->prepare('SELECT virtualhost_domain_alias.subdomain,domain.domain,domain.admin '
                    . 'FROM virtualhost_domain_alias '
                    . 'INNER JOIN domain ON domain.aid=virtualhost_domain_alias.domain '
                    . 'WHERE virtualhost_domain_alias.virtualhost=:id');
            $stmt->execute([':id' => $row['aid']]);
            foreach ($stmt->fetchAll() as $alias) {
                $domain = trim($alias['subdomain'] . '.' . $alias['domain'], '.');
                echo "  Handling Alias $domain\n";
                if (gethostbyname("$domain.") !== $ip && $this->certificate($domain, $alias['admin'])) {
                    $aliases[] = $domain;
                }
                echo "  Handling Alias www.$domain\n";
                if (gethostbyname("www.$domain.") !== $ip && $this->certificate("www.$domain", $alias['admin'])) {
                    $aliases[] = "www.$domain";
                }
            }
            $virtualhosts[] = [
                'domain' => $vhost,
                'vhost' => $vhost,
                'webroot' => $row['extra_webroot'] === '1' ? "/var/$vhost/public" : "/var/$vhost",
                'root' => "/var/$vhost",
                'admin' => $row['admin'],
                'aliases' => $aliases
            ];
            if (!is_dir('/var/' . $vhost)) {
                mkdir('/var/' . $vhost);
                chown('/var/' . $vhost, 'www-data');
            }
            if ($row['extra_webroot'] === '1' && !is_dir('/var/' . $vhost . '/public')) {
                mkdir('/var/' . $vhost . '/public');
                chown('/var/' . $vhost . '/public', 'www-data');
            }
        }
    }
    private function buildDefaultHostList(\PDOStatement $statement, array &$virtualhosts, string $ip): void
    {
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $vhost = 'default.' . $row['domain'];
            echo "Handling $vhost\n";
            if (gethostbyname($row['domain'] . '.') !== $ip) {
                continue;
            }
            if (gethostbyname($vhost . '.') !== $ip) {
                continue;
            }
            if (!$this->certificate($vhost, $row['admin'])) {
                continue;
            }
            $virtualhosts[] = [
                'domain' => $vhost,
                'vhost' => '*.' . $row['domain'],
                'webroot' => "/var/www/public",
                'root' => "/var/www",
                'admin' => $row['admin'],
            ];
        }
    }
    public function create()
    {
        exec("service apache2 stop");
        sleep(60);
        $hostname = gethostname();
        $ip = gethostbyname($hostname);
        $stmt = $this->database->prepare('SELECT virtualhost.aid,virtualhost.name,virtualhost.extra_webroot,domain.domain, domain.admin
FROM virtualhost
INNER JOIN server ON server.aid=virtualhost.server
INNER JOIN domain ON domain.aid=virtualhost.domain
WHERE server.hostname=:hostname');
        $stmt->execute([':hostname' => $hostname]);
        $virtualhosts = [];
        $this->buildHostList($stmt, $virtualhosts, $ip);
        $stmt = $this->database->prepare("SELECT domain.domain, domain.admin FROM domain");
        $stmt->execute();
        $defaulthosts = [];
        $this->buildDefaultHostList($stmt, $defaulthosts, $ip);
        file_put_contents(
            '/etc/apache2/sites-enabled/all.conf',
            $this->twig->render('config.twig', ['virtualhosts' => $virtualhosts, 'defaulthosts' => $defaulthosts])
        );
        exec("service apache2 start");
    }
}
