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

  // um registro por pergunta respondida (todas as tentativas, não só a última) —
  // alimenta as métricas de desempenho do painel e o resumo do responsável
  $pdo->exec('CREATE TABLE IF NOT EXISTS respostas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id      INT NOT NULL,
    licao_id        VARCHAR(24) NOT NULL,
    indice_pergunta INT NOT NULL,
    resposta        INT NOT NULL,
    acertou         TINYINT(1) NOT NULL,
    criado_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE,
    INDEX (licao_id), INDEX (jogador_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  // cada cena é um mapa navegável (uma imagem de fundo). "inicial" marca qual delas
  // abre quando o aluno toca em Trilhas; as outras são alcançadas por pontos tipo "cena"
  $pdo->exec('CREATE TABLE IF NOT EXISTS cenas (
    id            VARCHAR(24) PRIMARY KEY,
    nome          VARCHAR(60) NOT NULL,
    imagem        VARCHAR(160) NOT NULL,
    inicial       TINYINT(1) NOT NULL DEFAULT 0,
    publicado     TINYINT(1) NOT NULL DEFAULT 1,
    ordem         INT NOT NULL DEFAULT 0,
    criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  // pontos clicáveis de uma cena. posição/tamanho em % da imagem (não em pixel), pra
  // funcionar igual em qualquer tela. destino = par (tipo, destino) do catálogo de links
  $pdo->exec('CREATE TABLE IF NOT EXISTS pontos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    cena_id      VARCHAR(24) NOT NULL,
    rotulo       VARCHAR(60) NOT NULL,
    x            DECIMAL(6,3) NOT NULL,
    y            DECIMAL(6,3) NOT NULL,
    largura      DECIMAL(6,3) NOT NULL,
    altura       DECIMAL(6,3) NOT NULL,
    tipo         VARCHAR(12) NOT NULL,
    destino      VARCHAR(160) NOT NULL,
    mostrar_selo TINYINT(1) NOT NULL DEFAULT 1,
    mostrar_dica TINYINT(1) NOT NULL DEFAULT 0,
    publicado    TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (cena_id) REFERENCES cenas(id) ON DELETE CASCADE,
    INDEX (cena_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  // pasta das imagens enviadas pelo painel (as do repositório continuam em assets/)
  $pastaCenas = dirname(__DIR__) . '/assets/cenas';
  if (!is_dir($pastaCenas)) @mkdir($pastaCenas, 0755, true);

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

  /* semeia a "Ilha do saber" como primeira cena, com exatamente as posições que
     estavam fixas no código do app.html — assim quem já tem conteúdo no ar não perde
     nada e já começa com um mapa editável no painel. Roda independente da semeadura
     de conteúdo acima: banco antigo (com mundos) também ganha a cena. */
  $cenasExistentes = (int)$pdo->query('SELECT COUNT(*) FROM cenas')->fetchColumn();
  $pontosSemeados = 0;
  if ($cenasExistentes === 0) {
    $pdo->prepare('INSERT INTO cenas (id, nome, imagem, inicial, publicado, ordem) VALUES (?, ?, ?, 1, 1, 0)')
      ->execute(['ilha', 'Ilha do saber', 'mapa-mundosv2.webp']);
    // [rótulo, x, y, largura, altura, tipo, destino]
    $pontosIlha = [
      ['Português',       9, 22, 12, 25, 'mundo', 'por'],
      ['Matemática',     28, 13, 13, 30, 'mundo', 'mat'],
      ['Biologia',       40, 20, 10, 25, 'mundo', 'bio'],
      ['Física',         50, 15, 10, 28, 'mundo', 'fis'],
      ['História',       67, 15, 12, 28, 'mundo', 'his'],
      ['Geografia',      85, 15, 13, 28, 'mundo', 'geo'],
      ['Arte',           55, 45, 11, 28, 'mundo', 'art'],
      ['Literatura',     20, 15, 10, 28, 'aviso', 'Literatura ainda não tem lições — em breve! 🚧'],
      ['Química',        59, 13,  9, 28, 'aviso', 'Química ainda não tem lições — em breve! 🚧'],
      ['Filosofia',      20, 48, 11, 25, 'aviso', 'Filosofia ainda não tem lições — em breve! 🚧'],
      ['Sociologia',     30, 46, 11, 27, 'aviso', 'Sociologia ainda não tem lições — em breve! 🚧'],
      ['Língua Inglesa', 43, 42, 12, 32, 'aviso', 'Língua Inglesa ainda não tem lições — em breve! 🚧'],
      ['Educação Física',65, 48, 18, 25, 'aviso', 'Educação Física ainda não tem lições — em breve! 🚧'],
    ];
    $insPonto = $pdo->prepare('INSERT INTO pontos (cena_id, rotulo, x, y, largura, altura, tipo, destino, mostrar_selo)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($pontosIlha as $p) {
      $insPonto->execute(['ilha', $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[5] === 'mundo' ? 1 : 0]);
      $pontosSemeados++;
    }
  }

  echo '<p>✅ Banco pronto! Tabelas criadas (ou já existiam — rodar de novo não faz mal).</p>'
     . ($pontosSemeados
        ? '<p>🗺️ Cena "Ilha do saber" criada com ' . $pontosSemeados . ' pontos clicáveis — edite em <code>/admin.html</code>, aba Mapas.</p>'
        : '<p>🗺️ Cenas já existiam — nada foi semeado.</p>')
     . '<p>👤 Conta do painel: usuário <b>' . htmlspecialchars(ADMIN_USUARIO) . '</b>, com a senha que está em <code>config.php</code> agora.</p>'
     . ($semeados
        ? '<p>📚 ' . $semeados . ' lições de exemplo semeadas em ' . count($mundos) . ' mundos.</p>'
        : '<p>📚 Conteúdo já existia — nada foi semeado (o painel administrativo é quem manda a partir de agora).</p>')
     . '<p>Acesse <code>/admin.html</code> para entrar no painel. Depois é só apagar este arquivo (api/install.php) do servidor, ou deixá-lo — ele nunca sobrescreve conteúdo já existente.</p>';
} catch (Throwable $e) {
  http_response_code(500);
  echo '<p>Deu erro conectando ou criando o banco:</p><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}
