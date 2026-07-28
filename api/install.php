<?php
/* =========================================================
   Bichoteca — instalador do banco (rodar uma vez, e de novo
   sempre que quiser trocar a senha do painel administrativo)
   Abre no navegador, cria as tabelas que ainda não existem,
   semeia o conteúdo de exemplo e (re)grava a conta do painel
   a partir de ADMIN_USUARIO/ADMIN_SENHA em config.php.
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

  $pdo->exec('CREATE TABLE IF NOT EXISTS admins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    usuario    VARCHAR(40)  NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    criado_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  $pdo->exec('CREATE TABLE IF NOT EXISTS mundos (
    id            VARCHAR(24) PRIMARY KEY,
    nome          VARCHAR(60) NOT NULL,
    emoji         VARCHAR(8)  NOT NULL,
    cor           VARCHAR(30) NOT NULL,
    ordem         INT NOT NULL DEFAULT 0,
    publicado     TINYINT(1)  NOT NULL DEFAULT 1,
    criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  $pdo->exec('CREATE TABLE IF NOT EXISTS licoes (
    id            VARCHAR(24) PRIMARY KEY,
    mundo_id      VARCHAR(24) NOT NULL,
    titulo        VARCHAR(120) NOT NULL,
    emoji         VARCHAR(8)  NOT NULL,
    serie         VARCHAR(30) NOT NULL,
    ordem         INT NOT NULL DEFAULT 0,
    publicado     TINYINT(1)  NOT NULL DEFAULT 1,
    blocos        JSON NOT NULL,
    gabarito      JSON NOT NULL,
    criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mundo_id) REFERENCES mundos(id) ON DELETE CASCADE,
    INDEX (mundo_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  // conta do painel — roda de novo pra trocar a senha (edite config.php e recarregue esta página)
  $st = $pdo->prepare('INSERT INTO admins (usuario, senha_hash) VALUES (?, ?)
    ON DUPLICATE KEY UPDATE senha_hash = VALUES(senha_hash)');
  $st->execute([ADMIN_USUARIO, password_hash(ADMIN_SENHA, PASSWORD_DEFAULT)]);

  // semeia o conteúdo de exemplo só na primeira vez (banco de mundos vazio) —
  // depois disso quem manda é o painel administrativo, instalador não pisa em cima
  $mundosExistentes = (int)$pdo->query('SELECT COUNT(*) FROM mundos')->fetchColumn();
  $semeados = 0;
  if ($mundosExistentes === 0 && is_file(__DIR__ . '/seed-conteudo.json')) {
    $mundos = json_decode((string)file_get_contents(__DIR__ . '/seed-conteudo.json'), true) ?? [];
    $insMundo = $pdo->prepare('INSERT INTO mundos (id, nome, emoji, cor, ordem) VALUES (?, ?, ?, ?, ?)');
    $insLicao = $pdo->prepare('INSERT INTO licoes (id, mundo_id, titulo, emoji, serie, ordem, blocos, gabarito) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($mundos as $ordemMundo => $m) {
      $insMundo->execute([$m['id'], $m['nome'], $m['emoji'], $m['cor'], $ordemMundo]);
      foreach ($m['licoes'] as $ordemLicao => $l) {
        $gabarito = array_values(array_map(
          fn($b) => $b['certa'],
          array_filter($l['blocos'], fn($b) => $b['tipo'] === 'pergunta')
        ));
        $insLicao->execute([
          $l['id'], $m['id'], $l['titulo'], $l['emoji'], $l['serie'], $ordemLicao,
          json_encode($l['blocos'], JSON_UNESCAPED_UNICODE),
          json_encode($gabarito, JSON_UNESCAPED_UNICODE),
        ]);
        $semeados++;
      }
    }
  }

  echo '<p>✅ Banco pronto! Tabelas criadas (ou já existiam — rodar de novo não faz mal).</p>'
     . '<p>👤 Conta do painel: usuário <b>' . htmlspecialchars(ADMIN_USUARIO) . '</b>, com a senha que está em <code>config.php</code> agora.</p>'
     . ($semeados
        ? '<p>📚 ' . $semeados . ' lições de exemplo semeadas em ' . count($mundos) . ' mundos.</p>'
        : '<p>📚 Conteúdo já existia — nada foi semeado (o painel administrativo é quem manda a partir de agora).</p>')
     . '<p>Acesse <code>/admin.html</code> para entrar no painel. Depois é só apagar este arquivo (api/install.php) do servidor, ou deixá-lo — ele nunca sobrescreve conteúdo já existente.</p>';
} catch (Throwable $e) {
  http_response_code(500);
  echo '<p>Deu erro conectando ou criando o banco:</p><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}
