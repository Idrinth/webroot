<?php

require_once __DIR__ . '/../src/autoload.php';

echo (new VirtualHostList())->show(false);

