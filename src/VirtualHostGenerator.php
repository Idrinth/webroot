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
        $to = "/etc/ssl/certs/$vhost";
        exec("cp $from/cert.pem $to.crt");
        exec("cp $from/chain.pem {$to}_chain.crt");
        exec("cp $from/privkey.pem /etc/ssl/private/$vhost.key");
        return true;
    }
    private function buildHostList(\PDOStatement $statement, array &$virtualhosts, string $ip): void
    {
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $vhost = trim($row['name'] . '.' . $row['domain'], '.');
            if (gethostbyname($vhost) !== $ip) {
                continue;
            }
            if (!$this->certificate($vhost, $row['admin'])) {
                continue;
            }
            if (!$this->certificate("www.$vhost", $row['admin'])) {
                continue;
            }
            $virtualhosts[] = [
                'domain' => $vhost,
                'vhost' => $vhost,
                'webroot' => $row['extra_webroot'] === '1' ? "/var/$vhost/public" : "/var/$vhost",
                'root' => "/var/$vhost",
                'admin' => $row['admin'],
            ];
            $virtualhosts[] = [
                'domain' => $vhost,
                'vhost' => 'www.' . $vhost,
                'webroot' => $row['extra_webroot'] === '1' ? "/var/$vhost/public" : "/var/$vhost",
                'root' => "/var/$vhost",
                'admin' => $row['admin'],
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
            if (gethostbyname($row['domain']) !== $ip) {
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
        $hostname = gethostname();
        $ip = gethostbyname($hostname);
        $stmt = $this->database->prepare('SELECT virtualhost.name,virtualhost.extra_webroot,domain.domain, domain.admin
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
