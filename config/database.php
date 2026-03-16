<?php

  // Produção deve usar variáveis de ambiente; os valores abaixo servem apenas como fallback local.
  $dbServer = getenv('DB_SERVER') ?: getenv('DB_HOST') ?: 'localhost';
  $dbUser = getenv('DB_SERVER_USERNAME') ?: getenv('DB_USER') ?: 'root';
  $dbPassword = getenv('DB_SERVER_PASSWORD') ?: getenv('DB_PASSWORD') ?: '';
  $dbPort = getenv('DB_SERVER_PORT') ?: getenv('DB_PORT') ?: '';
  $dbName = getenv('DB_DATABASE') ?: getenv('DB_NAME') ?: 'infodoc_sisged';

  define('DB_SERVER', $dbServer); // eg, localhost - should not be empty for productive servers
  define('DB_SERVER_USERNAME', $dbUser);
  define('DB_SERVER_PASSWORD', $dbPassword);
  define('DB_SERVER_PORT', $dbPort);		
  define('DB_DATABASE', $dbName);