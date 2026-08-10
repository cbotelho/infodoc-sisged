<?php

  // Produção deve usar variáveis de ambiente; os valores abaixo servem apenas como fallback local.
  $dbServer = getenv('DB_SERVER') ?: getenv('DB_HOST') ?: 'localhost';
  $dbUser = getenv('DB_SERVER_USERNAME') ?: getenv('DB_USER') ?: 'root';
  $dbPassword = getenv('DB_SERVER_PASSWORD') ?: getenv('DB_PASSWORD') ?: '';
  $dbPort = getenv('DB_SERVER_PORT') ?: getenv('DB_PORT') ?: '';
  $dbName = getenv('DB_DATABASE') ?: getenv('DB_NAME') ?: 'infodoc_sisged';

  // --- MULTI-TENANT ROUTER ---
  // Identifica o tenant pelo dominio de acesso (atras de proxy ou direto)
  $httpHost = $_SERVER['HTTP_X_FORWARDED_HOST']
      ?? $_SERVER['HTTP_X_ORIGINAL_HOST']
      ?? $_SERVER['HTTP_HOST']
      ?? '';

  // Fallback caso o Forwarded Host venha como lista (ex: "cipemac.com, gea.com")
  if (strpos($httpHost, ',') !== false) {
      $hosts = explode(',', $httpHost);
      $httpHost = trim($hosts[0]);
  }

  $httpHost = strtolower(trim((string) $httpHost));

  // Remove porta para evitar variacoes como "host:443".
  if (strpos($httpHost, ':') !== false) {
      $httpHost = explode(':', $httpHost, 2)[0];
  }

  $tenantHeader = strtolower(trim((string) ($_SERVER['HTTP_X_TENANT_DB'] ?? '')));
  $isCipemac = $tenantHeader === 'cipemac' || strpos($httpHost, 'cipemac') !== false;

  if ($isCipemac) {
      $dbName = 'sisged_cipemac';
      // Força o bucket e variáveis para o ambiente CIPEMAC
      putenv('FILE_STORAGE_R2_BUCKET=cipemac');
      $_ENV['FILE_STORAGE_R2_BUCKET'] = 'cipemac';
      $_SERVER['FILE_STORAGE_R2_BUCKET'] = 'cipemac';
      
      // Se precisar de url base diferente nas classes (ex emails)
      putenv('APP_BASE_URL=https://cipemac.infodocsisged.com.br');
      $_ENV['APP_BASE_URL'] = 'https://cipemac.infodocsisged.com.br';
  } else {
      // Força o bucket e variáveis para o ambiente GEA quando o domínio NÃO for cipemac
      putenv('FILE_STORAGE_R2_BUCKET=gea');
      $_ENV['FILE_STORAGE_R2_BUCKET'] = 'gea';
      $_SERVER['FILE_STORAGE_R2_BUCKET'] = 'gea';

      putenv('APP_BASE_URL=https://gea.infodocsisged.com.br');
      $_ENV['APP_BASE_URL'] = 'https://gea.infodocsisged.com.br';
  }
  // --- FIM DA ROTA ---

  define('DB_SERVER', $dbServer); // eg, localhost - should not be empty for productive servers
  define('DB_SERVER_USERNAME', $dbUser);
  define('DB_SERVER_PASSWORD', $dbPassword);
  define('DB_SERVER_PORT', $dbPort);		
  define('DB_DATABASE', $dbName);