<?php

class BaseVirtualHost extends VirtualHost {
    protected static $noSsl = '
    RewriteEngine On
    RewriteRule (.*) https://default.idrinth.de$1 [R,L]';
    protected static $ssl = '
    SSLEngine Off
    RewriteEngine On
    RewriteRule (.*) https://default.idrinth.de$1 [R,L]';
    public function getHostEntry() {
        return preg_replace(
                "/\n\s*\n/","\n",$this->buildHostEntry(
                        self::$httpTemplate,self::$sharedTemplate,self::$ssl,self::$noSsl,'www','',array(),'idrinth.de','default.all'
        ));
    }
}