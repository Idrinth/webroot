<?php
class VirtualHost {
    protected $hide = true;
    protected $idrinth = false;
    protected $specific_webroot = false;
    protected $name = '';
    protected $proxied = false;
    protected static $proxiedTemplate = '';
    protected static $sharedTemplate = '
    ServerAdmin webmaster@###EMAIL-DOMAIN###
    ###FULLDOMAIN######ALIAS###
    DocumentRoot /var/###DOC-ROOT###
    ScriptAlias /cgi-bin/ /usr/lib/cgi-bin/
    ErrorLog /var/log/###LOG-DOMAIN###-error.log
    LogLevel warn
    CustomLog /var/log/###LOG-DOMAIN###-access.log "%l %u %t \"%r\" %>s %b"
    <Directory />
        Options +FollowSymLinks
        AllowOverride None
        Require all denied
    </Directory>
    <Directory /var/###PATH###>
        Options +ExecCGI -Indexes +FollowSymLinks +MultiViews
        AllowOverride All
        Require all granted
    </Directory>
    <Directory "/usr/lib/cgi-bin">
        AllowOverride None
        Options +ExecCGI -MultiViews +SymLinksIfOwnerMatch
        Require all granted
    </Directory>
    '/*H2Direct ON'*/;
    protected static $noSsl = '
      Header set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
      Header set Content-Security-Policy upgrade-insecure-requests
      '/*'Protocols H2C h2c HTTP/1.1 http/1.1
      ProtocolsHonorOrder On'*/.'
      RewriteEngine On
      RewriteCond %{HTTPS} !=on
      RewriteRule (.*) https://###DOMAIN###$1 [R,L]';
    protected static $ssl = '
      Header set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
      Header set Content-Security-Policy upgrade-insecure-requests
      '/*Protocols HTTP/2 http/2 H2 h2 SPDY/3.1 spdy/3.1 HTTP/1.1 http/1.1
      ProtocolsHonorOrder On*/.'
      SSLEngine On
      SSLCertificateFile    /etc/ssl/certs/###DOMAIN###.crt
      SSLCertificateKeyFile /etc/ssl/private/###DOMAIN###.key
      SSLCertificateChainFile    /etc/ssl/certs/###DOMAIN###_chain.crt';
    protected static $httpTemplate = '<VirtualHost *:80>###SHARED######NO-SSL###
</VirtualHost>
<VirtualHost *:443>###SHARED######SSL###
</VirtualHost>' . "\n";
    public function __construct() {
        if(strlen($this->name) > 0) {
            $this->name = explode(',',$this->name);
        } else {
            $this->name = array('');
        }
        $this->idrinth = (bool) $this->idrinth;
        $this->hide = (bool) $this->hide;
        $this->specific_webroot = (bool) $this->specific_webroot;
        return $this;
    }
    protected function nameToDomain($list,$name,$domain) {
        $list[] = trim($name . '.' . $domain,'.');
        $list[] = str_replace('..','.','www.' . $name . '.' . $domain);
        return $list;
    }
    protected function getAliasList($fistOnly = false) {
        $alias = array();
        foreach($this->name as $name) {
            if($this->idrinth) {
                $alias['idrinth'] = $this->nameToDomain(!isset($alias['idrinth'])?array():$alias['idrinth'],$name,'idrinth.de');
            }
            if($fistOnly) {
                return array_shift(array_shift($alias));
            }
        }
        return $alias;
    }
    protected function handleCert($fulldomain, $path) {
            echo "\n".date('H:i:s')." starting $fulldomain\n";
            exec(
                "certbot --non-interactive"
                ." --expand"
                #." --apache"
                ." --quiet"
                #." --standalone certonly"
                ." --webroot certonly --webroot-path=/var/$path"
                ." --domains=$fulldomain"
                ." --agree-tos"
                ." --email eldrim@gmx.de"
            );
            if(is_file("/etc/letsencrypt/live/$fulldomain/cert.pem")) {
                echo date('H:i:s')." moving files for $fulldomain\n";
                $from = "/etc/letsencrypt/live/$fulldomain";
                $to = "/etc/ssl/certs/$fulldomain";
                echo exec("cp $from/cert.pem $to.crt");
                echo exec("cp $from/chain.pem {$to}_chain.crt");
                echo exec("cp $from/privkey.pem /etc/ssl/private/$fulldomain.key");
            } else {
                echo date('H:i:s')." no files for $fulldomain\n";
            }
            echo date('H:i:s')." finished $fulldomain\n";
    }
    protected function buildHostEntry($mainTemplate,$sharedTemplate,$sslTemplate,$noSslTemplate,$path,$fulldomain,$aliaList,$emailDomain,$logDomain) {
        if($fulldomain&&$fulldomain{0}!=='*'){
            $this->handleCert($fulldomain, $path);
        }
        $temp = str_replace('###SHARED###',$sharedTemplate . ($this->proxied?self::$proxiedTemplate:''),$mainTemplate);
        $temp = str_replace('###SSL###',$sslTemplate,$temp);
        $temp = str_replace('###NO-SSL###',$noSslTemplate,$temp);
        $temp = str_replace('###EMAIL-DOMAIN###',$emailDomain,$temp);
        $temp = str_replace('###FULLDOMAIN###',$fulldomain?'ServerName ' . $fulldomain:'',$temp);
        $temp = str_replace('###DOMAIN###',$fulldomain,$temp);
        $temp = str_replace('###LOG-DOMAIN###',$logDomain,$temp);
        $temp = str_replace('###PATH###',$path,$temp);
        $temp = str_replace('###DOC-ROOT###',$path.($this->specific_webroot ? '/public': ''),$temp);
        $alias = '';
        foreach($aliaList as $domain) {
            $alias .= "\n" . '    ServerAlias ' . $domain;
        }
        return str_replace('###ALIAS###',$alias,$temp);
    }
    protected function getPath($name) {
        return $name . '.idrinth.de';
    }
    public function getHostEntry() {
        $content = '';
        foreach($this->getAliasList() as $secondLevel => $domains) {
            $path = trim($this->getPath($this->name[0]),'.');
            foreach($domains as $mainDomain) {
                $content .= $this->buildHostEntry(
                        self::$httpTemplate,
                    self::$sharedTemplate,
                    self::$ssl,
                    self::$noSsl,
                    $path,
                    $mainDomain,
                    array(),
                    $secondLevel . '.de',
                    preg_replace('/\.de$/','',$path)
                );
            }
        }
        return $content;
    }
    public function getListElement() {
        if($this->hide) {
            return '';
        }
        return '<li><a href="https://' . $this->getAliasList(true) . '">' . $this->getAliasList(true) . '</a></li>';
    }
}

