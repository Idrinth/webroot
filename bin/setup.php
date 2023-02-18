<?php

echo "Welcome to idrinth/webroot. Answer a few quick questions to have your system setup for use!\n\n";
echo "\nWhat is your mysql hostname?";
$hostname = trim(fgets(STDIN));
echo "\nWhat is your mysql username?";
$husername = trim(fgets(STDIN));
echo "\nWhat is your mysql password?";
$password = trim(fgets(STDIN));
echo "\nWhat is your mysql database name?";
$database = trim(fgets(STDIN));
file_put_contents(dirname(__DIR__) . '.env', "DB_DATABASE=$database
DB_USER=$user
DB_PASSWORD=$password
DB_HOST=$host");
$pdo = new PDO("mysql:dbname=$database;host=$host", $username, $password);
$pdo->exec("CREATE TABLE `domain` (
	`aid` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`domain` VARCHAR(255) NULL DEFAULT NULL COLLATE 'ascii_bin',
	`admin` VARCHAR(255) NOT NULL COLLATE 'ascii_bin',
	PRIMARY KEY (`aid`) USING BTREE,
	UNIQUE INDEX `domain` (`domain`) USING BTREE
)
COLLATE='ascii_bin'
ENGINE=InnoDB;");
$pdo->exec("CREATE TABLE `server` (
	`aid` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`hostname` VARCHAR(255) NOT NULL COLLATE 'ascii_bin',
	PRIMARY KEY (`aid`) USING BTREE,
	UNIQUE INDEX `hostname` (`hostname`) USING BTREE
)
COLLATE='ascii_bin'
ENGINE=InnoDB;");
$pdo->exec("CREATE TABLE `virtualhost` (
	`aid` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`hidden` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
	`extra_webroot` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
	`name` VARCHAR(255) NOT NULL COLLATE 'ascii_bin',
	`domain` INT(10) UNSIGNED NOT NULL,
	`server` INT(10) UNSIGNED NOT NULL,
	PRIMARY KEY (`aid`) USING BTREE,
	INDEX `domain` (`domain`) USING BTREE,
	INDEX `server` (`server`) USING BTREE,
	CONSTRAINT `domain` FOREIGN KEY (`domain`) REFERENCES `domain` (`aid`) ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT `server` FOREIGN KEY (`server`) REFERENCES `server` (`aid`) ON UPDATE NO ACTION ON DELETE CASCADE
)
COLLATE='ascii_bin'
ENGINE=InnoDB;");
$pdo->prepare("INSERT INTO server (hostname) VALUES (:hostname)")->execute(['hostname' => gethostname()]);
echo "\nDatabase created and current hostname added to servers.\n";