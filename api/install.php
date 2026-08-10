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
require_once __DIR__ . '/bd.php'; // só pra normalizarTexto() no seed da lista de palavras proibidas, abaixo

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

  // segurança de login: tentativas_falhas + bloqueado_ate implementam um bloqueio
  // temporário contra força bruta (usleep sozinho não segura tentativas em paralelo);
  // versao_sessao invalida sessões antigas quando a senha é trocada pelo painel — chegaram
  // depois de "jogadores" já existir em produção, mesma checagem por INFORMATION_SCHEMA
  // usada nas outras colunas novas deste arquivo
  $colunasJogadores = array_column($pdo->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jogadores'"
  )->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
  foreach ([
    'tentativas_falhas' => 'SMALLINT NOT NULL DEFAULT 0',
    'bloqueado_ate'      => 'DATETIME NULL DEFAULT NULL',
    'versao_sessao'      => 'INT NOT NULL DEFAULT 0',
  ] as $coluna => $definicao) {
    if (!in_array($coluna, $colunasJogadores, true)) {
      $pdo->exec("ALTER TABLE jogadores ADD COLUMN $coluna $definicao");
    }
  }

  // presentes pendentes: quem manda só mexe na própria linha de "jogadores" (decrementa
  // a mochila); o item entra numa fila aqui até o destinatário abrir o jogo de novo, e é
  // só o próprio destinatário (dono da linha dele) quem aplica o +1 e apaga a fila — assim
  // nunca tem duas escritas concorrentes na mesma linha de "jogadores" por causa de um presente.
  $pdo->exec('CREATE TABLE IF NOT EXISTS presentes (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    destinatario_id   INT NOT NULL,
    item_chave        VARCHAR(64) NOT NULL,
    remetente_apelido VARCHAR(14) NOT NULL,
    criado_em         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (destinatario_id) REFERENCES jogadores(id) ON DELETE CASCADE,
    INDEX (destinatario_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  // lista de palavras proibidas (editável pelo Hostmaster no painel, aba Moderação) — usada
  // pra barrar mensagens/posts do fórum antes de gravar. "normalizada" já vem sem acento e
  // em minúsculo, pra comparar rápido sem reprocessar a lista toda em toda mensagem
  $pdo->exec('CREATE TABLE IF NOT EXISTS palavras_proibidas (
    palavra      VARCHAR(60) PRIMARY KEY,
    normalizada  VARCHAR(60) NOT NULL,
    criado_em    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (normalizada)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  // amizade só vale depois que os DOIS lados aceitam — sem isso não dá pra mandar mensagem
  // privada (ver "mensagens" abaixo). "pendente" é o pedido esperando resposta; vira "aceita"
  // quando o destinatário aceita, ou a linha some se ele recusar/desfizer depois
  $pdo->exec('CREATE TABLE IF NOT EXISTS amizades (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    solicitante_id   INT NOT NULL,
    destinatario_id  INT NOT NULL,
    status           ENUM(\'pendente\',\'aceita\') NOT NULL DEFAULT \'pendente\',
    criado_em        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY par_unico (solicitante_id, destinatario_id),
    FOREIGN KEY (solicitante_id) REFERENCES jogadores(id) ON DELETE CASCADE,
    FOREIGN KEY (destinatario_id) REFERENCES jogadores(id) ON DELETE CASCADE,
    INDEX (destinatario_id, status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  // mensagem privada: só entre quem já é amigo aceito dos dois lados (checado na aplicação
  // antes de gravar, não aqui no banco). "denunciada" fica visível na aba Moderação do
  // painel pro Hostmaster revisar e remover se for o caso — nenhuma mensagem é
  // verdadeiramente privada de um adulto responsável quando denunciada.
  $pdo->exec('CREATE TABLE IF NOT EXISTS mensagens (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    remetente_id    INT NOT NULL,
    destinatario_id INT NOT NULL,
    texto           VARCHAR(300) NOT NULL,
    lida            TINYINT(1) NOT NULL DEFAULT 0,
    denunciada      TINYINT(1) NOT NULL DEFAULT 0,
    removida        TINYINT(1) NOT NULL DEFAULT 0,
    criado_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (remetente_id) REFERENCES jogadores(id) ON DELETE CASCADE,
    FOREIGN KEY (destinatario_id) REFERENCES jogadores(id) ON DELETE CASCADE,
    INDEX (remetente_id, destinatario_id),
    INDEX (destinatario_id, lida),
    INDEX (denunciada)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  // fórum de dúvidas/comentários, sempre atrelado a uma lição (não é um fórum geral solto —
  // é "tirar dúvida sobre ESSA atividade"). resposta_a permite 1 nível de resposta (thread
  // simples); "removido" é soft-delete quando o Hostmaster apaga por denúncia, mantendo o
  // registro pra auditoria em vez de sumir sem rastro
  $pdo->exec('CREATE TABLE IF NOT EXISTS forum_posts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    autor_id     INT NOT NULL,
    licao_id     VARCHAR(24) NOT NULL,
    texto        VARCHAR(500) NOT NULL,
    resposta_a   INT NULL,
    denunciado   TINYINT(1) NOT NULL DEFAULT 0,
    removido     TINYINT(1) NOT NULL DEFAULT 0,
    criado_em    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (autor_id) REFERENCES jogadores(id) ON DELETE CASCADE,
    FOREIGN KEY (resposta_a) REFERENCES forum_posts(id) ON DELETE CASCADE,
    INDEX (licao_id, removido),
    INDEX (denunciado)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  // grupo de mensagem: só quem já é amigo mútuo de quem convida entra — nunca junta gente
  // que não se conhece. "criador_id" não tem privilégio especial hoje além de ter fundado
  // o grupo (qualquer membro pode convidar outro amigo seu, qualquer um pode sair).
  $pdo->exec('CREATE TABLE IF NOT EXISTS grupos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(40) NOT NULL,
    criador_id  INT NOT NULL,
    criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criador_id) REFERENCES jogadores(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  // ultima_leitura marca até onde esse membro já viu (pra contar não-lidas sem precisar de
  // uma linha por mensagem por membro, que ficaria pesado com grupo grande)
  $pdo->exec('CREATE TABLE IF NOT EXISTS grupo_membros (
    grupo_id       INT NOT NULL,
    jogador_id     INT NOT NULL,
    entrou_em      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_leitura TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (grupo_id, jogador_id),
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  $pdo->exec('CREATE TABLE IF NOT EXISTS grupo_mensagens (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id     INT NOT NULL,
    remetente_id INT NOT NULL,
    texto        VARCHAR(300) NOT NULL,
    denunciada   TINYINT(1) NOT NULL DEFAULT 0,
    removida     TINYINT(1) NOT NULL DEFAULT 0,
    criado_em    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    FOREIGN KEY (remetente_id) REFERENCES jogadores(id) ON DELETE CASCADE,
    INDEX (grupo_id),
    INDEX (denunciada)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  // clube: grupo aberto (lista pública, entra na hora, sem convite) com um líder que
  // enxerga o progresso dos membros — é o "clube"/"associação" que dá ao líder algo
  // parecido com o painel de um professor, mas sem parecer escola. jogador_id é PRIMARY
  // KEY em clube_membros de propósito: garante 1 clube por jogador direto no banco, sem
  // precisar checar na aplicação. Se o líder sai, a aplicação promove o membro mais
  // antigo que sobrar (ou apaga o clube, se ficar vazio) — ver "clube_sair" em estado.php.
  $pdo->exec('CREATE TABLE IF NOT EXISTS clubes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(40) NOT NULL UNIQUE,
    emoji       VARCHAR(8) NOT NULL DEFAULT \'🏅\',
    descricao   VARCHAR(160) NOT NULL DEFAULT \'\',
    lider_id    INT NOT NULL,
    criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lider_id) REFERENCES jogadores(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  $pdo->exec('CREATE TABLE IF NOT EXISTS clube_membros (
    jogador_id INT NOT NULL PRIMARY KEY,
    clube_id   INT NOT NULL,
    entrou_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (clube_id) REFERENCES clubes(id) ON DELETE CASCADE,
    FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE,
    INDEX (clube_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  // inscrições de notificação push (lembrete de sequência) — um jogador pode ter mais de
  // uma (celular + computador, por exemplo). endpoint é a URL única que o navegador dá
  // pra cada inscrição; sem UNIQUE por causa do tamanho em utf8mb4, resolvido na aplicação
  // (apaga a antiga com o mesmo endpoint antes de inserir de novo)
  $pdo->exec('CREATE TABLE IF NOT EXISTS push_inscricoes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id   INT NOT NULL,
    endpoint     VARCHAR(500) NOT NULL,
    chave_p256dh VARCHAR(255) NOT NULL,
    chave_auth   VARCHAR(255) NOT NULL,
    criado_em    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE,
    INDEX (jogador_id)
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
    capa_imagem   VARCHAR(160) NOT NULL DEFAULT \'\',
    capa_ativa    TINYINT(1)  NOT NULL DEFAULT 0,
    criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  // capa_imagem/capa_ativa chegaram depois de mundos já existir em produção — cabeçalho
  // opcional com imagem (upload no painel), nome da matéria e ícone do NPC associado
  // (ver telaNpcValida em admin.php, prefixo "mundo:") por cima, com efeito de parallax.
  $colunasMundos = array_column($pdo->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mundos'"
  )->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
  foreach ([
    'capa_imagem'        => "VARCHAR(160) NOT NULL DEFAULT ''",
    'capa_ativa'         => "TINYINT(1) NOT NULL DEFAULT 0",
    // agenda opcional da capa (evento por tempo limitado — ex.: banner de Natal), mesmo
    // formato usado por NPCs/móveis/itens de loja; vazio = sempre visível enquanto ativa
    'capa_dias_semana'   => "VARCHAR(20) NOT NULL DEFAULT ''",
    'capa_hora_inicio'   => "VARCHAR(5) NOT NULL DEFAULT ''",
    'capa_hora_fim'      => "VARCHAR(5) NOT NULL DEFAULT ''",
    'capa_data_inicio'   => "VARCHAR(10) NOT NULL DEFAULT ''",
    'capa_data_fim'      => "VARCHAR(10) NOT NULL DEFAULT ''",
    // ponto de ancoragem vertical da imagem de capa (topo/centro/base) — decide que parte
    // da imagem fica visível quando ela é cortada pra caber na faixa do cabeçalho
    'capa_ancora'        => "VARCHAR(10) NOT NULL DEFAULT 'center'",
  ] as $coluna => $definicao) {
    if (!in_array($coluna, $colunasMundos, true)) $pdo->exec("ALTER TABLE mundos ADD COLUMN $coluna $definicao");
  }
  $pastaMundos = dirname(__DIR__) . '/assets/mundos';
  if (!is_dir($pastaMundos)) @mkdir($pastaMundos, 0755, true);

  // sprites dos minigames do Arcade — cada linha é um "slot" opcional (ver SPRITES_MINIGAME
  // em admin.php); sem linha pro slot, o jogo usa o emoji padrão que já vinha embutido
  $pdo->exec('CREATE TABLE IF NOT EXISTS minigame_sprites (
    chave         VARCHAR(40) PRIMARY KEY,
    imagem        VARCHAR(160) NOT NULL DEFAULT \'\',
    escala        SMALLINT NOT NULL DEFAULT 100,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  // "escala" (50 a 200, em %) chegou depois de minigame_sprites já existir em produção —
  // mesma checagem por INFORMATION_SCHEMA usada nas outras colunas novas deste arquivo
  $colunasSpritesMinigame = array_column($pdo->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'minigame_sprites'"
  )->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
  if (!in_array('escala', $colunasSpritesMinigame, true)) {
    $pdo->exec('ALTER TABLE minigame_sprites ADD COLUMN escala SMALLINT NOT NULL DEFAULT 100');
  }
  $pastaMinigames = dirname(__DIR__) . '/assets/minigames';
  if (!is_dir($pastaMinigames)) @mkdir($pastaMinigames, 0755, true);

  /* temporadas: pacotes de sprites do Arcade por período (ex.: "Festa Junina"),
     reaproveitando o mesmo esquema de agenda (dias_semana/hora_inicio/fim/data_inicio/fim)
     já usado por NPCs/móveis/capas — quem decide se está vigente é o cliente, com a hora do
     aparelho de quem está jogando (mesmo motivo do dentroDaAgenda() no app.html). Uma
     temporada não precisa sobrescrever os 20 slots: só os que tiver em temporada_sprites
     saem do padrão, o resto continua puxando o sprite/emoji de sempre. */
  $pdo->exec('CREATE TABLE IF NOT EXISTS temporadas (
    id            VARCHAR(24) PRIMARY KEY,
    nome          VARCHAR(60) NOT NULL,
    ativa         TINYINT(1) NOT NULL DEFAULT 1,
    dias_semana   VARCHAR(20) NOT NULL DEFAULT \'\',
    hora_inicio   VARCHAR(5)  NOT NULL DEFAULT \'\',
    hora_fim      VARCHAR(5)  NOT NULL DEFAULT \'\',
    data_inicio   VARCHAR(10) NOT NULL DEFAULT \'\',
    data_fim      VARCHAR(10) NOT NULL DEFAULT \'\',
    ordem         INT NOT NULL DEFAULT 0,
    criada_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  $pdo->exec('CREATE TABLE IF NOT EXISTS temporada_sprites (
    temporada_id VARCHAR(24) NOT NULL,
    chave        VARCHAR(40) NOT NULL,
    imagem       VARCHAR(160) NOT NULL DEFAULT \'\',
    escala       SMALLINT NOT NULL DEFAULT 100,
    PRIMARY KEY (temporada_id, chave)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  /* config de cada minigame do Arcade além dos sprites: uma imagem de fundo (opcional) e
     os dois sons de feedback (acerto/erro) — sem nada configurado, o jogo usa o visual e o
     bipe sintetizado padrão de sempre. Uma linha fixa por jogo (todos os 11 do Arcade). */
  $pdo->exec('CREATE TABLE IF NOT EXISTS minigame_config (
    jogo          VARCHAR(20) PRIMARY KEY,
    fundo         VARCHAR(160) NOT NULL DEFAULT \'\',
    som_acerto    VARCHAR(160) NOT NULL DEFAULT \'\',
    som_erro      VARCHAR(160) NOT NULL DEFAULT \'\',
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  // "thumb" chegou depois de minigame_config já existir em produção — miniatura opcional
  // usada no cartão/carrossel do Arcade (diferente do "fundo", que é o cenário DENTRO do
  // jogo); sem imagem enviada, o cartão cai no ícone colorido padrão de sempre
  $colunasMinigameConfig = array_column($pdo->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'minigame_config'"
  )->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
  if (!in_array('thumb', $colunasMinigameConfig, true)) {
    $pdo->exec('ALTER TABLE minigame_config ADD COLUMN thumb VARCHAR(160) NOT NULL DEFAULT \'\'');
  }

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

  // NPCs: cada um tem uma imagem (por enquanto só png/webp/jpg — lottie é o próximo passo,
  // a coluna imagem_tipo já existe pra não precisar migrar de novo depois) e um diálogo em
  // árvore (JSON): nó inicial + nós com texto (balão de fala) e opções (botões) que levam a
  // outro nó ou encerram a conversa. Sem campo de texto livre em lugar nenhum — só botões.
  // "tela" é opcional: se preenchida (casa/arcade/loja/mural/perfil), o NPC aparece sozinho
  // naquela tela, sem precisar de ponto no mapa — pro Hostmaster poder colocar um NPC em
  // qualquer página do jogo, não só nos mapas.
  $pdo->exec('CREATE TABLE IF NOT EXISTS npcs (
    id            VARCHAR(24) PRIMARY KEY,
    nome          VARCHAR(60) NOT NULL,
    emoji         VARCHAR(8)  NOT NULL DEFAULT \'🧑\',
    imagem        VARCHAR(160) NOT NULL DEFAULT \'\',
    imagem_tipo   VARCHAR(10) NOT NULL DEFAULT \'png\',
    tela          VARCHAR(12) NOT NULL DEFAULT \'\',
    dias_semana   VARCHAR(20) NOT NULL DEFAULT \'\',
    hora_inicio   VARCHAR(5)  NOT NULL DEFAULT \'\',
    hora_fim      VARCHAR(5)  NOT NULL DEFAULT \'\',
    data_inicio   VARCHAR(10) NOT NULL DEFAULT \'\',
    data_fim      VARCHAR(10) NOT NULL DEFAULT \'\',
    dialogo       JSON NOT NULL,
    publicado     TINYINT(1) NOT NULL DEFAULT 1,
    ordem         INT NOT NULL DEFAULT 0,
    criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  /* colunas de agenda (dias_semana, hora_inicio/fim, data_inicio/fim) chegaram depois:
     quem já rodou o install.php antes só ganha a tabela nova via CREATE TABLE acima,
     não as colunas extra. Confere no INFORMATION_SCHEMA em vez de usar "ADD COLUMN IF
     NOT EXISTS" (nem todo MySQL/MariaDB de hospedagem compartilhada aceita essa
     sintaxe) — assim funciona tanto na primeira instalação quanto rodando de novo. */
  $colunasExistentes = array_column($pdo->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'npcs'"
  )->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
  foreach ([
    'dias_semana' => "VARCHAR(20) NOT NULL DEFAULT ''",
    'hora_inicio' => "VARCHAR(5) NOT NULL DEFAULT ''",
    'hora_fim'    => "VARCHAR(5) NOT NULL DEFAULT ''",
    'data_inicio' => "VARCHAR(10) NOT NULL DEFAULT ''",
    'data_fim'    => "VARCHAR(10) NOT NULL DEFAULT ''",
    // imagens extras opcionais (feliz/triste/surpreso) que um nó do diálogo pode escolher
    // no lugar da imagem principal — {chave: nome-do-arquivo}, mesma pasta assets/npcs
    'expressoes'  => "JSON NOT NULL DEFAULT '{}'",
  ] as $coluna => $definicao) {
    if (!in_array($coluna, $colunasExistentes, true)) $pdo->exec("ALTER TABLE npcs ADD COLUMN $coluna $definicao");
  }

  /* Missões: catálogo central, fora da página do NPC de propósito — um NPC só referencia
     uma missão (nos botões do diálogo dele), nunca a "dona" dela. "objetivo" muda de cara
     conforme o tipo (item pra entregar, cena pra visitar, ou gatilho pra ativar em algum
     mapa) — mesmo padrão de "catálogo fechado validado no servidor" já usado em blocos,
     pontos e diálogos. */
  $pdo->exec('CREATE TABLE IF NOT EXISTS missoes (
    id            VARCHAR(24) PRIMARY KEY,
    titulo        VARCHAR(100) NOT NULL,
    descricao     VARCHAR(300) NOT NULL DEFAULT \'\',
    tipo          VARCHAR(20) NOT NULL,
    objetivo      JSON NOT NULL,
    premio        JSON NOT NULL,
    publicado     TINYINT(1) NOT NULL DEFAULT 1,
    ordem         INT NOT NULL DEFAULT 0,
    criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  /* Jornal (Neoiatimes): artigos do jornal temático da vila — cada um pode ser "geral"
     (mundo_id vazio) ou ligado a um mundo específico (vira "seção" daquele assunto), e
     pode ter um colunista de verdade: um NPC já cadastrado, cujo nome vira a assinatura
     clicável (abre o diálogo dele, mesmo sistema de sempre). Sem colunista, autor_nome
     é só texto (ex.: "Redação"). Tela própria, com visual de jornal — bem diferente do
     resto do app de propósito. */
  $pdo->exec('CREATE TABLE IF NOT EXISTS jornal_artigos (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    titulo           VARCHAR(160) NOT NULL,
    subtitulo        VARCHAR(240) NOT NULL DEFAULT \'\',
    corpo            TEXT NOT NULL,
    mundo_id         VARCHAR(24) NULL,
    colunista_npc_id VARCHAR(24) NULL,
    autor_nome       VARCHAR(60) NOT NULL DEFAULT \'\',
    imagem           VARCHAR(160) NOT NULL DEFAULT \'\',
    destaque         TINYINT(1) NOT NULL DEFAULT 0,
    publicado        TINYINT(1) NOT NULL DEFAULT 1,
    ordem            INT NOT NULL DEFAULT 0,
    criado_em        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mundo_id) REFERENCES mundos(id) ON DELETE SET NULL,
    FOREIGN KEY (colunista_npc_id) REFERENCES npcs(id) ON DELETE SET NULL,
    INDEX (mundo_id),
    INDEX (destaque)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  $pastaJornal = dirname(__DIR__) . '/assets/jornal';
  if (!is_dir($pastaJornal)) @mkdir($pastaJornal, 0755, true);

  /* itens colecionáveis: objetos soltos no mapa (o "binóculo"), sem preço nem moeda
     envolvida — o jogador simplesmente acha e pega. Viram um ponto tipo "item" numa
     cena; ao tocar, somem do mapa pra sempre e entram no inventário. Qualquer outro
     ponto (de qualquer tipo) pode exigir um desses no inventário pra aparecer — é o
     "binóculo desbloqueia a janela" sem precisar de mecânica nova pra cada caso. */
  $pdo->exec('CREATE TABLE IF NOT EXISTS itens (
    id            VARCHAR(24) PRIMARY KEY,
    nome          VARCHAR(60) NOT NULL,
    emoji         VARCHAR(8)  NOT NULL DEFAULT \'🔹\',
    imagem        VARCHAR(160) NOT NULL DEFAULT \'\',
    descricao     VARCHAR(200) NOT NULL DEFAULT \'\',
    publicado     TINYINT(1) NOT NULL DEFAULT 1,
    ordem         INT NOT NULL DEFAULT 0,
    criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  // requisito_item chegou depois de "pontos" já existir em produção — mesma checagem
  // por INFORMATION_SCHEMA usada pra dias_semana/hora_inicio etc dos NPCs
  $colunasPontos = array_column($pdo->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pontos'"
  )->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
  if (!in_array('requisito_item', $colunasPontos, true)) {
    $pdo->exec("ALTER TABLE pontos ADD COLUMN requisito_item VARCHAR(24) NOT NULL DEFAULT ''");
  }

  /* móveis: catálogo pra decorar a Casa, estilo Club Penguin. Comprados na "Loja de
     Móveis" (uma tela só alcançada por ponto no mapa, tipo "tela") com as mesmas moedas
     do resto do jogo, e depois colocados no quarto — isso fica no estado do jogador
     (client-trusted, igual mochila/equipado), não numa tabela à parte. Um móvel pode ter
     até 4 imagens (uma por rotação); "rotativel" só liga o botão de girar no cliente —
     se faltar alguma imagem de rotação, o cliente cai pra imagem_frente. */
  $pdo->exec('CREATE TABLE IF NOT EXISTS moveis (
    id              VARCHAR(24) PRIMARY KEY,
    nome            VARCHAR(60) NOT NULL,
    preco           INT NOT NULL DEFAULT 0,
    rotativel       TINYINT(1) NOT NULL DEFAULT 0,
    imagem_frente   VARCHAR(160) NOT NULL DEFAULT \'\',
    imagem_direita  VARCHAR(160) NOT NULL DEFAULT \'\',
    imagem_verso    VARCHAR(160) NOT NULL DEFAULT \'\',
    imagem_esquerda VARCHAR(160) NOT NULL DEFAULT \'\',
    dias_semana     VARCHAR(20) NOT NULL DEFAULT \'\',
    hora_inicio     VARCHAR(5)  NOT NULL DEFAULT \'\',
    hora_fim        VARCHAR(5)  NOT NULL DEFAULT \'\',
    data_inicio     VARCHAR(10) NOT NULL DEFAULT \'\',
    data_fim        VARCHAR(10) NOT NULL DEFAULT \'\',
    publicado       TINYINT(1) NOT NULL DEFAULT 1,
    ordem           INT NOT NULL DEFAULT 0,
    criado_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  // agenda (estoque por tempo limitado) chegou depois de "moveis" já existir em produção
  // — mesma checagem por INFORMATION_SCHEMA usada nos NPCs
  $colunasMoveis = array_column($pdo->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'moveis'"
  )->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
  foreach ([
    'dias_semana' => "VARCHAR(20) NOT NULL DEFAULT ''",
    'hora_inicio' => "VARCHAR(5) NOT NULL DEFAULT ''",
    'hora_fim'    => "VARCHAR(5) NOT NULL DEFAULT ''",
    'data_inicio' => "VARCHAR(10) NOT NULL DEFAULT ''",
    'data_fim'    => "VARCHAR(10) NOT NULL DEFAULT ''",
  ] as $coluna => $definicao) {
    if (!in_array($coluna, $colunasMoveis, true)) $pdo->exec("ALTER TABLE moveis ADD COLUMN $coluna $definicao");
  }

  /* lojas genéricas: o Hostmaster cria quantas quiser (Lanchonete, Mercado, e o que vier
     depois) sem precisar de código novo — cada uma vira um destino de verdade no mapa
     (ponto tipo "loja", destino = id da loja). Os itens ficam numa tabela à parte,
     ligados por loja_id, com o mesmo padrão de agenda (estoque por tempo limitado) já
     usado nos móveis, mais "variantes" (JSON: [{id, nome, imagem}]) pra sabores/tamanhos
     — o aluno escolhe qual levar na hora de comprar. fome/alegria são opcionais: só
     preenche se o item for de verdade uma comida (a Lanchonete provavelmente sim, o
     Mercado pode ter item que é só pra guardar). */
  $pdo->exec('CREATE TABLE IF NOT EXISTS lojas (
    id            VARCHAR(24) PRIMARY KEY,
    nome          VARCHAR(60) NOT NULL,
    emoji         VARCHAR(8)  NOT NULL DEFAULT \'🏪\',
    publicado     TINYINT(1) NOT NULL DEFAULT 1,
    ordem         INT NOT NULL DEFAULT 0,
    capa_imagem   VARCHAR(160) NOT NULL DEFAULT \'\',
    capa_ativa    TINYINT(1)  NOT NULL DEFAULT 0,
    criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  // mesmo cabeçalho de capa opcional das matérias, agora pras lojas
  $colunasLojas = array_column($pdo->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lojas'"
  )->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
  foreach ([
    'capa_imagem'      => "VARCHAR(160) NOT NULL DEFAULT ''",
    'capa_ativa'       => "TINYINT(1) NOT NULL DEFAULT 0",
    'capa_dias_semana' => "VARCHAR(20) NOT NULL DEFAULT ''",
    'capa_hora_inicio' => "VARCHAR(5) NOT NULL DEFAULT ''",
    'capa_hora_fim'    => "VARCHAR(5) NOT NULL DEFAULT ''",
    'capa_data_inicio' => "VARCHAR(10) NOT NULL DEFAULT ''",
    'capa_data_fim'    => "VARCHAR(10) NOT NULL DEFAULT ''",
    'capa_ancora'      => "VARCHAR(10) NOT NULL DEFAULT 'center'",
  ] as $coluna => $definicao) {
    if (!in_array($coluna, $colunasLojas, true)) $pdo->exec("ALTER TABLE lojas ADD COLUMN $coluna $definicao");
  }
  $pdo->exec('CREATE TABLE IF NOT EXISTS itens_loja (
    id            VARCHAR(24) PRIMARY KEY,
    loja_id       VARCHAR(24) NOT NULL,
    nome          VARCHAR(60) NOT NULL,
    emoji         VARCHAR(8)  NOT NULL DEFAULT \'🔹\',
    tipo          VARCHAR(12) NOT NULL DEFAULT \'comida\',
    preco         INT NOT NULL DEFAULT 0,
    imagem        VARCHAR(160) NOT NULL DEFAULT \'\',
    fome          INT NOT NULL DEFAULT 0,
    alegria       INT NOT NULL DEFAULT 0,
    variantes     JSON NOT NULL,
    dias_semana   VARCHAR(20) NOT NULL DEFAULT \'\',
    hora_inicio   VARCHAR(5)  NOT NULL DEFAULT \'\',
    hora_fim      VARCHAR(5)  NOT NULL DEFAULT \'\',
    data_inicio   VARCHAR(10) NOT NULL DEFAULT \'\',
    data_fim      VARCHAR(10) NOT NULL DEFAULT \'\',
    publicado     TINYINT(1) NOT NULL DEFAULT 1,
    ordem         INT NOT NULL DEFAULT 0,
    estoque_total   INT NOT NULL DEFAULT 0,
    estoque_vendido INT NOT NULL DEFAULT 0,
    criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (loja_id) REFERENCES lojas(id) ON DELETE CASCADE,
    INDEX (loja_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  // "tipo" e o estoque limitado chegaram depois de itens_loja já existir em produção
  // (tipo: comida = consumível, dá fome/alegria; acessorio = veste/tira, sem efeito.
  // estoque_total = 0 significa sem limite; estoque_vendido só é incrementado pelo
  // endpoint atômico de compra em estado.php, nunca pelo formulário do painel) — mesma
  // checagem por INFORMATION_SCHEMA usada nas outras colunas novas deste arquivo.
  $colunasItensLoja = array_column($pdo->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itens_loja'"
  )->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
  if (!in_array('tipo', $colunasItensLoja, true)) {
    $pdo->exec("ALTER TABLE itens_loja ADD COLUMN tipo VARCHAR(12) NOT NULL DEFAULT 'comida'");
  }
  if (!in_array('estoque_total', $colunasItensLoja, true)) {
    $pdo->exec('ALTER TABLE itens_loja ADD COLUMN estoque_total INT NOT NULL DEFAULT 0');
  }
  if (!in_array('estoque_vendido', $colunasItensLoja, true)) {
    $pdo->exec('ALTER TABLE itens_loja ADD COLUMN estoque_vendido INT NOT NULL DEFAULT 0');
  }
  $pastaLojas = dirname(__DIR__) . '/assets/lojas';
  if (!is_dir($pastaLojas)) @mkdir($pastaLojas, 0755, true);

  // configurações gerais do jogo: fundo da Casa e preço pra desbloquear a decoração —
  // um valor só, vale pra todo mundo (não é por jogador). Linha única (id sempre 1).
  $pdo->exec('CREATE TABLE IF NOT EXISTS configuracoes (
    id                    TINYINT PRIMARY KEY DEFAULT 1,
    casa_fundo            VARCHAR(160) NOT NULL DEFAULT \'\',
    preco_casa            INT NOT NULL DEFAULT 5000,
    capa_lojamoveis_imagem VARCHAR(160) NOT NULL DEFAULT \'\',
    capa_lojamoveis_ativa  TINYINT(1) NOT NULL DEFAULT 0,
    capa_mural_imagem      VARCHAR(160) NOT NULL DEFAULT \'\',
    capa_mural_ativa       TINYINT(1) NOT NULL DEFAULT 0,
    musica_fundo           VARCHAR(160) NOT NULL DEFAULT \'\',
    musica_ativa           TINYINT(1) NOT NULL DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  // preco_casa e as colunas de capa chegaram depois de "configuracoes" já existir em
  // produção — mesma checagem por INFORMATION_SCHEMA usada nas outras colunas novas deste
  // arquivo. Roda ANTES do INSERT logo abaixo, senão o INSERT referencia uma coluna que
  // ainda não existe.
  $colunasConfig = array_column($pdo->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracoes'"
  )->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
  if (!in_array('preco_casa', $colunasConfig, true)) {
    $pdo->exec('ALTER TABLE configuracoes ADD COLUMN preco_casa INT NOT NULL DEFAULT 5000');
  }
  if (!in_array('capa_lojamoveis_imagem', $colunasConfig, true)) {
    $pdo->exec('ALTER TABLE configuracoes ADD COLUMN capa_lojamoveis_imagem VARCHAR(160) NOT NULL DEFAULT \'\'');
  }
  if (!in_array('capa_lojamoveis_ativa', $colunasConfig, true)) {
    $pdo->exec('ALTER TABLE configuracoes ADD COLUMN capa_lojamoveis_ativa TINYINT(1) NOT NULL DEFAULT 0');
  }
  if (!in_array('capa_mural_imagem', $colunasConfig, true)) {
    $pdo->exec('ALTER TABLE configuracoes ADD COLUMN capa_mural_imagem VARCHAR(160) NOT NULL DEFAULT \'\'');
  }
  if (!in_array('capa_mural_ativa', $colunasConfig, true)) {
    $pdo->exec('ALTER TABLE configuracoes ADD COLUMN capa_mural_ativa TINYINT(1) NOT NULL DEFAULT 0');
  }
  if (!in_array('musica_fundo', $colunasConfig, true)) {
    $pdo->exec('ALTER TABLE configuracoes ADD COLUMN musica_fundo VARCHAR(160) NOT NULL DEFAULT \'\'');
  }
  if (!in_array('musica_ativa', $colunasConfig, true)) {
    $pdo->exec('ALTER TABLE configuracoes ADD COLUMN musica_ativa TINYINT(1) NOT NULL DEFAULT 0');
  }
  // agenda opcional das duas capas fixas (evento por tempo limitado, mesmo formato usado
  // em mundos/lojas/NPCs/móveis/itens de loja) — vazio = sempre visível enquanto ativa
  foreach (['lojamoveis', 'mural'] as $campoCapa) {
    foreach ([
      'dias_semana' => "VARCHAR(20) NOT NULL DEFAULT ''",
      'hora_inicio' => "VARCHAR(5) NOT NULL DEFAULT ''",
      'hora_fim'    => "VARCHAR(5) NOT NULL DEFAULT ''",
      'data_inicio' => "VARCHAR(10) NOT NULL DEFAULT ''",
      'data_fim'    => "VARCHAR(10) NOT NULL DEFAULT ''",
      'ancora'      => "VARCHAR(10) NOT NULL DEFAULT 'center'",
    ] as $sufixo => $definicao) {
      $coluna = "capa_{$campoCapa}_{$sufixo}";
      if (!in_array($coluna, $colunasConfig, true)) $pdo->exec("ALTER TABLE configuracoes ADD COLUMN $coluna $definicao");
    }
  }
  $pdo->exec('INSERT IGNORE INTO configuracoes (id, casa_fundo, preco_casa) VALUES (1, \'\', 5000)');

  /* Fases do Empurra-Caixas (minigame estilo Sokoban): cada fase é um texto multi-linha na
     notação clássica — # parede, @ jogador, $ caixa, . alvo, ' ' chão — mais 3 extras deste
     jogo: ^ espinho (bloqueia só o jogador, caixa passa por cima normal), % gatilho (quando
     alguma caixa fica em cima, TODAS as portas D destrancam de vez, mesmo que a caixa saia
     de cima depois) e D porta (parede até destrancar). "numero" é a ordem/identidade da fase
     pro cliente (estado.faseSokobanDesbloqueada, sokobanCompletadas) — trocar o texto da
     grade de uma fase existente não quebra o progresso salvo de ninguém, só apagar/renumerar
     fases quebraria. */
  $pdo->exec('CREATE TABLE IF NOT EXISTS sokoban_fases (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    numero        INT NOT NULL,
    nome          VARCHAR(60) NOT NULL DEFAULT \'\',
    grade         TEXT NOT NULL,
    publicada     TINYINT(1) NOT NULL DEFAULT 1,
    criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (numero)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  // numero não tem UNIQUE no banco de propósito — o upsert do admin.php usa "id" (o
  // AUTO_INCREMENT) como chave, e valida ali mesmo que não exista outra fase com o mesmo
  // número antes de salvar (erro claro pro admin, em vez de um ON DUPLICATE KEY silencioso
  // sobrescrevendo a fase errada por coincidência de número)

  // ranking do Empurra-Caixas: melhor pontuação (menos jogadas) de cada jogador em cada
  // fase — 1 linha por (jogador, fase), atualizada só quando melhora (ver estado.php,
  // sokoban_pontuar). "fase_numero" e não um id de sokoban_fases de propósito: se o admin
  // excluir/recriar a fase, o ranking de quem já jogou continua valendo pro mesmo número.
  $pdo->exec('CREATE TABLE IF NOT EXISTS sokoban_pontuacoes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id    INT NOT NULL,
    fase_numero   INT NOT NULL,
    jogadas       INT NOT NULL,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY jogador_fase (jogador_id, fase_numero),
    INDEX (fase_numero, jogadas),
    FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

  // mapas criados por jogador (aba "crie seu mapa" do Empurra-Caixas): ficam pendentes até
  // o hostmaster aprovar (vira uma linha nova em sokoban_fases, numerada) ou rejeitar. O
  // jogador pode jogar o próprio mapa a qualquer momento (aprovado ou não) — só quem
  // aprova é que decide se o resto da turma também joga.
  $pdo->exec("CREATE TABLE IF NOT EXISTS sokoban_mapas_jogadores (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id            INT NOT NULL,
    nome                  VARCHAR(60) NOT NULL DEFAULT '',
    grade                 TEXT NOT NULL,
    status                ENUM('pendente','aprovado','rejeitado') NOT NULL DEFAULT 'pendente',
    fase_numero_aprovada  INT NULL,
    criado_em             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (status),
    FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $sokobanExistentes = (int)$pdo->query('SELECT COUNT(*) FROM sokoban_fases')->fetchColumn();
  $sokobanSemeadas = 0;
  if ($sokobanExistentes === 0) {
    $niveisSokoban = [
      "#####\n#@  #\n# \$.#\n#####",
      "######\n#    #\n# \$  #\n#  #@#\n#  . #\n######",
      "###########\n#@ ########\n#^ ########\n#         #\n###\$###\$###\n### ### ###\n### ### ###\n### ### ###\n###.###.###\n###########",
      "#############\n#@    #######\n#^ #^ #######\n#           #\n###\$##\$###\$##\n### ## ### ##\n### ## ### ##\n### ## ### ##\n###.##.###.##\n#############",
      "#########\n#@    ###\n####^ ###\n#       #\n####  ###\n####^ ###\n#       #\n##\$###\$##\n## ### ##\n## ### ##\n##.###.##\n#########",
      "############\n#@     D   #\n###\$#\$##\$###\n### #%## ###\n### #### ###\n### #### ###\n### #### ###\n###.####.###\n############",
      "##############\n#@ ###########\n#^ ###########\n#        D   #\n###\$##\$##\$##\$#\n### ##%## ## #\n### ##### ## #\n###.#####.##.#\n##############",
      "##############\n#@    ########\n#^ #^ ########\n#            #\n###\$###\$###\$##\n### ### ### ##\n### ### ### ##\n### ### ### ##\n### ### ### ##\n### ### ### ##\n###.###.###.##\n##############",
      "################\n#@ #############\n#^ #############\n#            D #\n###\$###\$\$##\$##\$#\n### ### %## ## #\n### ### ### ## #\n### ### ### ## #\n###.###.###.##.#\n################",
      "#################\n#@    ###########\n#^ #^ ###########\n#             D #\n###\$###\$#\$##\$##\$#\n### ### #%## ## #\n### ### #### ## #\n### ### #### ## #\n### ### #### ## #\n### ### #### ## #\n###.###.####.##.#\n#################",
    ];
    $insSokoban = $pdo->prepare('INSERT INTO sokoban_fases (numero, nome, grade, publicada) VALUES (?, ?, ?, 1)');
    foreach ($niveisSokoban as $i => $g) {
      $insSokoban->execute([$i + 1, 'Fase ' . ($i + 1), $g]);
      $sokobanSemeadas++;
    }
  }

  // pasta das imagens enviadas pelo painel (as do repositório continuam em assets/)
  $pastaCenas = dirname(__DIR__) . '/assets/cenas';
  if (!is_dir($pastaCenas)) @mkdir($pastaCenas, 0755, true);
  $pastaNpcs = dirname(__DIR__) . '/assets/npcs';
  if (!is_dir($pastaNpcs)) @mkdir($pastaNpcs, 0755, true);
  $pastaItens = dirname(__DIR__) . '/assets/itens';
  if (!is_dir($pastaItens)) @mkdir($pastaItens, 0755, true);
  $pastaMoveis = dirname(__DIR__) . '/assets/moveis';
  if (!is_dir($pastaMoveis)) @mkdir($pastaMoveis, 0755, true);

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

  /* semeia um NPC de exemplo na Loja — só se ainda não houver nenhum NPC cadastrado,
     igual à cena "Ilha do saber": mostra o recurso funcionando de cara, mas nunca pisa
     em cima do que o Hostmaster já tiver criado ou editado no painel. */
  $npcsExistentes = (int)$pdo->query('SELECT COUNT(*) FROM npcs')->fetchColumn();
  $npcSemeado = false;
  if ($npcsExistentes === 0) {
    $dialogoLojista = [
      'inicial' => 'oi',
      'nos' => [
        'oi' => [
          'texto' => 'Ô da loja! Precisando de alguma coisa pro seu bicho?',
          'opcoes' => [
            ['rotulo' => 'O que você vende?', 'proximo' => 'vende'],
            ['rotulo' => 'Só passando, valeu!', 'proximo' => null],
          ],
        ],
        'vende' => [
          'texto' => 'Tenho comida pra matar a fome e acessórios pra deixar seu bicho estiloso. Dá uma olhada aqui embaixo!',
          'opcoes' => [
            ['rotulo' => 'Show, obrigado!', 'proximo' => null],
          ],
        ],
      ],
    ];
    $pdo->prepare('INSERT INTO npcs (id, nome, emoji, imagem, imagem_tipo, tela, dialogo, publicado, ordem) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0)')
      ->execute(['lojista', 'Seu Lontra', '🦦', '', 'png', 'loja', json_encode($dialogoLojista, JSON_UNESCAPED_UNICODE)]);
    $npcSemeado = true;
  }

  /* semeia os móveis de exemplo (mesa + poltrona) e o fundo padrão da Casa — as imagens
     já vêm no repositório (assets/moveis/), não precisam de upload pelo painel. Só na
     primeira vez (tabela vazia), igual aos outros catálogos de exemplo acima. O fundo da
     Casa só é setado se ainda estiver vazio, pra não sobrescrever se o Hostmaster já
     tiver trocado por outro antes de rodar o install.php de novo. */
  $moveisExistentes = (int)$pdo->query('SELECT COUNT(*) FROM moveis')->fetchColumn();
  $moveisSemeados = 0;
  if ($moveisExistentes === 0) {
    $pdo->prepare('INSERT INTO moveis (id, nome, preco, rotativel, imagem_frente, imagem_direita, imagem_verso, imagem_esquerda, publicado, ordem)
      VALUES (?, ?, ?, 0, ?, \'\', \'\', \'\', 1, ?)')
      ->execute(['mesaredonda', 'Mesa redonda', 40, 'mesa.webp', 0]);
    $pdo->prepare('INSERT INTO moveis (id, nome, preco, rotativel, imagem_frente, imagem_direita, imagem_verso, imagem_esquerda, publicado, ordem)
      VALUES (?, ?, ?, 1, ?, ?, ?, ?, 1, ?)')
      ->execute(['poltronaazul', 'Poltrona azul', 90, 'poltrona-frente.webp', 'poltrona-direita.webp', 'poltrona-verso.webp', 'poltrona-esquerda.webp', 1]);
    $moveisSemeados = 2;
  }
  $fundoAtual = (string)($pdo->query('SELECT casa_fundo FROM configuracoes WHERE id = 1')->fetchColumn() ?: '');
  $fundoSemeado = false;
  if ($fundoAtual === '') {
    $pdo->prepare('UPDATE configuracoes SET casa_fundo = ? WHERE id = 1')->execute(['casa-fundo-limoeiro.webp']);
    $fundoSemeado = true;
  }

  /* semeia as duas lojas de exemplo (Lanchonete e Mercado) só na primeira vez — mostra o
     sistema genérico de lojas já funcionando, com um item de cada tipo: um com variantes
     (sabores) e um só pra guardar (sem fome/alegria). Só emoji, sem imagem — o Hostmaster
     troca por foto de verdade quando quiser, na aba Lojas. */
  $lojasExistentes = (int)$pdo->query('SELECT COUNT(*) FROM lojas')->fetchColumn();
  $lojasSemeadas = 0;
  if ($lojasExistentes === 0) {
    $pdo->prepare('INSERT INTO lojas (id, nome, emoji, publicado, ordem) VALUES (?, ?, ?, 1, ?)')
      ->execute(['lanchonete', 'Lanchonete', '🍔', 0]);
    $pdo->prepare('INSERT INTO lojas (id, nome, emoji, publicado, ordem) VALUES (?, ?, ?, 1, ?)')
      ->execute(['mercado', 'Mercado', '🛒', 1]);
    $variantesSuco = json_encode([
      ['id' => 'morango', 'nome' => 'Morango', 'imagem' => ''],
      ['id' => 'uva', 'nome' => 'Uva', 'imagem' => ''],
      ['id' => 'laranja', 'nome' => 'Laranja', 'imagem' => ''],
    ], JSON_UNESCAPED_UNICODE);
    $pdo->prepare('INSERT INTO itens_loja (id, loja_id, nome, emoji, preco, fome, alegria, variantes, publicado, ordem)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)')
      ->execute(['sucogelado', 'lanchonete', 'Suco gelado', '🧃', 12, 8, 10, $variantesSuco, 0]);
    $pdo->prepare('INSERT INTO itens_loja (id, loja_id, nome, emoji, preco, fome, alegria, variantes, publicado, ordem)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)')
      ->execute(['sanduiche', 'lanchonete', 'Sanduíche natural', '🥪', 18, 22, 6, '[]', 1]);
    $pdo->prepare('INSERT INTO itens_loja (id, loja_id, nome, emoji, preco, fome, alegria, variantes, publicado, ordem)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)')
      ->execute(['cestabasica', 'mercado', 'Cesta de frutas', '🧺', 25, 15, 4, '[]', 0]);
    $pdo->prepare('INSERT INTO itens_loja (id, loja_id, nome, emoji, preco, fome, alegria, variantes, publicado, ordem)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)')
      ->execute(['bicho_pelucia', 'mercado', 'Bichinho de pelúcia', '🧸', 35, 0, 0, '[]', 1]);
    $lojasSemeadas = 2;
  }

  /* migra a "Loja da vila" (antes um array fixo ITENS em app.html, com comida e
     acessório misturados) pro motor genérico de Lojas — mesmos ids de sempre, pra quem
     já tem itens no inventário/mochila continuar reconhecendo tudo certinho. Roda uma
     vez só (checagem própria, independente da dos exemplos acima). */
  $stLojaVila = $pdo->prepare('SELECT COUNT(*) FROM lojas WHERE id = ?');
  $stLojaVila->execute(['lojadavila']);
  $lojaVilaExiste = (int)$stLojaVila->fetchColumn();
  $lojaVilaSemeada = false;
  if ($lojaVilaExiste === 0) {
    $pdo->prepare('INSERT INTO lojas (id, nome, emoji, publicado, ordem) VALUES (?, ?, ?, 1, ?)')
      ->execute(['lojadavila', 'Loja da vila', '🏪', -1]);
    $itensVila = [
      ['melancia', 'Melancia',            '🍉', 'comida',    15, 22, 6,  1],
      ['racao',    'Ração boa',           '🥣', 'comida',    10, 16, 2,  2],
      ['peixe',    'Peixe fresco',        '🐟', 'comida',    22, 30, 10, 3],
      ['bolo',     'Bolo de aniversário', '🍰', 'comida',    40, 18, 26, 4],
      ['chapeu',   'Chapéu de festa',     '🎩', 'acessorio', 70, 0,  0,  5],
      ['oculos',   'Óculos maneiro',      '🕶️', 'acessorio', 90, 0,  0,  6],
      ['cachecol', 'Cachecol roxo',       '🧣', 'acessorio', 110, 0, 0,  7],
    ];
    $inserirItemVila = $pdo->prepare('INSERT INTO itens_loja
      (id, loja_id, nome, emoji, tipo, preco, fome, alegria, variantes, publicado, ordem)
      VALUES (?, \'lojadavila\', ?, ?, ?, ?, ?, ?, \'[]\', 1, ?)');
    foreach ($itensVila as [$id, $nome, $emoji, $tipo, $preco, $fome, $alegria, $ordem]) {
      $inserirItemVila->execute([$id, $nome, $emoji, $tipo, $preco, $fome, $alegria, $ordem]);
    }
    $lojaVilaSemeada = true;
  }

  /* lista mínima de partida pra Moderação > Palavras proibidas (mensagens/fórum) — só
     palavrão comum e inequívoco, sem gíria regional nem termo ligado a identidade/grupo
     (isso exige julgamento humano de quem conhece a turma). O Hostmaster expande a lista
     no painel; aqui é só pra o filtro já sair funcionando, não vazio. */
  $palavrasExistentes = (int)$pdo->query('SELECT COUNT(*) FROM palavras_proibidas')->fetchColumn();
  $palavrasSemeadas = 0;
  if ($palavrasExistentes === 0) {
    $inserirPalavra = $pdo->prepare('INSERT INTO palavras_proibidas (palavra, normalizada) VALUES (?, ?)');
    foreach (['bosta', 'caralho', 'cuzão', 'merda', 'porra', 'puta', 'putaria', 'foder', 'fdp', 'arrombado'] as $p) {
      $inserirPalavra->execute([$p, normalizarTexto($p)]);
      $palavrasSemeadas++;
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
     . ($npcSemeado
        ? '<p>🦦 NPC de exemplo "Seu Lontra" criado na Loja — edite ou apague em <code>/admin.html</code>, aba NPCs.</p>'
        : '<p>🦦 Já havia NPC cadastrado — nada foi semeado.</p>')
     . ($moveisSemeados
        ? '<p>🛋️ ' . $moveisSemeados . ' móveis de exemplo (mesa + poltrona) criados — edite em <code>/admin.html</code>, aba Móveis. Falta só colocar um ponto tipo "tela" → "Loja de Móveis" num mapa pra dar acesso.</p>'
        : '<p>🛋️ Já havia móvel cadastrado — nada foi semeado.</p>')
     . ($fundoSemeado
        ? '<p>🏠 Fundo padrão da Casa configurado (o quarto de pedra azul).</p>'
        : '<p>🏠 A Casa já tinha um fundo configurado — nada foi trocado.</p>')
     . ($lojasSemeadas
        ? '<p>🏪 Lojas de exemplo "Lanchonete" e "Mercado" criadas, com alguns itens — edite em <code>/admin.html</code>, aba Lojas. Falta só colocar um ponto tipo "Visitar uma loja" em cada uma, na aba Mapas, pra dar acesso.</p>'
        : '<p>🏪 Já havia loja cadastrada — nada foi semeado.</p>')
     . ($lojaVilaSemeada
        ? '<p>🎒 A "Loja da vila" (comida e acessórios da aba Loja, no rodapé do jogo) agora também é uma loja normal, editável em <code>/admin.html</code> → Lojas. Os itens têm os mesmos ids de sempre, então quem já tinha algo guardado continua com tudo certinho.</p>'
        : '<p>🎒 A "Loja da vila" já tinha sido migrada — nada foi trocado.</p>')
     . ($palavrasSemeadas
        ? '<p>🛡️ ' . $palavrasSemeadas . ' palavras proibidas de partida cadastradas (Moderação > Palavras proibidas) — é só uma base mínima, complete com o que fizer sentido pra sua turma.</p>'
        : '<p>🛡️ A lista de palavras proibidas já existia — nada foi trocado.</p>')
     . ($sokobanSemeadas
        ? '<p>📦 ' . $sokobanSemeadas . ' fases do Empurra-Caixas semeadas — edite, reordene ou crie mais em <code>/admin.html</code>, aba Sokoban.</p>'
        : '<p>📦 Já havia fase do Empurra-Caixas cadastrada — nada foi semeado.</p>')
     . '<p>Acesse <code>/admin.html</code> para entrar no painel. Depois é só apagar este arquivo (api/install.php) do servidor, ou deixá-lo — ele nunca sobrescreve conteúdo já existente.</p>';
} catch (Throwable $e) {
  http_response_code(500);
  echo '<p>Deu erro conectando ou criando o banco:</p><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}
