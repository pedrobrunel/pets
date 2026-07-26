<?php
/* =========================================================
   Bichoteca — instalador do banco (rodar uma vez só)
   Abre no navegador, cria a tabela "jogadores" se ela ainda
   não existir e pode ser apagado do servidor depois disso.
   ========================================================= */

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

if (!is_file(__DIR__ . '/config.php')) {
  http_response_code(500);
  exit('<p>Falta o api/config.php. Copie o config.example.php, preencha com os dados do hPanel e recarregue esta página.</p>');
}
require __DIR__ . '/config.php';

try {
  $pdo = new PDO(
    'mysql:host='.BD_HOST.';dbname='.BD_NOME.';charset=utf8mb4', BD_USER, BD_SENHA,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
  $pdo->exec('CREATE TABLE IF NOT EXISTS jogadores (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    apelido       VARCHAR(14)  NOT NULL UNIQUE,
    pin_hash      VARCHAR(255) NOT NULL,
    estado        JSON         NOT NULL,
    criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  echo '<p>✅ Banco pronto! A tabela "jogadores" foi criada (ou já existia — rodar de novo não faz mal).</p>'
     . '<p>Agora é só apagar este arquivo (api/install.php) do servidor: ele não precisa mais rodar.</p>';
} catch (Throwable $e) {
  http_response_code(500);
  echo '<p>Deu erro conectando ou criando a tabela:</p><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}
