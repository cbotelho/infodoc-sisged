<?php

  // Produção deve usar variáveis de ambiente; os valores abaixo servem apenas como fallback local.
  $dbServer = getenv('DB_SERVER') ?: getenv('DB_HOST') ?: 'localhost';
  $dbUser = getenv('DB_SERVER_USERNAME') ?: getenv('DB_USER') ?: 'root';
  $dbPassword = getenv('DB_SERVER_PASSWORD') ?: getenv('DB_PASSWORD') ?: '';
  $dbPort = getenv('DB_SERVER_PORT') ?: getenv('DB_PORT') ?: '';
  $dbName = getenv('DB_DATABASE') ?: getenv('DB_NAME') ?: 'infodoc_sisged';

  // --- MULTI-TENANT ROUTER ---
  // Identifica o tenant pelo domínio de acesso
  $httpHost = $_SERVER['HTTP_HOST'] ?? '';

  if (strpos($httpHost, 'cipemac.infodocsisged.com.br') !== false) {
      $dbName = 'sisged_cipemac';
      // Força o bucket e variáveis para o ambiente CIPEMAC
      putenv('FILE_STORAGE_R2_BUCKET=cipemac');
      $_ENV['FILE_STORAGE_R2_BUCKET'] = 'cipemac';
      
      // Se precisar de url base diferente nas classes (ex emails)
      putenv('APP_BASE_URL=https://cipemac.infodocsisged.com.br');
      $_ENV['APP_BASE_URL'] = 'https://cipemac.infodocsisged.com.br';
  } elseif (strpos($httpHost, 'gea') !== false || $httpHost === 'seu-dominio-gea.com.br') { // ajuste com o dominio real do GEA
      // Pode omitir pois as envs do Docker default (GEA) cuidam disso
      // ou explicitar: $dbName = 'infodoc_gea';
  }
  // --- FIM DA ROTA ---

  define('DB_SERVER', $dbServer); // eg, localhost - should not be empty for productive servers
  define('DB_SERVER_USERNAME', $dbUser);
  define('DB_SERVER_PASSWORD', $dbPassword);
  define('DB_SERVER_PORT', $dbPort);		
  define('DB_DATABASE', $dbName);