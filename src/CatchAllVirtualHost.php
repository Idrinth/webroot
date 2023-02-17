<?php

class CatchAllVirtualHost extends VirtualHost {
    protected $idrinth = true;
    protected $waaagh = true;
    protected $kronos = true;
    protected $yaws = true;
    protected $name = 'default';
    protected function buildHostEntry($mainTemplate,$sharedTemplate,$sslTemplate,$noSslTemplate,$path,$fulldomain,$aliasList,$emailDomain,$logDomain) {
        $aliasList[] = '*.'.$emailDomain;
        return parent::buildHostEntry(
                        $mainTemplate,$sharedTemplate,$sslTemplate,$noSslTemplate,'www',$fulldomain,$aliasList,$emailDomain,$logDomain);
    }
    protected function nameToDomain($list,$name,$domain) {
        $list[] = trim($name . '.' . $domain,'.');
        return $list;
    }
}
