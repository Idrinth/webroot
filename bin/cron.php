<?php

require_once __DIR__ . '/../src/autoload.php';

exec("sh /etc/init.d/apache2 stop");
$start = microtime(true);
file_put_contents(
    '/etc/apache2/sites-enabled/all.conf',
    (new VirtualHostList())->show(true)
    );
sleep(15);
exec("sh /etc/init.d/apache2 restart");
die("\ndone, ".(microtime(true)-$start)."s\n\n" );