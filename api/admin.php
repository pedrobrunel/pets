<?php
/* =========================================================
   Bichoteca — painel do Hostmaster (back-end)
   Conta separada da conta de jogador: quem hospeda o site, não
   professor nem aluno. A conta é criada/atualizada pelo install.php
   a partir de ADMIN_USUARIO/ADMIN_SENHA em config.php.

   Ações (todas exigem sessão de admin, exceto admin_entrar):
     POST ?acao=admin_entrar          {usuario, senha}
     POST ?acao=admin_sair
     GET  ?acao=admin_eu
     GET  ?acao=mundos_listar
     POST ?acao=mundo_salvar          {id, nome, emoji, cor, ordem, publicado, capaImagem, capaAtiva,
                                        capaDiasSemana, capaHoraInicio, capaHoraFim, capaDataInicio, capaDataFim,
                                        capaAncora=top|center|bottom}
     POST ?acao=mundo_excluir         {id}
     POST ?acao=mundo_imagem          (multipart: arquivo) -> sobe imagem de capa pra assets/mundos/
     GET  ?acao=licoes_listar         [?mundo=xx]
     GET  ?acao=licao_obter           ?id=xx
     POST ?acao=licao_salvar          {id, mundoId, titulo, emoji, serie, ordem, publicado, blocos:[...]}
     POST ?acao=licao_excluir         {id}
     GET  ?acao=jogadores_listar      [?busca=]
     POST ?acao=jogador_resetar_senha {id, novaSenha}
     POST ?acao=jogador_excluir       {id}
     GET  ?acao=moderacao_listar      -> mensagens privadas, posts do fórum e mensagens de grupo denunciados
     POST ?acao=moderacao_mensagem_remover {id} -> some com o texto (fica "[mensagem removida]")
     POST ?acao=moderacao_mensagem_ignorar {id} -> falso positivo, some da fila mas mantém a mensagem
     POST ?acao=moderacao_post_remover {id}     -> remove o comentário do fórum
     POST ?acao=moderacao_post_ignorar {id}     -> falso positivo, some da fila mas mantém o post
     POST ?acao=moderacao_grupo_mensagem_remover {id} -> some com o texto da mensagem de grupo
     POST ?acao=moderacao_grupo_mensagem_ignorar {id} -> falso positivo, some da fila mas mantém a mensagem
     GET  ?acao=palavras_proibidas_listar
     POST ?acao=palavra_proibida_adicionar {palavra}
     POST ?acao=palavra_proibida_remover   {palavra}
     GET  ?acao=clubes_listar         -> todos os clubes (nome, líder, total de membros)
     POST ?acao=clube_excluir         {id} -> dissolve um clube (ex.: nome/descrição impróprios)
     GET  ?acao=metricas
     GET  ?acao=exportar
     POST ?acao=importar              {mundos:[...]}   (mesmo formato do exportar)
     GET  ?acao=cenas_listar
     GET  ?acao=cena_obter            ?id=xx           -> cena + seus pontos
     POST ?acao=cena_salvar           {id, nome, imagem, inicial, publicado, ordem}
     POST ?acao=cena_excluir          {id}
     POST ?acao=pontos_salvar         {cenaId, pontos:[...]}  -> substitui os pontos da cena
     POST ?acao=cena_imagem           (multipart: arquivo) -> sobe imagem pra assets/cenas/
     GET  ?acao=destinos              -> catálogo de links possíveis pra um ponto
     GET  ?acao=npcs_listar
     GET  ?acao=npc_obter             ?id=xx
     POST ?acao=npc_salvar            {id, nome, emoji, imagem, imagemTipo, tela, diasSemana, horaInicio, horaFim,
                                        dataInicio, dataFim, publicado, ordem,
                                        dialogo:{inicial, inicialRetorno?, condicoesEntrada?:[{estado,no}],
                                          nos:{..., expressao?, variantes?:[...]}},
                                        expressoes:{feliz?, triste?, surpreso?}}
     POST ?acao=npc_excluir           {id}
     POST ?acao=npc_duplicar          {id, novoId} -> clona um NPC (fica como rascunho)
     POST ?acao=npc_imagem            (multipart: arquivo) -> sobe imagem pra assets/npcs/ (usado pra imagem
                                        principal E pras expressões extras — mesmo upload genérico pros dois)
     POST ?acao=npcs_importar         {npcs:[...]} -> cria/atualiza vários de uma vez (mesmo formato do npc_salvar
                                        em cada item; imagem/expressões só funcionam se o arquivo já existir)
     GET  ?acao=missoes_listar
     GET  ?acao=missao_obter          ?id=xx
     POST ?acao=missao_salvar         {id, titulo, descricao, tipo, objetivo:{...}, premio:{moedas,xp}, publicado, ordem}
     POST ?acao=missao_excluir        {id}
     POST ?acao=missao_duplicar       {id, novoId} -> clona uma missão (fica como rascunho)
     POST ?acao=missoes_importar      {missoes:[...]} -> cria/atualiza várias de uma vez (mesmo formato do salvar)
     GET  ?acao=jornal_listar         -> artigos do Jornal (Neoiatimes)
     GET  ?acao=jornal_obter          ?id=xx
     POST ?acao=jornal_salvar         {id?, titulo, subtitulo, corpo, mundoId?, colunistaNpcId?, autorNome,
                                        imagem, destaque, publicado, ordem} -> sem id cria um artigo novo
     POST ?acao=jornal_excluir        {id}
     POST ?acao=jornal_imagem         (multipart: arquivo) -> sobe imagem pra assets/jornal/
     GET  ?acao=itens_listar
     GET  ?acao=item_obter            ?id=xx
     POST ?acao=item_salvar           {id, nome, emoji, imagem, descricao, publicado, ordem}
     POST ?acao=item_excluir          {id}
     POST ?acao=item_imagem           (multipart: arquivo) -> sobe imagem pra assets/itens/
     GET  ?acao=moveis_listar
     GET  ?acao=movel_obter           ?id=xx
     POST ?acao=movel_salvar          {id, nome, preco, rotativel, imagemFrente, imagemDireita,
                                        imagemVerso, imagemEsquerda, diasSemana, horaInicio, horaFim,
                                        dataInicio, dataFim, publicado, ordem}
     POST ?acao=movel_excluir         {id}
     POST ?acao=movel_duplicar        {id, novoId} -> clona um móvel (fica como rascunho)
     POST ?acao=movel_imagem          (multipart: arquivo, slot=frente|direita|verso|esquerda) -> sobe imagem pra assets/moveis/
     GET  ?acao=casa_config_obter     -> fundo e preço de desbloqueio atuais da Casa
     POST ?acao=casa_fundo_imagem     (multipart: arquivo) -> sobe e já grava o fundo da Casa
     POST ?acao=casa_fundo_remover    -> volta a Casa pro visual padrão (sem fundo)
     POST ?acao=casa_preco_salvar     {precoCasa} -> preço em moedas pra desbloquear a decoração
     GET  ?acao=capas_obter           -> capas de cabeçalho da Loja de Móveis e do Mural
     POST ?acao=capas_salvar          {campo, ativa, diasSemana, horaInicio, horaFim, dataInicio, dataFim,
                                        ancora=top|center|bottom} -> campo = lojamoveis|mural
     POST ?acao=capas_imagem          (multipart: arquivo, campo=lojamoveis|mural) -> sobe pra assets/moveis/
     GET  ?acao=musica_obter          -> música de fundo (opcional) atual
     POST ?acao=musica_ativa_salvar   {ativa}
     POST ?acao=musica_upload         (multipart: arquivo) -> sobe e já grava a música de fundo
     POST ?acao=musica_remover        -> remove a música (volta a não ter trilha sonora)
     GET  ?acao=minigame_sprites_listar -> os 20 slots de sprite dos 4 minigames (emoji padrão + imagem/escala, se configurados)
     POST ?acao=minigame_sprite_imagem  (multipart: arquivo, chave) -> sobe pra assets/minigames/
     POST ?acao=minigame_sprite_remover {chave} -> volta esse slot pro emoji padrão
     POST ?acao=minigame_sprite_escala_salvar {chave, escala} -> tamanho da imagem (50 a 200%), só com imagem já enviada
     GET  ?acao=minigame_configs_listar -> fundo + sons de acerto/erro dos 4 minigames (memoria/chuva/toca/sequencia)
     POST ?acao=minigame_fundo_imagem   (multipart: arquivo, jogo) -> imagem de fundo desse minigame, sobe pra assets/minigames/
     POST ?acao=minigame_fundo_remover  {jogo} -> volta pro fundo padrão
     POST ?acao=minigame_som_upload     (multipart: arquivo, jogo, evento=acerto|erro) -> substitui o bipe padrão
     POST ?acao=minigame_som_remover    {jogo, evento} -> volta pro bipe padrão
     GET  ?acao=temporadas_listar     -> pacotes de sprite por período (ex.: "Festa Junina"), com agenda e quantos slots têm override
     GET  ?acao=temporada_obter       ?id=xx -> temporada + seus 20 slots (override ou vazio = usa o sprite padrão)
     POST ?acao=temporada_salvar      {id, nome, ativa, ordem, diasSemana, horaInicio, horaFim, dataInicio, dataFim}
     POST ?acao=temporada_excluir     {id}
     POST ?acao=temporada_duplicar    {id, novoId} -> clona uma temporada (agenda + sprites), fica desativada
     POST ?acao=temporada_sprite_imagem  (multipart: arquivo, temporadaId, chave) -> sobe pra assets/minigames/
     POST ?acao=temporada_sprite_remover {temporadaId, chave} -> volta esse slot da temporada pro sprite padrão
     POST ?acao=temporada_sprite_escala_salvar {temporadaId, chave, escala} -> tamanho (50 a 200%), só com imagem já enviada
     GET  ?acao=lojas_listar
     GET  ?acao=loja_obter            ?id=xx
     POST ?acao=loja_salvar           {id, nome, emoji, publicado, ordem, capaImagem, capaAtiva,
                                        capaDiasSemana, capaHoraInicio, capaHoraFim, capaDataInicio, capaDataFim,
                                        capaAncora=top|center|bottom}
     POST ?acao=loja_excluir          {id}
     POST ?acao=loja_imagem           (multipart: arquivo) -> sobe imagem de capa pra assets/lojas/
     GET  ?acao=itens_loja_listar     [?loja=xx]
     GET  ?acao=item_loja_obter       ?id=xx
     POST ?acao=item_loja_salvar      {id, lojaId, nome, emoji, preco, imagem, fome, alegria,
                                        variantes:[{id,nome,imagem}], diasSemana, horaInicio, horaFim,
                                        dataInicio, dataFim, publicado, ordem, estoqueTotal}
     POST ?acao=item_loja_excluir     {id}
     POST ?acao=item_loja_duplicar    {id, novoId} -> clona um item (fica como rascunho, estoque_vendido zera)
     POST ?acao=item_loja_estoque_resetar {id} -> zera estoque_vendido (repõe o estoque)
     POST ?acao=item_loja_imagem      (multipart: arquivo, slot=id da variante ou vazio p/ imagem base) -> sobe pra assets/lojas/
     GET  ?acao=sokoban_fases_listar
     GET  ?acao=sokoban_fase_obter     ?id=xx
     POST ?acao=sokoban_fase_salvar    {id?, numero, nome, grade, publicada} -> sem id cria uma fase nova
     POST ?acao=sokoban_fase_excluir   {id}
   ========================================================= */

declare(strict_types=1);
require __DIR__ . '/bd.php';
iniciarSessaoSegura();
header('Content-Type: application/json; charset=utf-8');

const TIPOS_BLOCO = ['texto', 'flashcard', 'video', 'cloze', 'cacapalavras', 'pergunta'];
const SERIES_VALIDAS = [
  '1º ano', '2º ano', '3º ano', '4º ano', '5º ano', // Fundamental I
  '6º ano', '7º ano', '8º ano', '9º ano', // Fundamental II
  '1º ano do médio', '2º ano do médio', '3º ano do médio', // Ensino Médio
];
const CORES_VALIDAS = ['var(--manga)', 'var(--rosa)', 'var(--mata)', 'var(--ceu)', 'var(--jabuti)', 'var(--moeda)', 'var(--erro)'];

/* catálogo de links do jogo: pra onde um ponto de uma cena pode levar.
   "tela" é limitado às abas que existem em app.html (RENDER.*) — nada de string livre,
   senão um ponto mal configurado leva a criança pra uma tela que não existe. */
const TIPOS_PONTO = ['mundo', 'cena', 'licao', 'tela', 'aviso', 'npc', 'gatilho', 'item', 'loja'];
// "editarcasa" é a tela de decorar o quarto (arrastar/girar/remover móvel), separada
// da Casa de propósito — lá é só o perfil do bicho (cuidar, ver progresso). Fica fora
// de TELAS_NPC (não faz sentido um NPC flutuar sozinho na tela de decoração).
const TELAS_VALIDAS = ['casa', 'trilhas', 'arcade', 'loja', 'mural', 'perfil', 'lojamoveis', 'editarcasa', 'jornal'];
// onde um NPC pode flutuar sozinho, sem precisar de ponto no mapa. Sem "trilhas": lá
// quem posiciona um NPC é o ponto no mapa mesmo, senão teria dois jeitos de fazer a
// mesma coisa competindo entre si.
const TELAS_NPC = ['casa', 'arcade', 'loja', 'mural', 'perfil', 'lojamoveis'];
// além das telas fixas acima, um NPC também pode flutuar dentro de uma loja genérica
// específica (aba Lojas) — "tela" nesse caso vem como "loja:<id>", id validado contra a
// tabela lojas (é dinâmica, não dá pra fechar num enum como as outras)
function telaNpcValida(string $tela): bool {
  if ($tela === '' || in_array($tela, TELAS_NPC, true)) return true;
  if (str_starts_with($tela, 'loja:')) {
    $st = bd()->prepare('SELECT COUNT(*) FROM lojas WHERE id = ?');
    $st->execute([substr($tela, 5)]);
    return (bool)$st->fetchColumn();
  }
  if (str_starts_with($tela, 'mundo:')) {
    $st = bd()->prepare('SELECT COUNT(*) FROM mundos WHERE id = ?');
    $st->execute([substr($tela, 6)]);
    return (bool)$st->fetchColumn();
  }
  return false;
}
const ROTULOS_TELA = ['lojamoveis' => 'Loja de Móveis', 'editarcasa' => 'Casa — editor de móveis'];
const EXTENSOES_IMAGEM = ['webp' => 'image/webp', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];
// música de fundo: sem getimagesize() aqui, é áudio — só confere extensão (o navegador
// do aluno decide se toca; nada crítico o suficiente pra validar o conteúdo do arquivo)
const EXTENSOES_AUDIO = ['mp3' => true, 'ogg' => true, 'wav' => true, 'm4a' => true];

/* catálogo de objetivos de missão — três tipos hoje, mas fechado e validado igual a
   TIPOS_BLOCO/TIPOS_PONTO, pra dar pra crescer sem virar bagunça de string livre. */
const TIPOS_MISSAO = ['entregar_item', 'visitar_cena', 'gatilho'];

function validarId(string $id): bool {
  return (bool)preg_match('/^[a-z0-9]{2,24}$/', $id);
}
/* nome de arquivo de imagem de cena: só o que o painel gera/aceita, sem caminho.
   Barra, "..", byte nulo ou extensão estranha viram recusa — o valor entra num <img src>. */
function validarNomeImagem(string $nome): bool {
  if ($nome === '' || strlen($nome) > 160) return false;
  if (!preg_match('/^[A-Za-z0-9._-]+$/', $nome)) return false;
  if (str_contains($nome, '..')) return false;
  $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
  return isset(EXTENSOES_IMAGEM[$ext]);
}
function pastaCenas(): string { return dirname(__DIR__) . '/assets/cenas'; }
function pastaNpcs(): string { return dirname(__DIR__) . '/assets/npcs'; }
function pastaItens(): string { return dirname(__DIR__) . '/assets/itens'; }
function pastaMoveis(): string { return dirname(__DIR__) . '/assets/moveis'; }
function pastaLojas(): string { return dirname(__DIR__) . '/assets/lojas'; }
function pastaMundos(): string { return dirname(__DIR__) . '/assets/mundos'; }
function pastaMinigames(): string { return dirname(__DIR__) . '/assets/minigames'; }
function pastaJornal(): string { return dirname(__DIR__) . '/assets/jornal'; }
function caminhoPublicoMundo(string $nome): string { return $nome === '' ? '' : 'assets/mundos/' . $nome; }
function caminhoPublicoNpc(string $nome): string { return $nome === '' ? '' : 'assets/npcs/' . $nome; }
// expressões extras (opcionais) que um nó do diálogo pode escolher no lugar da imagem
// principal do NPC — catálogo fechado, mesmo espírito de whitelist usado em outros lugares
const EXPRESSOES_NPC = ['feliz', 'triste', 'surpreso'];
// estados do bicho que o diálogo pode usar pra escolher um nó de entrada diferente do
// normal (ex.: o NPC comentar que o bicho tá com fome antes do papo de sempre) — "baixa"
// é decidido no cliente (mesmo corte de 40 usado nos humores da Casa)
const ESTADOS_ENTRADA_NPC = ['fome_baixa', 'felicidade_baixa', 'energia_baixa'];
function caminhoPublicoItem(string $nome): string { return $nome === '' ? '' : 'assets/itens/' . $nome; }
function caminhoPublicoMovel(string $nome): string { return $nome === '' ? '' : 'assets/moveis/' . $nome; }
function caminhoPublicoLoja(string $nome): string { return $nome === '' ? '' : 'assets/lojas/' . $nome; }
function caminhoPublicoMinigame(string $nome): string { return $nome === '' ? '' : 'assets/minigames/' . $nome; }
function caminhoPublicoJornal(string $nome): string { return $nome === '' ? '' : 'assets/jornal/' . $nome; }

/* catálogo fechado dos "slots" de sprite dos minigames (Arcade) — cada um tem um emoji
   padrão (o que já vinha embutido no jogo) que continua valendo enquanto o Hostmaster não
   subir uma imagem própria. Fechado igual a TIPOS_BLOCO/TIPOS_PONTO: nada de chave livre
   indo pro banco, senão o cliente vira alvo de upload em local arbitrário. */
const SPRITES_MINIGAME = [
  'memoria1' => ['emoji' => '🍉', 'rotulo' => 'Memória do Bicho — carta 1'],
  'memoria2' => ['emoji' => '🐟', 'rotulo' => 'Memória do Bicho — carta 2'],
  'memoria3' => ['emoji' => '🦴', 'rotulo' => 'Memória do Bicho — carta 3'],
  'memoria4' => ['emoji' => '🧀', 'rotulo' => 'Memória do Bicho — carta 4'],
  'memoria5' => ['emoji' => '🍎', 'rotulo' => 'Memória do Bicho — carta 5'],
  'memoria6' => ['emoji' => '🍪', 'rotulo' => 'Memória do Bicho — carta 6'],
  'chuva1' => ['emoji' => '🍉', 'rotulo' => 'Chuva de Frutas — item 1 (melancia, vale 1)'],
  'chuva2' => ['emoji' => '🍎', 'rotulo' => 'Chuva de Frutas — item 2 (maçã, vale 1)'],
  'chuva3' => ['emoji' => '🍌', 'rotulo' => 'Chuva de Frutas — item 3 (banana, vale 1)'],
  'chuva4' => ['emoji' => '🍓', 'rotulo' => 'Chuva de Frutas — item 4 (morango, vale 2)'],
  'chuva5' => ['emoji' => '🥫', 'rotulo' => 'Chuva de Frutas — item 5 (lata, tira 2 — evitar)'],
  'chuvacesta' => ['emoji' => '', 'rotulo' => 'Chuva de Frutas — cesta (sem imagem: retângulo colorido)'],
  'tocacapivara' => ['emoji' => '🦫', 'rotulo' => 'Toca do Bicho — alvo (bicho capivara)'],
  'tocasalsicha' => ['emoji' => '🐶', 'rotulo' => 'Toca do Bicho — alvo (bicho cachorro-salsicha)'],
  'tocagato' => ['emoji' => '🐱', 'rotulo' => 'Toca do Bicho — alvo (bicho gato)'],
  'tocaraio' => ['emoji' => '⚡', 'rotulo' => 'Toca do Bicho — raio (evitar)'],
  'sequencia1' => ['emoji' => '🍉', 'rotulo' => 'Sequência do Bicho — quadrado 1'],
  'sequencia2' => ['emoji' => '🌸', 'rotulo' => 'Sequência do Bicho — quadrado 2'],
  'sequencia3' => ['emoji' => '🍀', 'rotulo' => 'Sequência do Bicho — quadrado 3'],
  'sequencia4' => ['emoji' => '⭐', 'rotulo' => 'Sequência do Bicho — quadrado 4'],
];
/* os 4 minigames em si (não os slots de sprite) — usado pra validar "jogo" nos endpoints
   de fundo/som, mesmo espírito de whitelist fechada do SPRITES_MINIGAME acima */
const JOGOS_ARCADE = [
  'memoria' => 'Memória do Bicho', 'chuva' => 'Chuva de Frutas',
  'toca' => 'Toca do Bicho', 'sequencia' => 'Sequência do Bicho',
];
/* a imagem pode estar em assets/cenas/ (enviada pelo painel) ou em assets/ (veio no
   repositório, como o mapa-mundosv2.webp da ilha) — o front tenta nessa ordem */
function caminhoPublicoImagem(string $nome): string {
  return is_file(pastaCenas() . '/' . $nome) ? 'assets/cenas/' . $nome : 'assets/' . $nome;
}

/** valida um ponto vindo do editor. @return string|null mensagem de erro, ou null se ok */
function validarPonto(array $p, int $i): ?string {
  $rotulo = trim((string)($p['rotulo'] ?? ''));
  if ($rotulo === '' || mb_strlen($rotulo) > 60) return "Ponto $i: o rótulo precisa ter de 1 a 60 caracteres.";
  foreach (['x', 'y', 'largura', 'altura'] as $campo) {
    if (!is_numeric($p[$campo] ?? null)) return "Ponto \"$rotulo\": $campo inválido.";
    $v = (float)$p[$campo];
    if ($v < 0 || $v > 100) return "Ponto \"$rotulo\": $campo fora de 0–100%.";
  }
  if ((float)$p['largura'] <= 0 || (float)$p['altura'] <= 0) return "Ponto \"$rotulo\": largura e altura precisam ser maiores que zero.";
  $tipo = $p['tipo'] ?? null;
  if (!in_array($tipo, TIPOS_PONTO, true)) return "Ponto \"$rotulo\": tipo de destino desconhecido.";
  $destino = trim((string)($p['destino'] ?? ''));
  if ($destino === '') return "Ponto \"$rotulo\": escolha o destino.";

  if ($tipo === 'tela' && !in_array($destino, TELAS_VALIDAS, true)) {
    return "Ponto \"$rotulo\": tela \"$destino\" não existe (use: " . implode(', ', TELAS_VALIDAS) . ').';
  }
  if ($tipo === 'aviso' && mb_strlen($destino) > 160) return "Ponto \"$rotulo\": o aviso passou de 160 caracteres.";
  // mundo/cena/licao/npc/item/loja: confere se o alvo existe de verdade, pra não gerar link quebrado
  $tabelas = ['mundo' => 'mundos', 'cena' => 'cenas', 'licao' => 'licoes', 'npc' => 'npcs', 'item' => 'itens', 'loja' => 'lojas'];
  if (isset($tabelas[$tipo])) {
    $st = bd()->prepare('SELECT COUNT(*) FROM ' . $tabelas[$tipo] . ' WHERE id = ?');
    $st->execute([$destino]);
    if (!$st->fetchColumn()) return "Ponto \"$rotulo\": não existe $tipo com id \"$destino\".";
  }
  // gatilho: destino é a "chave" que alguma missão tipo "gatilho" espera — confere contra
  // o objetivo dela em vez de contra uma tabela, pra não ter typo entre missão e ponto
  if ($tipo === 'gatilho') {
    $st = bd()->prepare('SELECT COUNT(*) FROM missoes WHERE tipo = \'gatilho\' AND JSON_UNQUOTE(JSON_EXTRACT(objetivo, \'$.chave\')) = ?');
    $st->execute([$destino]);
    if (!$st->fetchColumn()) return "Ponto \"$rotulo\": nenhuma missão espera o gatilho \"$destino\". Crie a missão primeiro, na aba Missões.";
  }

  // requisito: opcional, em QUALQUER tipo de ponto — só aparece pro aluno se ele já tiver
  // esse item no inventário (o "binóculo destrava a janela"). Confere que o item existe.
  $requisitoItem = trim((string)($p['requisitoItem'] ?? ''));
  if ($requisitoItem !== '') {
    $st = bd()->prepare('SELECT COUNT(*) FROM itens WHERE id = ?');
    $st->execute([$requisitoItem]);
    if (!$st->fetchColumn()) return "Ponto \"$rotulo\": o item exigido \"$requisitoItem\" não existe.";
  }
  return null;
}

/** valida um item colecionável. @return string|null mensagem de erro, ou null se ok */
function validarItem(array $it): ?string {
  $nome = trim((string)($it['nome'] ?? ''));
  if ($nome === '' || mb_strlen($nome) > 60) return '"nome" precisa ter de 1 a 60 caracteres.';
  $emoji = (string)($it['emoji'] ?? '🔹');
  if ($emoji === '' || mb_strlen($emoji) > 8) return '"emoji" é obrigatório.';
  $descricao = trim((string)($it['descricao'] ?? ''));
  if (mb_strlen($descricao) > 200) return '"descricao" passou de 200 caracteres.';
  return null;
}

/* valida um móvel da Loja de Móveis. "rotativel" só liga o botão de girar no cliente —
   não bloqueia salvar sem as 4 imagens: falta alguma rotação, o cliente cai pra
   imagem_frente nela. Só a de frente é obrigatória mesmo (sem imagem não dá pra vender). */
function validarMovel(array $m): ?string {
  $nome = trim((string)($m['nome'] ?? ''));
  if ($nome === '' || mb_strlen($nome) > 60) return '"nome" precisa ter de 1 a 60 caracteres.';
  if (!is_numeric($m['preco'] ?? null) || (int)$m['preco'] < 0 || (int)$m['preco'] > 100000) {
    return '"preco" precisa ser um número entre 0 e 100000.';
  }
  $frente = trim((string)($m['imagemFrente'] ?? ''));
  if ($frente === '') return 'Envie ao menos a imagem de frente do móvel.';
  foreach (['imagemFrente', 'imagemDireita', 'imagemVerso', 'imagemEsquerda'] as $campo) {
    $nomeImg = trim((string)($m[$campo] ?? ''));
    if ($nomeImg !== '' && !validarNomeImagem($nomeImg)) return "\"$campo\" tem um nome de arquivo inválido.";
  }
  return validarAgenda($m);
}

/** valida uma loja genérica (Lanchonete, Mercado, ou o que o Hostmaster criar depois).
    @return string|null mensagem de erro, ou null se ok */
function validarLoja(array $l): ?string {
  $nome = trim((string)($l['nome'] ?? ''));
  if ($nome === '' || mb_strlen($nome) > 60) return '"nome" precisa ter de 1 a 60 caracteres.';
  $emoji = (string)($l['emoji'] ?? '🏪');
  if ($emoji === '' || mb_strlen($emoji) > 8) return '"emoji" é obrigatório.';
  $capaImagem = trim((string)($l['capaImagem'] ?? ''));
  if ($capaImagem !== '' && !validarNomeImagem($capaImagem)) return '"capaImagem" tem um nome de arquivo inválido.';
  $erroAncora = validarAncora((string)($l['capaAncora'] ?? 'center'));
  if ($erroAncora) return $erroAncora;
  return validarAgenda([
    'diasSemana' => $l['capaDiasSemana'] ?? '', 'horaInicio' => $l['capaHoraInicio'] ?? '', 'horaFim' => $l['capaHoraFim'] ?? '',
    'dataInicio' => $l['capaDataInicio'] ?? '', 'dataFim' => $l['capaDataFim'] ?? '',
  ]);
}

/* valida as variantes de um item de loja (JSON: [{id, nome, imagem}]) — sabores/tamanhos
   que o aluno escolhe na hora de comprar. Cada variante precisa de id (curto, vira parte
   da chave no inventário) e nome; imagem é opcional (cai pro emoji/imagem base do item). */
function validarVariantes($variantes): ?string {
  if (!is_array($variantes)) return '"variantes" precisa ser uma lista.';
  if (count($variantes) > 8) return 'Máximo de 8 variantes por item.';
  $ids = [];
  foreach ($variantes as $i => $v) {
    if (!is_array($v)) return "Variante $i: formato inválido.";
    $id = (string)($v['id'] ?? '');
    if (!preg_match('/^[a-z0-9]{1,24}$/', $id)) return "Variante $i: \"id\" precisa ter de 1 a 24 letras minúsculas ou números.";
    if (in_array($id, $ids, true)) return "Variante \"$id\" repetida.";
    $ids[] = $id;
    $nome = trim((string)($v['nome'] ?? ''));
    if ($nome === '' || mb_strlen($nome) > 40) return "Variante \"$id\": \"nome\" precisa ter de 1 a 40 caracteres.";
    $imagem = trim((string)($v['imagem'] ?? ''));
    if ($imagem !== '' && !validarNomeImagem($imagem)) return "Variante \"$id\": nome de arquivo de imagem inválido.";
  }
  return null;
}

/** valida um item de uma loja genérica. @return string|null mensagem de erro, ou null se ok */
const TIPOS_ITEM_LOJA = ['comida', 'acessorio'];
function validarItemLoja(array $it): ?string {
  $nome = trim((string)($it['nome'] ?? ''));
  if ($nome === '' || mb_strlen($nome) > 60) return '"nome" precisa ter de 1 a 60 caracteres.';
  $emoji = (string)($it['emoji'] ?? '🔹');
  if ($emoji === '' || mb_strlen($emoji) > 8) return '"emoji" é obrigatório.';
  $tipoItem = (string)($it['tipo'] ?? 'comida');
  if (!in_array($tipoItem, TIPOS_ITEM_LOJA, true)) return '"tipo" precisa ser "comida" ou "acessorio".';
  if (!is_numeric($it['preco'] ?? null) || (int)$it['preco'] < 0 || (int)$it['preco'] > 100000) {
    return '"preco" precisa ser um número entre 0 e 100000.';
  }
  foreach (['fome', 'alegria'] as $campo) {
    if (!is_numeric($it[$campo] ?? 0) || (int)($it[$campo] ?? 0) < 0 || (int)($it[$campo] ?? 0) > 100) {
      return "\"$campo\" precisa ser um número entre 0 e 100 (ou vazio, se o item não alimenta).";
    }
  }
  $imagem = trim((string)($it['imagem'] ?? ''));
  if ($imagem !== '' && !validarNomeImagem($imagem)) return '"imagem" tem um nome de arquivo inválido.';
  if (!is_numeric($it['estoqueTotal'] ?? 0) || (int)($it['estoqueTotal'] ?? 0) < 0 || (int)($it['estoqueTotal'] ?? 0) > 1000000) {
    return '"estoqueTotal" precisa ser um número entre 0 e 1000000 (0 = sem limite).';
  }
  $erroVariantes = validarVariantes($it['variantes'] ?? []);
  if ($erroVariantes) return $erroVariantes;
  return validarAgenda($it);
}

/* chave de nó do diálogo: mais permissiva que validarId (aceita "_") pra não atrapalhar
   quem nomeia nós tipo "boas_vindas", mas continua fechada — nada de string livre indo pro banco */
function validarChaveNo(string $c): bool {
  return (bool)preg_match('/^[a-z0-9_]{1,24}$/', $c);
}

/* agenda de exibição de um NPC: dias da semana (1=segunda...7=domingo), horário e
   período de datas — todos opcionais; vazio = sem restrição naquele critério. Quem
   decide se "agora" cai dentro é o app.html (hora do aparelho de quem está jogando,
   não do servidor), então aqui só valida o formato. */
function validarHorario(string $h): bool {
  return $h === '' || (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h);
}
function validarData(string $d): bool {
  if ($d === '') return true;
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
  [$ano, $mes, $dia] = array_map('intval', explode('-', $d));
  return checkdate($mes, $dia, $ano);
}
function validarDiasSemana(string $s): bool {
  if ($s === '') return true;
  foreach (explode(',', $s) as $d) if (!preg_match('/^[1-7]$/', $d)) return false;
  return true;
}
/** normaliza "3,1,1,2" -> "1,2,3": ordenado e sem repetição, pra gravar sempre igual */
function normalizarDiasSemana(string $s): string {
  if ($s === '') return '';
  $dias = array_unique(array_map('intval', explode(',', $s)));
  sort($dias);
  return implode(',', $dias);
}
/** valida o formato dos 5 campos de agenda vindos do formulário (diasSemana, horaInicio,
    horaFim, dataInicio, dataFim) — mesma checagem usada pelos NPCs e pelos móveis
    (estoque por tempo limitado na Loja de Móveis). @return string|null erro, ou null se ok */
function validarAgenda(array $d): ?string {
  $diasSemana = normalizarDiasSemana(trim((string)($d['diasSemana'] ?? '')));
  if (!validarDiasSemana($diasSemana)) return '"diasSemana" precisa ser uma lista de números de 1 (segunda) a 7 (domingo), separados por vírgula.';
  $horaInicio = trim((string)($d['horaInicio'] ?? ''));
  $horaFim = trim((string)($d['horaFim'] ?? ''));
  if (!validarHorario($horaInicio) || !validarHorario($horaFim)) return 'Horário inválido: use o formato HH:MM.';
  if (($horaInicio === '') !== ($horaFim === '')) return 'Preencha o horário de início e fim, ou deixe os dois vazios.';
  $dataInicio = trim((string)($d['dataInicio'] ?? ''));
  $dataFim = trim((string)($d['dataFim'] ?? ''));
  if (!validarData($dataInicio) || !validarData($dataFim)) return 'Data inválida: use o formato AAAA-MM-DD.';
  if (($dataInicio === '') !== ($dataFim === '')) return 'Preencha a data de início e fim, ou deixe as duas vazias.';
  if ($dataInicio !== '' && $dataFim !== '' && $dataInicio > $dataFim) return 'A data de início precisa vir antes (ou no mesmo dia) da data de fim.';
  return null;
}
/** ponto de ancoragem vertical da imagem de capa — decide que parte da imagem fica visível
    quando o cabeçalho (proporção fixa) corta a imagem original. @return string|null erro */
const ANCORAS_CAPA_VALIDAS = ['top', 'center', 'bottom'];
function validarAncora(string $ancora): ?string {
  if (!in_array($ancora, ANCORAS_CAPA_VALIDAS, true)) return '"capaAncora" precisa ser "top", "center" ou "bottom".';
  return null;
}

/** valida a árvore de diálogo de um NPC. @return string|null mensagem de erro, ou null se ok */
function validarDialogo(array $d): ?string {
  if (!is_array($d['nos'] ?? null) || !$d['nos']) return 'O diálogo precisa ter ao menos 1 nó.';
  if (count($d['nos']) > 60) return 'O diálogo passou de 60 nós — divida em outro NPC.';
  foreach ($d['nos'] as $chave => $no) {
    if (!is_string($chave) || !validarChaveNo($chave)) return "Nó \"$chave\": chave inválida (use letras minúsculas, números e _, até 24 caracteres).";
    if (!is_array($no)) return "Nó \"$chave\": formato inválido.";
    $texto = trim((string)($no['texto'] ?? ''));
    if ($texto === '' || mb_strlen($texto) > 300) return "Nó \"$chave\": o texto do balão precisa ter de 1 a 300 caracteres.";
    $expressao = (string)($no['expressao'] ?? '');
    if ($expressao !== '' && !in_array($expressao, EXPRESSOES_NPC, true)) return "Nó \"$chave\": expressão desconhecida.";
    // variações da mesma fala — o cliente sorteia uma toda vez que o nó aparece, pra não
    // repetir sempre a mesma frase quando o aluno conversa de novo com o NPC
    $variantes = $no['variantes'] ?? [];
    if (!is_array($variantes)) return "Nó \"$chave\": formato de variações inválido.";
    if (count($variantes) > 2) return "Nó \"$chave\": no máximo 2 variações extras de fala por nó.";
    foreach ($variantes as $v) {
      if (!is_string($v) || trim($v) === '' || mb_strlen($v) > 300) return "Nó \"$chave\": cada variação de fala precisa ter de 1 a 300 caracteres.";
    }
    if (!is_array($no['opcoes'] ?? null) || !$no['opcoes']) return "Nó \"$chave\": precisa de ao menos 1 opção (botão).";
    if (count($no['opcoes']) > 4) return "Nó \"$chave\": no máximo 4 opções por nó.";
    foreach ($no['opcoes'] as $j => $op) {
      if (!is_array($op)) return "Nó \"$chave\", opção $j: formato inválido.";
      $rotulo = trim((string)($op['rotulo'] ?? ''));
      if ($rotulo === '' || mb_strlen($rotulo) > 40) return "Nó \"$chave\", opção $j: o texto do botão precisa ter de 1 a 40 caracteres.";
      $proximo = $op['proximo'] ?? null;
      if ($proximo !== null && !isset($d['nos'][$proximo])) return "Nó \"$chave\", opção \"$rotulo\": aponta pra um nó que não existe.";

      // ação do botão (oferecer/entregar missão) e a condição pra ele aparecer (mostrarSe)
      // são opcionais e só referenciam uma missão do catálogo central — o NPC não guarda
      // nada da missão em si, só o id dela
      $acao = $op['acao'] ?? null;
      if ($acao !== null) {
        if (!is_array($acao) || !in_array($acao['tipo'] ?? null, ['oferecer', 'entregar'], true))
          return "Nó \"$chave\", opção \"$rotulo\": ação inválida (use oferecer ou entregar).";
        $missaoId = (string)($acao['missaoId'] ?? '');
        if ($missaoId === '') return "Nó \"$chave\", opção \"$rotulo\": escolha a missão da ação.";
        $st = bd()->prepare('SELECT COUNT(*) FROM missoes WHERE id = ?');
        $st->execute([$missaoId]);
        if (!$st->fetchColumn()) return "Nó \"$chave\", opção \"$rotulo\": a missão \"$missaoId\" não existe.";
      }
      $mostrarSe = $op['mostrarSe'] ?? null;
      if ($mostrarSe !== null) {
        $estadosValidos = ['sem_missao', 'missao_ativa', 'missao_pronta', 'missao_completa'];
        if (!is_array($mostrarSe) || !in_array($mostrarSe['estado'] ?? null, $estadosValidos, true))
          return "Nó \"$chave\", opção \"$rotulo\": condição \"mostrar se\" inválida.";
        $missaoId2 = (string)($mostrarSe['missaoId'] ?? '');
        if ($missaoId2 === '') return "Nó \"$chave\", opção \"$rotulo\": escolha a missão da condição \"mostrar se\".";
        $st = bd()->prepare('SELECT COUNT(*) FROM missoes WHERE id = ?');
        $st->execute([$missaoId2]);
        if (!$st->fetchColumn()) return "Nó \"$chave\", opção \"$rotulo\": a missão \"$missaoId2\" (em \"mostrar se\") não existe.";
      }
    }
  }
  $inicial = $d['inicial'] ?? null;
  if (!is_string($inicial) || !isset($d['nos'][$inicial])) return 'Escolha um nó inicial válido pro diálogo.';

  // nó alternativo pra quando o aluno já conversou com esse NPC antes (2ª visita em
  // diante) — opcional; sem escolher, a conversa sempre recomeça pelo nó inicial de cima
  $inicialRetorno = (string)($d['inicialRetorno'] ?? '');
  if ($inicialRetorno !== '' && !isset($d['nos'][$inicialRetorno])) return 'O nó pra "já conversamos antes" aponta pra um nó que não existe.';

  // reações ao estado do bicho: lista ordenada, a primeira que bater decide o nó de
  // entrada (tem prioridade até sobre o "já conversamos antes") — catálogo fechado de
  // estados, cada um no máximo 1 vez
  $condicoesEntrada = $d['condicoesEntrada'] ?? [];
  if (!is_array($condicoesEntrada)) return 'Formato de condições de entrada inválido.';
  if (count($condicoesEntrada) > count(ESTADOS_ENTRADA_NPC)) return 'No máximo ' . count(ESTADOS_ENTRADA_NPC) . ' condições de entrada.';
  $estadosUsados = [];
  foreach ($condicoesEntrada as $c) {
    if (!is_array($c) || !in_array($c['estado'] ?? null, ESTADOS_ENTRADA_NPC, true)) return 'Condição de entrada inválida.';
    if (in_array($c['estado'], $estadosUsados, true)) return 'Cada estado do bicho só pode aparecer 1 vez nas condições de entrada.';
    $estadosUsados[] = $c['estado'];
    if (!is_string($c['no'] ?? null) || !isset($d['nos'][$c['no']])) return 'Uma condição de entrada aponta pra um nó que não existe.';
  }
  return null;
}

/** valida um NPC completo (mesmo formato do npc_salvar) — usado tanto ali quanto no
    import em massa, pra não ter duas checagens que podem desalinhar com o tempo.
    @return string|null mensagem de erro, ou null se ok */
function validarNpc(array $d): ?string {
  if (!validarId((string)($d['id'] ?? ''))) return '"id" inválido: use de 2 a 24 letras minúsculas ou números.';
  $nome = trim((string)($d['nome'] ?? ''));
  if ($nome === '' || mb_strlen($nome) > 60) return '"nome" precisa ter de 1 a 60 caracteres.';
  $emoji = (string)($d['emoji'] ?? '🧑');
  if ($emoji === '' || mb_strlen($emoji) > 8) return '"emoji" é obrigatório.';
  $imagemTipo = (string)($d['imagemTipo'] ?? 'png');
  if (!in_array($imagemTipo, ['png', 'lottie'], true)) return '"imagemTipo" precisa ser "png" ou "lottie".';
  $tela = trim((string)($d['tela'] ?? ''));
  if (!telaNpcValida($tela)) return '"tela" precisa ser uma tela válida, uma loja existente ("loja:id"), ou vazio (só via ponto no mapa).';
  $erroAgenda = validarAgenda($d);
  if ($erroAgenda) return $erroAgenda;
  $dialogo = $d['dialogo'] ?? null;
  if (!is_array($dialogo)) return 'Formato de diálogo inválido.';
  $erro = validarDialogo($dialogo);
  if ($erro) return $erro;
  // expressões extras: {chave: nome-do-arquivo} — só confere que a chave é do catálogo
  // fechado; o arquivo em si é enviado à parte (upload de imagem não viaja dentro do JSON)
  $expressoesEntrada = $d['expressoes'] ?? [];
  if (!is_array($expressoesEntrada)) return 'Formato de expressões inválido.';
  foreach ($expressoesEntrada as $chaveExpr => $arquivoExpr) {
    if (!in_array($chaveExpr, EXPRESSOES_NPC, true)) return "Expressão \"$chaveExpr\" desconhecida.";
  }
  return null;
}
function expressoesLimpas(array $expressoesEntrada): array {
  $expressoes = [];
  foreach ($expressoesEntrada as $chaveExpr => $arquivoExpr) {
    $arquivoExpr = trim((string)$arquivoExpr);
    if ($arquivoExpr !== '') $expressoes[$chaveExpr] = $arquivoExpr;
  }
  return $expressoes;
}

/** valida uma missão. @return string|null mensagem de erro, ou null se ok */
function validarMissao(array $m): ?string {
  $titulo = trim((string)($m['titulo'] ?? ''));
  if ($titulo === '' || mb_strlen($titulo) > 100) return '"titulo" precisa ter de 1 a 100 caracteres.';
  $descricao = trim((string)($m['descricao'] ?? ''));
  if ($descricao === '' || mb_strlen($descricao) > 300) return '"descricao" precisa ter de 1 a 300 caracteres — é o que o aluno vê no diário de missões.';
  $tipo = $m['tipo'] ?? null;
  if (!in_array($tipo, TIPOS_MISSAO, true)) return '"tipo" precisa ser um destes: ' . implode(', ', TIPOS_MISSAO) . '.';
  $objetivo = $m['objetivo'] ?? null;
  if (!is_array($objetivo)) return '"objetivo" inválido.';
  if ($tipo === 'entregar_item') {
    $itemId = (string)($objetivo['itemId'] ?? '');
    // só item tipo "comida" entra em missão de entrega: é o único que o jogador guarda
    // com quantidade (estado.inventario); acessório vai pra mochila sem contador
    $st = bd()->prepare("SELECT COUNT(*) FROM itens_loja WHERE id = ? AND tipo = 'comida'");
    $st->execute([$itemId]);
    if (!$st->fetchColumn()) return "Escolha um item de comida que exista numa loja (\"$itemId\" não encontrado).";
    $quantidade = $objetivo['quantidade'] ?? null;
    if (!is_int($quantidade) || $quantidade < 1 || $quantidade > 99) return '"quantidade" precisa ser um número inteiro de 1 a 99.';
  } elseif ($tipo === 'visitar_cena') {
    $cenaId = (string)($objetivo['cenaId'] ?? '');
    $st = bd()->prepare('SELECT COUNT(*) FROM cenas WHERE id = ?');
    $st->execute([$cenaId]);
    if (!$st->fetchColumn()) return "Escolha um mapa que exista (\"$cenaId\" não encontrado).";
  } elseif ($tipo === 'gatilho') {
    $chave = (string)($objetivo['chave'] ?? '');
    if (!validarChaveNo($chave)) return '"chave" do gatilho inválida (use letras minúsculas, números e _, até 24 caracteres).';
  }
  $premio = $m['premio'] ?? null;
  if (!is_array($premio)) return '"premio" inválido.';
  $moedas = $premio['moedas'] ?? 0;
  $xp = $premio['xp'] ?? 0;
  if (!is_int($moedas) || $moedas < 0 || $moedas > 500) return '"premio.moedas" precisa ser um número inteiro de 0 a 500.';
  if (!is_int($xp) || $xp < 0 || $xp > 500) return '"premio.xp" precisa ser um número inteiro de 0 a 500.';
  return null;
}

/* Grade de uma fase do Empurra-Caixas (minigame estilo Sokoban) — mesma notação que o
   parser de app.html usa: # parede, @ jogador, $ caixa, . alvo, + jogador-no-alvo,
   * caixa-no-alvo, ^ espinho, % gatilho, D porta, espaço = chão. Só confere estrutura
   (exatamente 1 jogador, caixas >= alvos, caracteres válidos, tamanho razoável) — não roda
   um solver aqui pra confirmar que dá pra resolver (isso o admin confere jogando a fase
   depois de publicar). */
function validarSokobanGrade(string $grade): ?string {
  if (trim($grade) === '') return '"grade" não pode ficar vazia.';
  if (mb_strlen($grade) > 3000) return 'A grade tá grande demais (máximo 3000 caracteres).';
  $linhas = explode("\n", $grade);
  if (count($linhas) < 3 || count($linhas) > 30) return 'A grade precisa ter de 3 a 30 linhas.';
  foreach ($linhas as $i => $linha) {
    if (mb_strlen($linha) > 40) return 'Linha ' . ($i + 1) . ': no máximo 40 colunas.';
    if (preg_match('/[^ #@\$.+*^%D]/', $linha)) {
      return 'Linha ' . ($i + 1) . ': só pode ter espaço, # @ $ . + * ^ % D.';
    }
  }
  $jogadores = 0; $caixas = 0; $alvos = 0;
  foreach ($linhas as $linha) {
    $jogadores += substr_count($linha, '@') + substr_count($linha, '+');
    $caixas += substr_count($linha, '$') + substr_count($linha, '*');
    $alvos += substr_count($linha, '.') + substr_count($linha, '+') + substr_count($linha, '*');
  }
  if ($jogadores !== 1) return "A grade precisa ter exatamente 1 jogador (@ ou +) — tem $jogadores.";
  if ($alvos < 1) return 'A grade precisa ter pelo menos 1 alvo (.).';
  if ($caixas < $alvos) return "Tem menos caixas ($caixas) do que alvos ($alvos) — impossível de resolver.";
  return null;
}

/* Espelha exatamente o que cada INICIAR_BLOCO de app.html espera — um bloco
   com formato errado não trava o admin aqui, trava o aluno lá na hora de
   estudar. É por isso que essa validação existe: pra travar aqui. */
function validarBloco(array $b, int $i): ?string {
  $tipo = $b['tipo'] ?? null;
  if (!in_array($tipo, TIPOS_BLOCO, true)) return "Bloco $i: \"tipo\" ausente ou desconhecido (use: " . implode(', ', TIPOS_BLOCO) . ').';

  if ($tipo === 'texto') {
    if (!is_array($b['paragrafos'] ?? null) || !$b['paragrafos']) return "Bloco $i (texto): precisa de ao menos 1 parágrafo em \"paragrafos\".";
    foreach ($b['paragrafos'] as $p) if (!is_string($p) || $p === '') return "Bloco $i (texto): cada parágrafo precisa ser um texto não vazio.";
  }

  if ($tipo === 'flashcard') {
    if (!is_array($b['cartas'] ?? null) || !$b['cartas']) return "Bloco $i (flashcard): precisa de ao menos 1 item em \"cartas\".";
    foreach ($b['cartas'] as $c) {
      if (!is_array($c) || !is_string($c['frente'] ?? null) || !is_string($c['verso'] ?? null) || $c['frente'] === '' || $c['verso'] === '')
        return "Bloco $i (flashcard): cada carta precisa de \"frente\" e \"verso\" preenchidos.";
    }
  }

  if ($tipo === 'video') {
    if (!is_string($b['videoId'] ?? null) || !preg_match('/^[\w-]{6,15}$/', $b['videoId']))
      return "Bloco $i (video): \"videoId\" precisa ser o ID do vídeo no YouTube (6 a 15 caracteres, sem a URL toda).";
    if (!is_string($b['titulo'] ?? null) || $b['titulo'] === '') return "Bloco $i (video): falta \"titulo\".";
  }

  if ($tipo === 'cloze') {
    if (!is_string($b['frase'] ?? null) || substr_count($b['frase'], '___') !== 1)
      return "Bloco $i (cloze): \"frase\" precisa ter exatamente uma lacuna, marcada como ___.";
    if (!is_array($b['opcoes'] ?? null) || count($b['opcoes']) < 2 || count($b['opcoes']) > 6)
      return "Bloco $i (cloze): \"opcoes\" precisa ter de 2 a 6 alternativas.";
    if (!is_string($b['certa'] ?? null) || !in_array($b['certa'], $b['opcoes'], true))
      return "Bloco $i (cloze): \"certa\" precisa ser um texto idêntico a uma das \"opcoes\".";
  }

  if ($tipo === 'cacapalavras') {
    if (!is_array($b['palavras'] ?? null) || count($b['palavras']) < 2 || count($b['palavras']) > 8)
      return "Bloco $i (cacapalavras): \"palavras\" precisa ter de 2 a 8 itens.";
    foreach ($b['palavras'] as $p) {
      if (!is_string($p) || !preg_match('/^[\p{Lu}]{3,10}$/u', $p))
        return "Bloco $i (cacapalavras): cada palavra precisa ter de 3 a 10 letras MAIÚSCULAS, sem espaço nem número.";
    }
  }

  if ($tipo === 'pergunta') {
    if (!is_string($b['p'] ?? null) || $b['p'] === '') return "Bloco $i (pergunta): falta o texto da pergunta em \"p\".";
    if (!is_array($b['alts'] ?? null) || count($b['alts']) < 2 || count($b['alts']) > 6)
      return "Bloco $i (pergunta): \"alts\" precisa ter de 2 a 6 alternativas.";
    foreach ($b['alts'] as $a) if (!is_string($a) || $a === '') return "Bloco $i (pergunta): toda alternativa em \"alts\" precisa ser texto não vazio.";
    if (!is_int($b['certa'] ?? null) || $b['certa'] < 0 || $b['certa'] >= count($b['alts']))
      return "Bloco $i (pergunta): \"certa\" precisa ser o índice (número, começando em 0) de uma alternativa de \"alts\".";
  }

  return null;
}

/** @return array{erro:string}|array{gabarito:array<int>} */
function validarLicao(array $l): array {
  if (!validarId((string)($l['id'] ?? ''))) return ['erro' => '"id" inválido: use de 2 a 24 letras minúsculas ou números, sem espaço nem acento.'];
  if (!is_string($l['mundoId'] ?? null) || !validarId($l['mundoId'])) return ['erro' => '"mundoId" inválido.'];
  $titulo = trim((string)($l['titulo'] ?? ''));
  if ($titulo === '' || mb_strlen($titulo) > 120) return ['erro' => '"titulo" precisa ter de 1 a 120 caracteres.'];
  $emoji = (string)($l['emoji'] ?? '');
  if ($emoji === '' || mb_strlen($emoji) > 8) return ['erro' => '"emoji" é obrigatório.'];
  if (!in_array($l['serie'] ?? null, SERIES_VALIDAS, true)) return ['erro' => '"serie" precisa ser uma destas: ' . implode(', ', SERIES_VALIDAS)];
  if (!is_array($l['blocos'] ?? null) || !$l['blocos']) return ['erro' => '"blocos" precisa ser uma lista com ao menos 1 item.'];
  foreach ($l['blocos'] as $i => $b) {
    if (!is_array($b)) return ['erro' => "Bloco $i inválido: precisa ser um objeto."];
    $erro = validarBloco($b, $i);
    if ($erro) return ['erro' => $erro];
  }
  $gabarito = array_values(array_map(
    fn($b) => $b['certa'],
    array_filter($l['blocos'], fn($b) => $b['tipo'] === 'pergunta')
  ));
  return ['gabarito' => $gabarito];
}

$acao = $_GET['acao'] ?? '';

try {
  if ($acao === 'admin_entrar') {
    $d = corpo();
    $usuario = trim((string)($d['usuario'] ?? ''));
    $senha = (string)($d['senha'] ?? '');
    $st = bd()->prepare('SELECT id, senha_hash FROM admins WHERE usuario = ?');
    $st->execute([$usuario]);
    $admin = $st->fetch(PDO::FETCH_ASSOC);
    if (!$admin || !password_verify($senha, $admin['senha_hash'])) {
      usleep(400000); // atrasa tentativa de força bruta
      responder(['erro' => 'Usuário ou senha incorretos.'], 401);
    }
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_usuario'] = $usuario;
    responder(['ok' => true, 'usuario' => $usuario]);
  }

  if ($acao === 'admin_sair') {
    unset($_SESSION['admin_id'], $_SESSION['admin_usuario']);
    responder(['ok' => true]);
  }

  if ($acao === 'admin_eu') {
    responder(['logado' => isset($_SESSION['admin_id']), 'usuario' => $_SESSION['admin_usuario'] ?? null]);
  }

  if (!isset($_SESSION['admin_id'])) responder(['erro' => 'Entre no painel primeiro.'], 401);

  if ($acao === 'mundos_listar') {
    $linhas = bd()->query('SELECT m.id, m.nome, m.emoji, m.cor, m.ordem, m.publicado, m.capa_imagem, m.capa_ativa,
        m.capa_dias_semana, m.capa_hora_inicio, m.capa_hora_fim, m.capa_data_inicio, m.capa_data_fim, m.capa_ancora, COUNT(l.id) AS licoes
      FROM mundos m LEFT JOIN licoes l ON l.mundo_id = m.id GROUP BY m.id ORDER BY m.ordem, m.nome')->fetchAll(PDO::FETCH_ASSOC);
    responder(['mundos' => array_map(fn($m) => [
      'id' => $m['id'], 'nome' => $m['nome'], 'emoji' => $m['emoji'], 'cor' => $m['cor'],
      'ordem' => (int)$m['ordem'], 'publicado' => (bool)$m['publicado'], 'licoes' => (int)$m['licoes'],
      'capaImagem' => $m['capa_imagem'], 'capaImagemUrl' => caminhoPublicoMundo($m['capa_imagem']), 'capaAtiva' => (bool)$m['capa_ativa'],
      'capaDiasSemana' => $m['capa_dias_semana'], 'capaHoraInicio' => $m['capa_hora_inicio'], 'capaHoraFim' => $m['capa_hora_fim'],
      'capaDataInicio' => $m['capa_data_inicio'], 'capaDataFim' => $m['capa_data_fim'], 'capaAncora' => $m['capa_ancora'],
    ], $linhas)]);
  }

  if ($acao === 'mundo_salvar') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    if (!validarId($id)) responder(['erro' => '"id" inválido: use de 2 a 24 letras minúsculas ou números.'], 422);
    $nome = trim((string)($d['nome'] ?? ''));
    if ($nome === '' || mb_strlen($nome) > 60) responder(['erro' => '"nome" precisa ter de 1 a 60 caracteres.'], 422);
    $emoji = (string)($d['emoji'] ?? '');
    if ($emoji === '' || mb_strlen($emoji) > 8) responder(['erro' => '"emoji" é obrigatório.'], 422);
    $cor = (string)($d['cor'] ?? '');
    if (!in_array($cor, CORES_VALIDAS, true)) responder(['erro' => '"cor" precisa ser uma das cores do tema: ' . implode(', ', CORES_VALIDAS)], 422);
    $ordem = (int)($d['ordem'] ?? 0);
    $publicado = !empty($d['publicado']) ? 1 : 0;
    $capaImagem = trim((string)($d['capaImagem'] ?? ''));
    if ($capaImagem !== '' && !validarNomeImagem($capaImagem)) responder(['erro' => '"capaImagem" tem um nome de arquivo inválido.'], 422);
    $capaAtiva = !empty($d['capaAtiva']) ? 1 : 0;
    $capaAncora = (string)($d['capaAncora'] ?? 'center');
    $erroAncora = validarAncora($capaAncora);
    if ($erroAncora) responder(['erro' => $erroAncora], 422);
    $erroAgenda = validarAgenda([
      'diasSemana' => $d['capaDiasSemana'] ?? '', 'horaInicio' => $d['capaHoraInicio'] ?? '', 'horaFim' => $d['capaHoraFim'] ?? '',
      'dataInicio' => $d['capaDataInicio'] ?? '', 'dataFim' => $d['capaDataFim'] ?? '',
    ]);
    if ($erroAgenda) responder(['erro' => $erroAgenda], 422);
    bd()->prepare('INSERT INTO mundos (id, nome, emoji, cor, ordem, publicado, capa_imagem, capa_ativa,
        capa_dias_semana, capa_hora_inicio, capa_hora_fim, capa_data_inicio, capa_data_fim, capa_ancora)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE nome=VALUES(nome), emoji=VALUES(emoji), cor=VALUES(cor), ordem=VALUES(ordem), publicado=VALUES(publicado),
        capa_imagem=VALUES(capa_imagem), capa_ativa=VALUES(capa_ativa), capa_dias_semana=VALUES(capa_dias_semana),
        capa_hora_inicio=VALUES(capa_hora_inicio), capa_hora_fim=VALUES(capa_hora_fim),
        capa_data_inicio=VALUES(capa_data_inicio), capa_data_fim=VALUES(capa_data_fim), capa_ancora=VALUES(capa_ancora)')
      ->execute([
        $id, $nome, $emoji, $cor, $ordem, $publicado, $capaImagem, $capaAtiva,
        normalizarDiasSemana(trim((string)($d['capaDiasSemana'] ?? ''))), trim((string)($d['capaHoraInicio'] ?? '')), trim((string)($d['capaHoraFim'] ?? '')),
        trim((string)($d['capaDataInicio'] ?? '')), trim((string)($d['capaDataFim'] ?? '')), $capaAncora,
      ]);
    responder(['ok' => true]);
  }

  if ($acao === 'mundo_imagem') {
    $arq = $_FILES['arquivo'] ?? null;
    if (!$arq || $arq['error'] !== UPLOAD_ERR_OK) responder(['erro' => 'Falha no envio da imagem.'], 422);
    $ext = strtolower(pathinfo((string)$arq['name'], PATHINFO_EXTENSION));
    if (!isset(EXTENSOES_IMAGEM[$ext])) responder(['erro' => 'Formato inválido. Use webp, png ou jpg.'], 422);
    $nome = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!is_dir(pastaMundos()) && !@mkdir(pastaMundos(), 0755, true)) {
      responder(['erro' => 'Não consegui criar a pasta de imagens das matérias no servidor.'], 500);
    }
    if (!@move_uploaded_file($arq['tmp_name'], pastaMundos() . '/' . $nome)) {
      responder(['erro' => 'Não consegui salvar a imagem no servidor.'], 500);
    }
    responder(['ok' => true, 'imagem' => $nome, 'imagemUrl' => caminhoPublicoMundo($nome)]);
  }

  if ($acao === 'mundo_excluir') {
    $d = corpo();
    bd()->prepare('DELETE FROM mundos WHERE id = ?')->execute([(string)($d['id'] ?? '')]);
    responder(['ok' => true]);
  }

  if ($acao === 'licoes_listar') {
    $mundo = $_GET['mundo'] ?? null;
    if ($mundo) {
      $st = bd()->prepare('SELECT id, mundo_id, titulo, emoji, serie, ordem, publicado FROM licoes WHERE mundo_id = ? ORDER BY ordem, titulo');
      $st->execute([(string)$mundo]);
    } else {
      $st = bd()->query('SELECT id, mundo_id, titulo, emoji, serie, ordem, publicado FROM licoes ORDER BY mundo_id, ordem, titulo');
    }
    responder(['licoes' => array_map(fn($l) => [
      'id' => $l['id'], 'mundoId' => $l['mundo_id'], 'titulo' => $l['titulo'], 'emoji' => $l['emoji'],
      'serie' => $l['serie'], 'ordem' => (int)$l['ordem'], 'publicado' => (bool)$l['publicado'],
    ], $st->fetchAll(PDO::FETCH_ASSOC))]);
  }

  if ($acao === 'licao_obter') {
    $st = bd()->prepare('SELECT id, mundo_id, titulo, emoji, serie, ordem, publicado, blocos FROM licoes WHERE id = ?');
    $st->execute([(string)($_GET['id'] ?? '')]);
    $l = $st->fetch(PDO::FETCH_ASSOC);
    if (!$l) responder(['erro' => 'Lição não encontrada.'], 404);
    responder([
      'id' => $l['id'], 'mundoId' => $l['mundo_id'], 'titulo' => $l['titulo'], 'emoji' => $l['emoji'],
      'serie' => $l['serie'], 'ordem' => (int)$l['ordem'], 'publicado' => (bool)$l['publicado'],
      'blocos' => json_decode($l['blocos'], true),
    ]);
  }

  if ($acao === 'licao_desempenho') {
    $licaoId = (string)($_GET['id'] ?? '');
    $st = bd()->prepare('SELECT titulo, blocos FROM licoes WHERE id = ?');
    $st->execute([$licaoId]);
    $l = $st->fetch(PDO::FETCH_ASSOC);
    if (!$l) responder(['erro' => 'Lição não encontrada.'], 404);
    $perguntas = array_values(array_filter(json_decode($l['blocos'], true) ?: [], fn($b) => $b['tipo'] === 'pergunta'));

    $st = bd()->prepare('SELECT indice_pergunta, COUNT(*) tentativas, SUM(acertou) acertos
      FROM respostas WHERE licao_id = ? GROUP BY indice_pergunta');
    $st->execute([$licaoId]);
    $porIndice = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $linha) $porIndice[(int)$linha['indice_pergunta']] = $linha;

    $saida = [];
    foreach ($perguntas as $i => $p) {
      $linha = $porIndice[$i] ?? null;
      $tentativas = $linha ? (int)$linha['tentativas'] : 0;
      $acertos = $linha ? (int)$linha['acertos'] : 0;
      $saida[] = [
        'indice' => $i, 'pergunta' => $p['p'], 'alts' => $p['alts'], 'certa' => $p['certa'],
        'tentativas' => $tentativas, 'acertos' => $acertos,
        'taxaAcerto' => $tentativas ? round($acertos / $tentativas * 100) : null,
      ];
    }
    responder(['titulo' => $l['titulo'], 'perguntas' => $saida]);
  }

  if ($acao === 'licao_salvar') {
    $d = corpo();
    $st = bd()->prepare('SELECT COUNT(*) FROM mundos WHERE id = ?');
    $st->execute([(string)($d['mundoId'] ?? '')]);
    if (!$st->fetchColumn()) responder(['erro' => '"mundoId" não existe. Crie o mundo antes da lição.'], 422);
    $resultado = validarLicao($d);
    if (isset($resultado['erro'])) responder(['erro' => $resultado['erro']], 422);
    bd()->prepare('INSERT INTO licoes (id, mundo_id, titulo, emoji, serie, ordem, publicado, blocos, gabarito)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE mundo_id=VALUES(mundo_id), titulo=VALUES(titulo), emoji=VALUES(emoji), serie=VALUES(serie),
        ordem=VALUES(ordem), publicado=VALUES(publicado), blocos=VALUES(blocos), gabarito=VALUES(gabarito)')
      ->execute([
        (string)$d['id'], (string)$d['mundoId'], trim((string)$d['titulo']), (string)$d['emoji'], (string)$d['serie'],
        (int)($d['ordem'] ?? 0), !empty($d['publicado']) ? 1 : 0,
        json_encode($d['blocos'], JSON_UNESCAPED_UNICODE), json_encode($resultado['gabarito'], JSON_UNESCAPED_UNICODE),
      ]);
    responder(['ok' => true, 'totalPerguntas' => count($resultado['gabarito'])]);
  }

  if ($acao === 'licao_excluir') {
    $d = corpo();
    bd()->prepare('DELETE FROM licoes WHERE id = ?')->execute([(string)($d['id'] ?? '')]);
    responder(['ok' => true]);
  }

  if ($acao === 'jogadores_listar') {
    $busca = trim((string)($_GET['busca'] ?? ''));
    if ($busca !== '') {
      $st = bd()->prepare('SELECT id, apelido, estado, criado_em, atualizado_em FROM jogadores WHERE apelido LIKE ? ORDER BY atualizado_em DESC LIMIT 200');
      $st->execute(['%' . $busca . '%']);
      $linhas = $st->fetchAll(PDO::FETCH_ASSOC);
    } else {
      $linhas = bd()->query('SELECT id, apelido, estado, criado_em, atualizado_em FROM jogadores ORDER BY atualizado_em DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
    }
    responder(['jogadores' => array_map(function ($j) {
      $estado = json_decode((string)$j['estado'], true) ?: [];
      return [
        'id' => (int)$j['id'], 'apelido' => $j['apelido'],
        'moedas' => (int)($estado['moedas'] ?? 0), 'xp' => (int)($estado['xp'] ?? 0),
        'licoesFeitas' => count($estado['licoesFeitas'] ?? []), 'streak' => (int)($estado['streak']['atual'] ?? 0),
        'criadoEm' => $j['criado_em'], 'atualizadoEm' => $j['atualizado_em'],
      ];
    }, $linhas)]);
  }

  if ($acao === 'jogador_resetar_senha') {
    $d = corpo();
    $novaSenha = (string)($d['novaSenha'] ?? '');
    if (mb_strlen($novaSenha) < 4 || mb_strlen($novaSenha) > 60) responder(['erro' => 'Senha precisa ter de 4 a 60 caracteres.'], 422);
    // versao_sessao += 1 derruba qualquer sessão antiga desse jogador (se alguém mais tinha
    // acesso a uma sessão ativa, o reset de senha tira esse acesso); zera bloqueio de
    // tentativas erradas também, já que é um recomeço assistido pelo professor
    bd()->prepare('UPDATE jogadores SET pin_hash = ?, versao_sessao = versao_sessao + 1, tentativas_falhas = 0, bloqueado_ate = NULL WHERE id = ?')
      ->execute([password_hash($novaSenha, PASSWORD_DEFAULT), (int)($d['id'] ?? 0)]);
    responder(['ok' => true]);
  }

  if ($acao === 'jogador_excluir') {
    $d = corpo();
    bd()->prepare('DELETE FROM jogadores WHERE id = ?')->execute([(int)($d['id'] ?? 0)]);
    responder(['ok' => true]);
  }

  /* ---------- Moderação: mensagens privadas e comentários do fórum denunciados ---------- */

  if ($acao === 'moderacao_listar') {
    $mensagens = bd()->query("SELECT m.id, m.texto, m.criado_em,
        jr.apelido AS remetente, jd.apelido AS destinatario
      FROM mensagens m
      JOIN jogadores jr ON jr.id = m.remetente_id
      JOIN jogadores jd ON jd.id = m.destinatario_id
      WHERE m.denunciada = 1 AND m.removida = 0 ORDER BY m.criado_em DESC")->fetchAll(PDO::FETCH_ASSOC);

    $posts = bd()->query("SELECT f.id, f.texto, f.licao_id, f.criado_em, j.apelido AS autor,
        l.titulo AS licao_titulo
      FROM forum_posts f
      JOIN jogadores j ON j.id = f.autor_id
      LEFT JOIN licoes l ON l.id = f.licao_id
      WHERE f.denunciado = 1 AND f.removido = 0 ORDER BY f.criado_em DESC")->fetchAll(PDO::FETCH_ASSOC);

    $mensagensGrupo = bd()->query("SELECT gm.id, gm.texto, gm.criado_em, j.apelido AS remetente, g.nome AS grupo
      FROM grupo_mensagens gm
      JOIN jogadores j ON j.id = gm.remetente_id
      JOIN grupos g ON g.id = gm.grupo_id
      WHERE gm.denunciada = 1 AND gm.removida = 0 ORDER BY gm.criado_em DESC")->fetchAll(PDO::FETCH_ASSOC);

    responder([
      'mensagens' => array_map(fn($m) => [
        'id' => (int)$m['id'], 'texto' => $m['texto'], 'quando' => $m['criado_em'],
        'remetente' => $m['remetente'], 'destinatario' => $m['destinatario'],
      ], $mensagens),
      'posts' => array_map(fn($p) => [
        'id' => (int)$p['id'], 'texto' => $p['texto'], 'quando' => $p['criado_em'],
        'autor' => $p['autor'], 'licao' => $p['licao_titulo'] ?: $p['licao_id'],
      ], $posts),
      'mensagensGrupo' => array_map(fn($m) => [
        'id' => (int)$m['id'], 'texto' => $m['texto'], 'quando' => $m['criado_em'],
        'remetente' => $m['remetente'], 'grupo' => $m['grupo'],
      ], $mensagensGrupo),
    ]);
  }

  if ($acao === 'moderacao_mensagem_remover') {
    bd()->prepare('UPDATE mensagens SET removida = 1, denunciada = 0 WHERE id = ?')->execute([(int)(corpo()['id'] ?? 0)]);
    responder(['ok' => true]);
  }

  if ($acao === 'moderacao_mensagem_ignorar') {
    bd()->prepare('UPDATE mensagens SET denunciada = 0 WHERE id = ?')->execute([(int)(corpo()['id'] ?? 0)]);
    responder(['ok' => true]);
  }

  if ($acao === 'moderacao_post_remover') {
    bd()->prepare('UPDATE forum_posts SET removido = 1, denunciado = 0 WHERE id = ?')->execute([(int)(corpo()['id'] ?? 0)]);
    responder(['ok' => true]);
  }

  if ($acao === 'moderacao_post_ignorar') {
    bd()->prepare('UPDATE forum_posts SET denunciado = 0 WHERE id = ?')->execute([(int)(corpo()['id'] ?? 0)]);
    responder(['ok' => true]);
  }

  if ($acao === 'moderacao_grupo_mensagem_remover') {
    bd()->prepare('UPDATE grupo_mensagens SET removida = 1, denunciada = 0 WHERE id = ?')->execute([(int)(corpo()['id'] ?? 0)]);
    responder(['ok' => true]);
  }

  if ($acao === 'moderacao_grupo_mensagem_ignorar') {
    bd()->prepare('UPDATE grupo_mensagens SET denunciada = 0 WHERE id = ?')->execute([(int)(corpo()['id'] ?? 0)]);
    responder(['ok' => true]);
  }

  /* clubes: nome/descrição já passam pelo filtro de palavra proibida na criação/edição
     (ver clube_criar/clube_editar em estado.php) — isso aqui é a rede de segurança do
     Hostmaster pra casos que passaram batido, ou um clube que virou problema por outro
     motivo (não tem "denúncia" de clube, só dissolver). */
  if ($acao === 'clubes_listar') {
    $st = bd()->query('SELECT c.id, c.nome, c.emoji, c.descricao, c.criado_em, j.apelido AS lider,
        (SELECT COUNT(*) FROM clube_membros WHERE clube_id = c.id) AS total_membros
      FROM clubes c JOIN jogadores j ON j.id = c.lider_id ORDER BY c.criado_em DESC');
    responder(['clubes' => array_map(fn($c) => [
      'id' => (int)$c['id'], 'nome' => $c['nome'], 'emoji' => $c['emoji'], 'descricao' => $c['descricao'],
      'lider' => $c['lider'], 'totalMembros' => (int)$c['total_membros'], 'criadoEm' => $c['criado_em'],
    ], $st->fetchAll(PDO::FETCH_ASSOC))]);
  }

  if ($acao === 'clube_excluir') {
    bd()->prepare('DELETE FROM clubes WHERE id = ?')->execute([(int)(corpo()['id'] ?? 0)]);
    responder(['ok' => true]);
  }

  if ($acao === 'palavras_proibidas_listar') {
    responder(['palavras' => bd()->query('SELECT palavra FROM palavras_proibidas ORDER BY palavra')->fetchAll(PDO::FETCH_COLUMN)]);
  }

  if ($acao === 'palavra_proibida_adicionar') {
    $palavra = trim(mb_strtolower((string)(corpo()['palavra'] ?? '')));
    if ($palavra === '' || mb_strlen($palavra) > 60) responder(['erro' => '"palavra" precisa ter de 1 a 60 caracteres.'], 422);
    bd()->prepare('INSERT INTO palavras_proibidas (palavra, normalizada) VALUES (?, ?) ON DUPLICATE KEY UPDATE normalizada = VALUES(normalizada)')
      ->execute([$palavra, normalizarTexto($palavra)]);
    responder(['ok' => true]);
  }

  if ($acao === 'palavra_proibida_remover') {
    $palavra = trim(mb_strtolower((string)(corpo()['palavra'] ?? '')));
    bd()->prepare('DELETE FROM palavras_proibidas WHERE palavra = ?')->execute([$palavra]);
    responder(['ok' => true]);
  }

  if ($acao === 'metricas') {
    $bdc = bd();
    $totalConcluidas = 0;
    foreach ($bdc->query('SELECT estado FROM jogadores') as $linha) {
      $e = json_decode((string)$linha['estado'], true) ?: [];
      $totalConcluidas += count($e['licoesFeitas'] ?? []);
    }
    $totalLicoes = (int)$bdc->query('SELECT COUNT(*) FROM licoes')->fetchColumn();
    $licoesPublicadas = (int)$bdc->query('SELECT COUNT(*) FROM licoes WHERE publicado = 1')->fetchColumn();

    // engajamento social + moderação — mesma tabela de métricas, pra não espalhar o
    // painel em telas separadas; "duvidas" conta só post-raiz (pergunta em si, sem
    // contar as respostas), pra apontar QUAL lição gera mais dúvida de verdade
    $duvidasPorLicao = $bdc->query("SELECT f.licao_id, COUNT(*) n, MAX(l.titulo) titulo
      FROM forum_posts f LEFT JOIN licoes l ON l.id = f.licao_id
      WHERE f.removido = 0 AND f.resposta_a IS NULL
      GROUP BY f.licao_id ORDER BY n DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    responder([
      'totalJogadores' => (int)$bdc->query('SELECT COUNT(*) FROM jogadores')->fetchColumn(),
      'ativos7d' => (int)$bdc->query('SELECT COUNT(*) FROM jogadores WHERE atualizado_em >= NOW() - INTERVAL 7 DAY')->fetchColumn(),
      'totalMundos' => (int)$bdc->query('SELECT COUNT(*) FROM mundos')->fetchColumn(),
      'totalLicoes' => $totalLicoes, 'licoesPublicadas' => $licoesPublicadas, 'licoesRascunho' => $totalLicoes - $licoesPublicadas,
      'totalConclusoes' => $totalConcluidas,
      'denunciasPendentes' => (int)$bdc->query("SELECT
          (SELECT COUNT(*) FROM mensagens WHERE denunciada = 1 AND removida = 0)
          + (SELECT COUNT(*) FROM forum_posts WHERE denunciado = 1 AND removido = 0)")->fetchColumn(),
      'mensagensRemovidas' => (int)$bdc->query('SELECT COUNT(*) FROM mensagens WHERE removida = 1')->fetchColumn(),
      'postsRemovidos' => (int)$bdc->query('SELECT COUNT(*) FROM forum_posts WHERE removido = 1')->fetchColumn(),
      'totalAmizades' => (int)$bdc->query("SELECT COUNT(*) FROM amizades WHERE status = 'aceita'")->fetchColumn(),
      'mensagens7d' => (int)$bdc->query('SELECT COUNT(*) FROM mensagens WHERE criado_em >= NOW() - INTERVAL 7 DAY')->fetchColumn(),
      'licoesComMaisDuvidas' => array_map(fn($l) => [
        'licaoId' => $l['licao_id'], 'titulo' => $l['titulo'] ?: $l['licao_id'], 'perguntas' => (int)$l['n'],
      ], $duvidasPorLicao),
    ]);
  }

  if ($acao === 'exportar') {
    $mundos = bd()->query('SELECT id, nome, emoji, cor, ordem, publicado FROM mundos ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC);
    $porMundo = [];
    foreach (bd()->query('SELECT id, mundo_id, titulo, emoji, serie, ordem, publicado, blocos FROM licoes ORDER BY ordem, titulo') as $l) {
      $porMundo[$l['mundo_id']][] = [
        'id' => $l['id'], 'titulo' => $l['titulo'], 'emoji' => $l['emoji'], 'serie' => $l['serie'],
        'ordem' => (int)$l['ordem'], 'publicado' => (bool)$l['publicado'], 'blocos' => json_decode($l['blocos'], true),
      ];
    }
    // cenas entram no backup junto: é o desenho dos mapas. O arquivo de imagem em si
    // não cabe aqui (é binário) — ele vive em assets/cenas/ no servidor.
    $porCena = [];
    foreach (bd()->query('SELECT cena_id, rotulo, x, y, largura, altura, tipo, destino, mostrar_selo, mostrar_dica, requisito_item, publicado FROM pontos ORDER BY id') as $p) {
      $porCena[$p['cena_id']][] = [
        'rotulo' => $p['rotulo'], 'x' => (float)$p['x'], 'y' => (float)$p['y'],
        'largura' => (float)$p['largura'], 'altura' => (float)$p['altura'],
        'tipo' => $p['tipo'], 'destino' => $p['destino'],
        'mostrarSelo' => (bool)$p['mostrar_selo'], 'mostrarDica' => (bool)$p['mostrar_dica'],
        'requisitoItem' => $p['requisito_item'], 'publicado' => (bool)$p['publicado'],
      ];
    }
    $cenas = bd()->query('SELECT id, nome, imagem, inicial, publicado, ordem FROM cenas ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC);
    responder([
      'mundos' => array_map(fn($m) => [
        'id' => $m['id'], 'nome' => $m['nome'], 'emoji' => $m['emoji'], 'cor' => $m['cor'],
        'ordem' => (int)$m['ordem'], 'publicado' => (bool)$m['publicado'], 'licoes' => $porMundo[$m['id']] ?? [],
      ], $mundos),
      'cenas' => array_map(fn($c) => [
        'id' => $c['id'], 'nome' => $c['nome'], 'imagem' => $c['imagem'],
        'inicial' => (bool)$c['inicial'], 'publicado' => (bool)$c['publicado'], 'ordem' => (int)$c['ordem'],
        'pontos' => $porCena[$c['id']] ?? [],
      ], $cenas),
    ]);
  }

  if ($acao === 'importar') {
    $d = corpo();
    $mundos = $d['mundos'] ?? null;
    if (!is_array($mundos)) responder(['erro' => 'Formato inválido: esperado {"mundos": [...]} — o mesmo do botão "Baixar backup".'], 422);
    $cenasImp = is_array($d['cenas'] ?? null) ? $d['cenas'] : []; // backup antigo não tem cenas

    // valida tudo antes de gravar qualquer coisa: ou importa inteiro, ou não muda nada
    foreach ($cenasImp as $c) {
      if (!validarId((string)($c['id'] ?? ''))) responder(['erro' => 'Mapa com "id" inválido: ' . json_encode($c['id'] ?? null)], 422);
      if (!validarNomeImagem((string)($c['imagem'] ?? ''))) responder(['erro' => 'Mapa "' . $c['id'] . '": nome de imagem inválido.'], 422);
      if (trim((string)($c['nome'] ?? '')) === '') responder(['erro' => 'Mapa "' . $c['id'] . '": falta o nome.'], 422);
    }
    foreach ($mundos as $m) {
      if (!validarId((string)($m['id'] ?? ''))) responder(['erro' => 'Mundo com "id" inválido: ' . json_encode($m['id'] ?? null)], 422);
      if (!in_array($m['cor'] ?? null, CORES_VALIDAS, true)) responder(['erro' => 'Mundo "' . $m['id'] . '": cor inválida.'], 422);
      foreach (($m['licoes'] ?? []) as $l) {
        $l['mundoId'] = $m['id'];
        $resultado = validarLicao($l);
        if (isset($resultado['erro'])) responder(['erro' => 'Lição "' . ($l['id'] ?? '?') . '": ' . $resultado['erro']], 422);
      }
    }

    $bdc = bd();
    $bdc->beginTransaction();
    try {
      $insMundo = $bdc->prepare('INSERT INTO mundos (id, nome, emoji, cor, ordem, publicado) VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE nome=VALUES(nome), emoji=VALUES(emoji), cor=VALUES(cor), ordem=VALUES(ordem), publicado=VALUES(publicado)');
      $insLicao = $bdc->prepare('INSERT INTO licoes (id, mundo_id, titulo, emoji, serie, ordem, publicado, blocos, gabarito) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE mundo_id=VALUES(mundo_id), titulo=VALUES(titulo), emoji=VALUES(emoji), serie=VALUES(serie),
          ordem=VALUES(ordem), publicado=VALUES(publicado), blocos=VALUES(blocos), gabarito=VALUES(gabarito)');
      foreach ($mundos as $m) {
        $insMundo->execute([$m['id'], $m['nome'], $m['emoji'], $m['cor'], (int)($m['ordem'] ?? 0), !empty($m['publicado']) ? 1 : 0]);
        foreach (($m['licoes'] ?? []) as $l) {
          $l['mundoId'] = $m['id'];
          $resultado = validarLicao($l);
          $insLicao->execute([
            $l['id'], $m['id'], trim((string)$l['titulo']), $l['emoji'], $l['serie'], (int)($l['ordem'] ?? 0), !empty($l['publicado']) ? 1 : 0,
            json_encode($l['blocos'], JSON_UNESCAPED_UNICODE), json_encode($resultado['gabarito'], JSON_UNESCAPED_UNICODE),
          ]);
        }
      }
      /* cenas e pontos entram depois dos mundos/lições de propósito: a validação de um
         ponto confere se o mundo/lição/cena de destino existe, e agora eles já existem
         (mesma transação). Se um ponto falhar, o rollback desfaz a importação inteira. */
      if ($cenasImp) {
        $insCena = $bdc->prepare('INSERT INTO cenas (id, nome, imagem, inicial, publicado, ordem) VALUES (?, ?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE nome=VALUES(nome), imagem=VALUES(imagem), inicial=VALUES(inicial),
            publicado=VALUES(publicado), ordem=VALUES(ordem)');
        foreach ($cenasImp as $c) {
          $insCena->execute([$c['id'], trim((string)$c['nome']), (string)$c['imagem'],
            !empty($c['inicial']) ? 1 : 0, !empty($c['publicado']) ? 1 : 0, (int)($c['ordem'] ?? 0)]);
        }
        $delPontos = $bdc->prepare('DELETE FROM pontos WHERE cena_id = ?');
        $insPonto = $bdc->prepare('INSERT INTO pontos (cena_id, rotulo, x, y, largura, altura, tipo, destino, mostrar_selo, mostrar_dica, requisito_item, publicado)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($cenasImp as $c) {
          $delPontos->execute([$c['id']]);
          foreach (($c['pontos'] ?? []) as $i => $p) {
            if (!is_array($p)) throw new RuntimeException('Mapa "' . $c['id'] . '": ponto ' . $i . ' inválido.');
            $erro = validarPonto($p, $i);
            if ($erro) throw new RuntimeException('Mapa "' . $c['id'] . '": ' . $erro);
            $insPonto->execute([
              $c['id'], trim((string)$p['rotulo']), (float)$p['x'], (float)$p['y'], (float)$p['largura'], (float)$p['altura'],
              $p['tipo'], trim((string)$p['destino']),
              !empty($p['mostrarSelo']) ? 1 : 0, !empty($p['mostrarDica']) ? 1 : 0,
              trim((string)($p['requisitoItem'] ?? '')),
              array_key_exists('publicado', $p) ? (!empty($p['publicado']) ? 1 : 0) : 1,
            ]);
          }
        }
      }
      $bdc->commit();
    } catch (RuntimeException $e) {
      $bdc->rollBack();
      responder(['erro' => $e->getMessage()], 422);
    } catch (Throwable $e) {
      $bdc->rollBack();
      throw $e;
    }
    responder(['ok' => true, 'mundos' => count($mundos), 'cenas' => count($cenasImp)]);
  }

  /* ---------- cenas (mapas) e seus pontos clicáveis ---------- */

  if ($acao === 'cenas_listar') {
    $linhas = bd()->query('SELECT c.id, c.nome, c.imagem, c.inicial, c.publicado, c.ordem, COUNT(p.id) AS pontos
      FROM cenas c LEFT JOIN pontos p ON p.cena_id = c.id
      GROUP BY c.id ORDER BY c.inicial DESC, c.ordem, c.nome')->fetchAll(PDO::FETCH_ASSOC);
    responder(['cenas' => array_map(fn($c) => [
      'id' => $c['id'], 'nome' => $c['nome'], 'imagem' => $c['imagem'],
      'imagemUrl' => caminhoPublicoImagem($c['imagem']),
      'inicial' => (bool)$c['inicial'], 'publicado' => (bool)$c['publicado'],
      'ordem' => (int)$c['ordem'], 'pontos' => (int)$c['pontos'],
    ], $linhas)]);
  }

  if ($acao === 'cena_obter') {
    $st = bd()->prepare('SELECT id, nome, imagem, inicial, publicado, ordem FROM cenas WHERE id = ?');
    $st->execute([(string)($_GET['id'] ?? '')]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c) responder(['erro' => 'Cena não encontrada.'], 404);
    $st = bd()->prepare('SELECT id, rotulo, x, y, largura, altura, tipo, destino, mostrar_selo, mostrar_dica, requisito_item, publicado
      FROM pontos WHERE cena_id = ? ORDER BY id');
    $st->execute([$c['id']]);
    responder([
      'id' => $c['id'], 'nome' => $c['nome'], 'imagem' => $c['imagem'],
      'imagemUrl' => caminhoPublicoImagem($c['imagem']),
      'inicial' => (bool)$c['inicial'], 'publicado' => (bool)$c['publicado'], 'ordem' => (int)$c['ordem'],
      'pontos' => array_map(fn($p) => [
        'rotulo' => $p['rotulo'], 'x' => (float)$p['x'], 'y' => (float)$p['y'],
        'largura' => (float)$p['largura'], 'altura' => (float)$p['altura'],
        'tipo' => $p['tipo'], 'destino' => $p['destino'],
        'mostrarSelo' => (bool)$p['mostrar_selo'], 'mostrarDica' => (bool)$p['mostrar_dica'],
        'requisitoItem' => $p['requisito_item'], 'publicado' => (bool)$p['publicado'],
      ], $st->fetchAll(PDO::FETCH_ASSOC)),
    ]);
  }

  if ($acao === 'cena_salvar') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    if (!validarId($id)) responder(['erro' => '"id" inválido: use de 2 a 24 letras minúsculas ou números.'], 422);
    $nome = trim((string)($d['nome'] ?? ''));
    if ($nome === '' || mb_strlen($nome) > 60) responder(['erro' => 'O nome precisa ter de 1 a 60 caracteres.'], 422);
    $imagem = trim((string)($d['imagem'] ?? ''));
    if (!validarNomeImagem($imagem)) responder(['erro' => 'Escolha ou envie a imagem de fundo da cena (webp, png ou jpg).'], 422);
    $inicial = !empty($d['inicial']) ? 1 : 0;
    $bdc = bd();
    $bdc->beginTransaction();
    try {
      $bdc->prepare('INSERT INTO cenas (id, nome, imagem, inicial, publicado, ordem) VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE nome=VALUES(nome), imagem=VALUES(imagem), inicial=VALUES(inicial),
          publicado=VALUES(publicado), ordem=VALUES(ordem)')
        ->execute([$id, $nome, $imagem, $inicial, !empty($d['publicado']) ? 1 : 0, (int)($d['ordem'] ?? 0)]);
      // só uma cena pode ser a inicial (a que abre na aba Trilhas)
      if ($inicial) $bdc->prepare('UPDATE cenas SET inicial = 0 WHERE id <> ?')->execute([$id]);
      $bdc->commit();
    } catch (Throwable $e) { $bdc->rollBack(); throw $e; }
    responder(['ok' => true]);
  }

  if ($acao === 'cena_excluir') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    $st = bd()->prepare('SELECT inicial FROM cenas WHERE id = ?');
    $st->execute([$id]);
    $inicial = $st->fetchColumn();
    if ($inicial === false) responder(['erro' => 'Cena não encontrada.'], 404);
    $total = (int)bd()->query('SELECT COUNT(*) FROM cenas')->fetchColumn();
    if ($inicial && $total > 1) {
      responder(['erro' => 'Essa é a cena inicial. Marque outra como inicial antes de excluir esta.'], 422);
    }
    // pontos de OUTRAS cenas que apontavam pra cá ficariam quebrados — avisa em vez de silenciar
    $st = bd()->prepare('SELECT COUNT(*) FROM pontos WHERE tipo = "cena" AND destino = ? AND cena_id <> ?');
    $st->execute([$id, $id]);
    $apontam = (int)$st->fetchColumn();
    bd()->prepare('DELETE FROM cenas WHERE id = ?')->execute([$id]);
    responder(['ok' => true, 'pontosOrfaos' => $apontam]);
  }

  if ($acao === 'pontos_salvar') {
    $d = corpo();
    $cenaId = (string)($d['cenaId'] ?? '');
    $st = bd()->prepare('SELECT COUNT(*) FROM cenas WHERE id = ?');
    $st->execute([$cenaId]);
    if (!$st->fetchColumn()) responder(['erro' => 'Cena não encontrada.'], 422);
    $pontos = is_array($d['pontos'] ?? null) ? $d['pontos'] : null;
    if ($pontos === null) responder(['erro' => 'Formato inválido: esperado {"cenaId":..., "pontos":[...]}.'], 422);

    // valida tudo antes de gravar: ou salva o mapa inteiro, ou não muda nada
    foreach ($pontos as $i => $p) {
      if (!is_array($p)) responder(['erro' => "Ponto $i inválido."], 422);
      $erro = validarPonto($p, $i);
      if ($erro) responder(['erro' => $erro], 422);
    }

    $bdc = bd();
    $bdc->beginTransaction();
    try {
      $bdc->prepare('DELETE FROM pontos WHERE cena_id = ?')->execute([$cenaId]);
      $ins = $bdc->prepare('INSERT INTO pontos (cena_id, rotulo, x, y, largura, altura, tipo, destino, mostrar_selo, mostrar_dica, requisito_item, publicado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      foreach ($pontos as $p) {
        $ins->execute([
          $cenaId, trim((string)$p['rotulo']), (float)$p['x'], (float)$p['y'], (float)$p['largura'], (float)$p['altura'],
          $p['tipo'], trim((string)$p['destino']),
          !empty($p['mostrarSelo']) ? 1 : 0, !empty($p['mostrarDica']) ? 1 : 0,
          trim((string)($p['requisitoItem'] ?? '')),
          array_key_exists('publicado', $p) ? (!empty($p['publicado']) ? 1 : 0) : 1,
        ]);
      }
      $bdc->commit();
    } catch (Throwable $e) { $bdc->rollBack(); throw $e; }
    responder(['ok' => true, 'pontos' => count($pontos)]);
  }

  if ($acao === 'cena_imagem') {
    $arq = $_FILES['arquivo'] ?? null;
    if (!$arq || ($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $limite = ini_get('upload_max_filesize');
      $motivo = ($arq['error'] ?? null) === UPLOAD_ERR_INI_SIZE
        ? "A imagem passou do limite do servidor ($limite)."
        : 'Nenhum arquivo recebido.';
      responder(['erro' => $motivo], 422);
    }
    $ext = strtolower(pathinfo((string)$arq['name'], PATHINFO_EXTENSION));
    if (!isset(EXTENSOES_IMAGEM[$ext])) {
      responder(['erro' => 'Formato não aceito. Use webp, png ou jpg (webp é o mais leve).'], 422);
    }
    // não confia na extensão nem no content-type do navegador: confirma que é imagem de verdade
    $info = @getimagesize($arq['tmp_name']);
    if (!$info || !in_array($info['mime'], EXTENSOES_IMAGEM, true)) {
      responder(['erro' => 'O arquivo não é uma imagem válida.'], 422);
    }
    $base = strtolower(pathinfo((string)$arq['name'], PATHINFO_FILENAME));
    $base = preg_replace('/[^a-z0-9_-]+/', '-', $base) ?: 'cena';
    $base = trim($base, '-') ?: 'cena';
    $nome = substr($base, 0, 60) . '.' . $ext;
    if (!is_dir(pastaCenas()) && !@mkdir(pastaCenas(), 0755, true)) {
      responder(['erro' => 'Não consegui criar a pasta assets/cenas no servidor.'], 500);
    }
    if (!@move_uploaded_file($arq['tmp_name'], pastaCenas() . '/' . $nome)) {
      responder(['erro' => 'Não consegui gravar o arquivo em assets/cenas (confira a permissão da pasta).'], 500);
    }
    responder(['ok' => true, 'imagem' => $nome, 'imagemUrl' => 'assets/cenas/' . $nome,
      'largura' => $info[0], 'altura' => $info[1]]);
  }

  /* ---------- NPCs e seus diálogos ---------- */

  if ($acao === 'npcs_listar') {
    $linhas = bd()->query('SELECT id, nome, emoji, imagem, imagem_tipo, tela, dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, publicado, ordem FROM npcs ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC);
    responder(['npcs' => array_map(fn($n) => [
      'id' => $n['id'], 'nome' => $n['nome'], 'emoji' => $n['emoji'],
      'imagem' => $n['imagem'], 'imagemUrl' => caminhoPublicoNpc($n['imagem']), 'imagemTipo' => $n['imagem_tipo'],
      'tela' => $n['tela'], 'diasSemana' => $n['dias_semana'], 'horaInicio' => $n['hora_inicio'], 'horaFim' => $n['hora_fim'],
      'dataInicio' => $n['data_inicio'], 'dataFim' => $n['data_fim'],
      'publicado' => (bool)$n['publicado'], 'ordem' => (int)$n['ordem'],
    ], $linhas)]);
  }

  if ($acao === 'npc_obter') {
    $st = bd()->prepare('SELECT id, nome, emoji, imagem, imagem_tipo, tela, dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, dialogo, expressoes, publicado, ordem FROM npcs WHERE id = ?');
    $st->execute([(string)($_GET['id'] ?? '')]);
    $n = $st->fetch(PDO::FETCH_ASSOC);
    if (!$n) responder(['erro' => 'NPC não encontrado.'], 404);
    $expressoes = json_decode($n['expressoes'] ?: '{}', true) ?: [];
    responder([
      'id' => $n['id'], 'nome' => $n['nome'], 'emoji' => $n['emoji'],
      'imagem' => $n['imagem'], 'imagemUrl' => caminhoPublicoNpc($n['imagem']), 'imagemTipo' => $n['imagem_tipo'],
      'tela' => $n['tela'], 'diasSemana' => $n['dias_semana'], 'horaInicio' => $n['hora_inicio'], 'horaFim' => $n['hora_fim'],
      'dataInicio' => $n['data_inicio'], 'dataFim' => $n['data_fim'],
      'publicado' => (bool)$n['publicado'], 'ordem' => (int)$n['ordem'],
      'dialogo' => json_decode($n['dialogo'], true),
      'expressoes' => $expressoes,
      'expressoesUrl' => array_map(fn($nomeArquivo) => caminhoPublicoNpc((string)$nomeArquivo), $expressoes),
    ]);
  }

  if ($acao === 'npc_salvar') {
    $d = corpo();
    $erro = validarNpc($d);
    if ($erro) responder(['erro' => $erro], 422);
    bd()->prepare('INSERT INTO npcs (id, nome, emoji, imagem, imagem_tipo, tela, dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, dialogo, expressoes, publicado, ordem)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE nome=VALUES(nome), emoji=VALUES(emoji), imagem=VALUES(imagem), imagem_tipo=VALUES(imagem_tipo),
        tela=VALUES(tela), dias_semana=VALUES(dias_semana), hora_inicio=VALUES(hora_inicio), hora_fim=VALUES(hora_fim),
        data_inicio=VALUES(data_inicio), data_fim=VALUES(data_fim),
        dialogo=VALUES(dialogo), expressoes=VALUES(expressoes), publicado=VALUES(publicado), ordem=VALUES(ordem)')
      ->execute([
        (string)$d['id'], trim((string)$d['nome']), (string)($d['emoji'] ?? '🧑'), trim((string)($d['imagem'] ?? '')),
        (string)($d['imagemTipo'] ?? 'png'), trim((string)($d['tela'] ?? '')), normalizarDiasSemana(trim((string)($d['diasSemana'] ?? ''))),
        trim((string)($d['horaInicio'] ?? '')), trim((string)($d['horaFim'] ?? '')),
        trim((string)($d['dataInicio'] ?? '')), trim((string)($d['dataFim'] ?? '')),
        json_encode($d['dialogo'], JSON_UNESCAPED_UNICODE), json_encode(expressoesLimpas($d['expressoes'] ?? []), JSON_UNESCAPED_UNICODE),
        !empty($d['publicado']) ? 1 : 0, (int)($d['ordem'] ?? 0),
      ]);
    responder(['ok' => true]);
  }

  if ($acao === 'npcs_importar') {
    // pensado pra criar em massa: cola/sobe um array de NPCs completos (mesmo formato do
    // npc_salvar, diálogo inteiro incluso) e grava tudo de uma vez — ou tudo entra, ou nada
    // muda. Imagens (imagem/expressoes) não viajam no JSON — se referenciar um arquivo, ele
    // precisa já ter sido enviado antes (ou é enviado depois, editando o NPC na tela)
    $d = corpo();
    $npcs = $d['npcs'] ?? null;
    if (!is_array($npcs) || !$npcs) responder(['erro' => 'Formato inválido: esperado {"npcs": [...]} com ao menos 1 item.'], 422);
    if (count($npcs) > 100) responder(['erro' => 'No máximo 100 NPCs por importação.'], 422);
    foreach ($npcs as $i => $n) {
      if (!is_array($n)) responder(['erro' => "NPC $i: precisa ser um objeto."], 422);
      $erro = validarNpc($n);
      if ($erro) responder(['erro' => "NPC \"" . ($n['id'] ?? '?') . "\": " . $erro], 422);
    }
    $bdc = bd();
    $bdc->beginTransaction();
    try {
      $ins = $bdc->prepare('INSERT INTO npcs (id, nome, emoji, imagem, imagem_tipo, tela, dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, dialogo, expressoes, publicado, ordem)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE nome=VALUES(nome), emoji=VALUES(emoji), imagem=VALUES(imagem), imagem_tipo=VALUES(imagem_tipo),
          tela=VALUES(tela), dias_semana=VALUES(dias_semana), hora_inicio=VALUES(hora_inicio), hora_fim=VALUES(hora_fim),
          data_inicio=VALUES(data_inicio), data_fim=VALUES(data_fim),
          dialogo=VALUES(dialogo), expressoes=VALUES(expressoes), publicado=VALUES(publicado), ordem=VALUES(ordem)');
      foreach ($npcs as $n) {
        $ins->execute([
          (string)$n['id'], trim((string)$n['nome']), (string)($n['emoji'] ?? '🧑'), trim((string)($n['imagem'] ?? '')),
          (string)($n['imagemTipo'] ?? 'png'), trim((string)($n['tela'] ?? '')), normalizarDiasSemana(trim((string)($n['diasSemana'] ?? ''))),
          trim((string)($n['horaInicio'] ?? '')), trim((string)($n['horaFim'] ?? '')),
          trim((string)($n['dataInicio'] ?? '')), trim((string)($n['dataFim'] ?? '')),
          json_encode($n['dialogo'], JSON_UNESCAPED_UNICODE), json_encode(expressoesLimpas($n['expressoes'] ?? []), JSON_UNESCAPED_UNICODE),
          !empty($n['publicado']) ? 1 : 0, (int)($n['ordem'] ?? 0),
        ]);
      }
      $bdc->commit();
    } catch (Throwable $e) { $bdc->rollBack(); throw $e; }
    responder(['ok' => true, 'npcs' => count($npcs)]);
  }

  if ($acao === 'npc_excluir') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    // pontos que apontavam pra este NPC ficariam quebrados — avisa em vez de silenciar
    $st = bd()->prepare('SELECT COUNT(*) FROM pontos WHERE tipo = "npc" AND destino = ?');
    $st->execute([$id]);
    $apontam = (int)$st->fetchColumn();
    bd()->prepare('DELETE FROM npcs WHERE id = ?')->execute([$id]);
    responder(['ok' => true, 'pontosOrfaos' => $apontam]);
  }

  if ($acao === 'npc_duplicar') {
    // clona um NPC pra um id novo: nome, imagem, agenda e diálogo inteiro vêm junto —
    // é o jeito rápido de criar vários parecidos sem montar o diálogo do zero toda vez
    $d = corpo();
    $novoId = (string)($d['novoId'] ?? '');
    if (!validarId($novoId)) responder(['erro' => '"novoId" inválido: use de 2 a 24 letras minúsculas ou números.'], 422);
    $st = bd()->prepare('SELECT COUNT(*) FROM npcs WHERE id = ?');
    $st->execute([$novoId]);
    if ($st->fetchColumn()) responder(['erro' => "Já existe um NPC com id \"$novoId\"."], 422);
    $st = bd()->prepare('SELECT nome, emoji, imagem, imagem_tipo, tela, dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, dialogo, expressoes, ordem FROM npcs WHERE id = ?');
    $st->execute([(string)($d['id'] ?? '')]);
    $n = $st->fetch(PDO::FETCH_ASSOC);
    if (!$n) responder(['erro' => 'NPC original não encontrado.'], 404);
    bd()->prepare('INSERT INTO npcs (id, nome, emoji, imagem, imagem_tipo, tela, dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, dialogo, expressoes, publicado, ordem)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)')
      ->execute([
        $novoId, $n['nome'] . ' (cópia)', $n['emoji'], $n['imagem'], $n['imagem_tipo'], $n['tela'],
        $n['dias_semana'], $n['hora_inicio'], $n['hora_fim'], $n['data_inicio'], $n['data_fim'],
        $n['dialogo'], $n['expressoes'], (int)$n['ordem'],
      ]);
    responder(['ok' => true, 'id' => $novoId]);
  }

  if ($acao === 'npc_imagem') {
    $arq = $_FILES['arquivo'] ?? null;
    if (!$arq || ($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $limite = ini_get('upload_max_filesize');
      $motivo = ($arq['error'] ?? null) === UPLOAD_ERR_INI_SIZE
        ? "A imagem passou do limite do servidor ($limite)."
        : 'Nenhum arquivo recebido.';
      responder(['erro' => $motivo], 422);
    }
    $ext = strtolower(pathinfo((string)$arq['name'], PATHINFO_EXTENSION));
    if (!isset(EXTENSOES_IMAGEM[$ext])) {
      responder(['erro' => 'Formato não aceito. Use webp, png ou jpg (webp é o mais leve).'], 422);
    }
    // não confia na extensão nem no content-type do navegador: confirma que é imagem de verdade
    $info = @getimagesize($arq['tmp_name']);
    if (!$info || !in_array($info['mime'], EXTENSOES_IMAGEM, true)) {
      responder(['erro' => 'O arquivo não é uma imagem válida.'], 422);
    }
    $base = strtolower(pathinfo((string)$arq['name'], PATHINFO_FILENAME));
    $base = preg_replace('/[^a-z0-9_-]+/', '-', $base) ?: 'npc';
    $base = trim($base, '-') ?: 'npc';
    $nome = substr($base, 0, 60) . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!is_dir(pastaNpcs()) && !@mkdir(pastaNpcs(), 0755, true)) {
      responder(['erro' => 'Não consegui criar a pasta assets/npcs no servidor.'], 500);
    }
    if (!@move_uploaded_file($arq['tmp_name'], pastaNpcs() . '/' . $nome)) {
      responder(['erro' => 'Não consegui gravar o arquivo em assets/npcs (confira a permissão da pasta).'], 500);
    }
    responder(['ok' => true, 'imagem' => $nome, 'imagemUrl' => 'assets/npcs/' . $nome]);
  }

  /* ---------- Missões (catálogo central, fora da página do NPC) ---------- */

  if ($acao === 'missoes_listar') {
    $linhas = bd()->query('SELECT id, titulo, descricao, tipo, objetivo, premio, publicado, ordem FROM missoes ORDER BY ordem, titulo')->fetchAll(PDO::FETCH_ASSOC);
    responder(['missoes' => array_map(fn($m) => [
      'id' => $m['id'], 'titulo' => $m['titulo'], 'descricao' => $m['descricao'], 'tipo' => $m['tipo'],
      'objetivo' => json_decode($m['objetivo'], true), 'premio' => json_decode($m['premio'], true),
      'publicado' => (bool)$m['publicado'], 'ordem' => (int)$m['ordem'],
    ], $linhas)]);
  }

  if ($acao === 'missao_obter') {
    $st = bd()->prepare('SELECT id, titulo, descricao, tipo, objetivo, premio, publicado, ordem FROM missoes WHERE id = ?');
    $st->execute([(string)($_GET['id'] ?? '')]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m) responder(['erro' => 'Missão não encontrada.'], 404);
    responder([
      'id' => $m['id'], 'titulo' => $m['titulo'], 'descricao' => $m['descricao'], 'tipo' => $m['tipo'],
      'objetivo' => json_decode($m['objetivo'], true), 'premio' => json_decode($m['premio'], true),
      'publicado' => (bool)$m['publicado'], 'ordem' => (int)$m['ordem'],
    ]);
  }

  if ($acao === 'missao_salvar') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    if (!validarId($id)) responder(['erro' => '"id" inválido: use de 2 a 24 letras minúsculas ou números.'], 422);
    $erro = validarMissao($d);
    if ($erro) responder(['erro' => $erro], 422);
    bd()->prepare('INSERT INTO missoes (id, titulo, descricao, tipo, objetivo, premio, publicado, ordem) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE titulo=VALUES(titulo), descricao=VALUES(descricao), tipo=VALUES(tipo),
        objetivo=VALUES(objetivo), premio=VALUES(premio), publicado=VALUES(publicado), ordem=VALUES(ordem)')
      ->execute([
        $id, trim((string)$d['titulo']), trim((string)$d['descricao']), (string)$d['tipo'],
        json_encode($d['objetivo'], JSON_UNESCAPED_UNICODE), json_encode($d['premio'], JSON_UNESCAPED_UNICODE),
        !empty($d['publicado']) ? 1 : 0, (int)($d['ordem'] ?? 0),
      ]);
    responder(['ok' => true]);
  }

  if ($acao === 'missao_excluir') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    // botões de diálogo que referenciam essa missão ficariam quebrados — avisa em vez de silenciar
    $refs = 0;
    foreach (bd()->query('SELECT dialogo FROM npcs') as $linha) {
      if (str_contains((string)$linha['dialogo'], '"' . $id . '"')) $refs++;
    }
    bd()->prepare('DELETE FROM missoes WHERE id = ?')->execute([$id]);
    responder(['ok' => true, 'npcsAfetados' => $refs]);
  }

  if ($acao === 'missao_duplicar') {
    $d = corpo();
    $novoId = (string)($d['novoId'] ?? '');
    if (!validarId($novoId)) responder(['erro' => '"novoId" inválido: use de 2 a 24 letras minúsculas ou números.'], 422);
    $st = bd()->prepare('SELECT COUNT(*) FROM missoes WHERE id = ?');
    $st->execute([$novoId]);
    if ($st->fetchColumn()) responder(['erro' => "Já existe uma missão com id \"$novoId\"."], 422);
    $st = bd()->prepare('SELECT titulo, descricao, tipo, objetivo, premio, ordem FROM missoes WHERE id = ?');
    $st->execute([(string)($d['id'] ?? '')]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m) responder(['erro' => 'Missão original não encontrada.'], 404);
    bd()->prepare('INSERT INTO missoes (id, titulo, descricao, tipo, objetivo, premio, publicado, ordem) VALUES (?, ?, ?, ?, ?, ?, 0, ?)')
      ->execute([$novoId, $m['titulo'] . ' (cópia)', $m['descricao'], $m['tipo'], $m['objetivo'], $m['premio'], (int)$m['ordem']]);
    responder(['ok' => true, 'id' => $novoId]);
  }

  if ($acao === 'missoes_importar') {
    // pensado pra criar em massa: cola/sobe um array de missões (mesmo formato do
    // missao_salvar) e grava tudo de uma vez — ou tudo entra, ou nada muda
    $d = corpo();
    $missoes = $d['missoes'] ?? null;
    if (!is_array($missoes) || !$missoes) responder(['erro' => 'Formato inválido: esperado {"missoes": [...]} com ao menos 1 item.'], 422);
    if (count($missoes) > 200) responder(['erro' => 'No máximo 200 missões por importação.'], 422);
    foreach ($missoes as $i => $m) {
      if (!is_array($m)) responder(['erro' => "Missão $i: precisa ser um objeto."], 422);
      if (!validarId((string)($m['id'] ?? ''))) responder(['erro' => "Missão $i: \"id\" inválido."], 422);
      $erro = validarMissao($m);
      if ($erro) responder(['erro' => "Missão \"" . ($m['id'] ?? '?') . "\": " . $erro], 422);
    }
    $bdc = bd();
    $bdc->beginTransaction();
    try {
      $ins = $bdc->prepare('INSERT INTO missoes (id, titulo, descricao, tipo, objetivo, premio, publicado, ordem) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE titulo=VALUES(titulo), descricao=VALUES(descricao), tipo=VALUES(tipo),
          objetivo=VALUES(objetivo), premio=VALUES(premio), publicado=VALUES(publicado), ordem=VALUES(ordem)');
      foreach ($missoes as $m) {
        $ins->execute([
          (string)$m['id'], trim((string)$m['titulo']), trim((string)$m['descricao']), (string)$m['tipo'],
          json_encode($m['objetivo'], JSON_UNESCAPED_UNICODE), json_encode($m['premio'], JSON_UNESCAPED_UNICODE),
          !empty($m['publicado']) ? 1 : 0, (int)($m['ordem'] ?? 0),
        ]);
      }
      $bdc->commit();
    } catch (Throwable $e) { $bdc->rollBack(); throw $e; }
    responder(['ok' => true, 'missoes' => count($missoes)]);
  }

  /* ---------- Jornal (Neoiatimes): artigos ---------- */

  function linhaJornal(array $a): array {
    return [
      'id' => (int)$a['id'], 'titulo' => $a['titulo'], 'subtitulo' => $a['subtitulo'], 'corpo' => $a['corpo'],
      'mundoId' => $a['mundo_id'], 'colunistaNpcId' => $a['colunista_npc_id'], 'autorNome' => $a['autor_nome'],
      'imagem' => $a['imagem'], 'imagemUrl' => caminhoPublicoJornal($a['imagem']),
      'destaque' => (bool)$a['destaque'], 'publicado' => (bool)$a['publicado'], 'ordem' => (int)$a['ordem'],
    ];
  }

  if ($acao === 'jornal_listar') {
    $linhas = bd()->query('SELECT * FROM jornal_artigos ORDER BY ordem, id DESC')->fetchAll(PDO::FETCH_ASSOC);
    responder(['artigos' => array_map('linhaJornal', $linhas)]);
  }

  if ($acao === 'jornal_obter') {
    $st = bd()->prepare('SELECT * FROM jornal_artigos WHERE id = ?');
    $st->execute([(int)($_GET['id'] ?? 0)]);
    $a = $st->fetch(PDO::FETCH_ASSOC);
    if (!$a) responder(['erro' => 'Artigo não encontrado.'], 404);
    responder(linhaJornal($a));
  }

  if ($acao === 'jornal_salvar') {
    $d = corpo();
    $titulo = trim((string)($d['titulo'] ?? ''));
    if ($titulo === '' || mb_strlen($titulo) > 160) responder(['erro' => '"titulo" precisa ter de 1 a 160 caracteres.'], 422);
    $subtitulo = trim((string)($d['subtitulo'] ?? ''));
    if (mb_strlen($subtitulo) > 240) responder(['erro' => '"subtitulo" pode ter no máximo 240 caracteres.'], 422);
    $corpoArtigo = trim((string)($d['corpo'] ?? ''));
    if ($corpoArtigo === '') responder(['erro' => '"corpo" não pode ficar vazio.'], 422);
    $mundoId = trim((string)($d['mundoId'] ?? ''));
    if ($mundoId !== '') {
      $st = bd()->prepare('SELECT 1 FROM mundos WHERE id = ?');
      $st->execute([$mundoId]);
      if (!$st->fetchColumn()) responder(['erro' => 'Esse mundo não existe.'], 422);
    }
    $colunistaId = trim((string)($d['colunistaNpcId'] ?? ''));
    if ($colunistaId !== '') {
      $st = bd()->prepare('SELECT 1 FROM npcs WHERE id = ?');
      $st->execute([$colunistaId]);
      if (!$st->fetchColumn()) responder(['erro' => 'Esse NPC (colunista) não existe.'], 422);
    }
    $autorNome = trim((string)($d['autorNome'] ?? ''));
    if ($autorNome === '' && $colunistaId === '') responder(['erro' => 'Defina um "autorNome" ou escolha um colunista (NPC).'], 422);
    if (mb_strlen($autorNome) > 60) responder(['erro' => '"autorNome" pode ter no máximo 60 caracteres.'], 422);
    $imagem = trim((string)($d['imagem'] ?? ''));
    if ($imagem !== '' && !validarNomeImagem($imagem)) responder(['erro' => '"imagem" tem um nome de arquivo inválido.'], 422);
    $destaque = !empty($d['destaque']) ? 1 : 0;
    $publicado = !empty($d['publicado']) ? 1 : 0;
    $ordem = (int)($d['ordem'] ?? 0);
    $id = (int)($d['id'] ?? 0);
    if ($id > 0) {
      bd()->prepare('UPDATE jornal_artigos SET titulo=?, subtitulo=?, corpo=?, mundo_id=?, colunista_npc_id=?, autor_nome=?, imagem=?, destaque=?, publicado=?, ordem=? WHERE id=?')
        ->execute([$titulo, $subtitulo, $corpoArtigo, $mundoId ?: null, $colunistaId ?: null, $autorNome, $imagem, $destaque, $publicado, $ordem, $id]);
    } else {
      bd()->prepare('INSERT INTO jornal_artigos (titulo, subtitulo, corpo, mundo_id, colunista_npc_id, autor_nome, imagem, destaque, publicado, ordem) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([$titulo, $subtitulo, $corpoArtigo, $mundoId ?: null, $colunistaId ?: null, $autorNome, $imagem, $destaque, $publicado, $ordem]);
      $id = (int)bd()->lastInsertId();
    }
    responder(['ok' => true, 'id' => $id]);
  }

  if ($acao === 'jornal_excluir') {
    $d = corpo();
    bd()->prepare('DELETE FROM jornal_artigos WHERE id = ?')->execute([(int)($d['id'] ?? 0)]);
    responder(['ok' => true]);
  }

  if ($acao === 'jornal_imagem') {
    $arq = $_FILES['arquivo'] ?? null;
    if (!$arq || ($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $limite = ini_get('upload_max_filesize');
      $motivo = ($arq['error'] ?? null) === UPLOAD_ERR_INI_SIZE
        ? "A imagem passou do limite do servidor ($limite)."
        : 'Nenhum arquivo recebido.';
      responder(['erro' => $motivo], 422);
    }
    $ext = strtolower(pathinfo((string)$arq['name'], PATHINFO_EXTENSION));
    if (!isset(EXTENSOES_IMAGEM[$ext])) {
      responder(['erro' => 'Formato não aceito. Use webp, png ou jpg (webp é o mais leve).'], 422);
    }
    $info = @getimagesize($arq['tmp_name']);
    if (!$info || !in_array($info['mime'], EXTENSOES_IMAGEM, true)) {
      responder(['erro' => 'O arquivo não é uma imagem válida.'], 422);
    }
    $nome = 'artigo-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!is_dir(pastaJornal()) && !@mkdir(pastaJornal(), 0755, true)) {
      responder(['erro' => 'Não consegui criar a pasta assets/jornal no servidor.'], 500);
    }
    if (!@move_uploaded_file($arq['tmp_name'], pastaJornal() . '/' . $nome)) {
      responder(['erro' => 'Não consegui gravar o arquivo em assets/jornal (confira a permissão da pasta).'], 500);
    }
    responder(['ok' => true, 'imagem' => $nome, 'imagemUrl' => caminhoPublicoJornal($nome)]);
  }

  /* ---------- Itens colecionáveis (objetos soltos no mapa) ---------- */

  if ($acao === 'itens_listar') {
    $linhas = bd()->query('SELECT id, nome, emoji, imagem, descricao, publicado, ordem FROM itens ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC);
    responder(['itens' => array_map(fn($it) => [
      'id' => $it['id'], 'nome' => $it['nome'], 'emoji' => $it['emoji'],
      'imagem' => $it['imagem'], 'imagemUrl' => caminhoPublicoItem($it['imagem']), 'descricao' => $it['descricao'],
      'publicado' => (bool)$it['publicado'], 'ordem' => (int)$it['ordem'],
    ], $linhas)]);
  }

  if ($acao === 'item_obter') {
    $st = bd()->prepare('SELECT id, nome, emoji, imagem, descricao, publicado, ordem FROM itens WHERE id = ?');
    $st->execute([(string)($_GET['id'] ?? '')]);
    $it = $st->fetch(PDO::FETCH_ASSOC);
    if (!$it) responder(['erro' => 'Item não encontrado.'], 404);
    responder([
      'id' => $it['id'], 'nome' => $it['nome'], 'emoji' => $it['emoji'],
      'imagem' => $it['imagem'], 'imagemUrl' => caminhoPublicoItem($it['imagem']), 'descricao' => $it['descricao'],
      'publicado' => (bool)$it['publicado'], 'ordem' => (int)$it['ordem'],
    ]);
  }

  if ($acao === 'item_salvar') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    if (!validarId($id)) responder(['erro' => '"id" inválido: use de 2 a 24 letras minúsculas ou números.'], 422);
    $erro = validarItem($d);
    if ($erro) responder(['erro' => $erro], 422);
    bd()->prepare('INSERT INTO itens (id, nome, emoji, imagem, descricao, publicado, ordem) VALUES (?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE nome=VALUES(nome), emoji=VALUES(emoji), imagem=VALUES(imagem),
        descricao=VALUES(descricao), publicado=VALUES(publicado), ordem=VALUES(ordem)')
      ->execute([
        $id, trim((string)$d['nome']), (string)($d['emoji'] ?? '🔹'), trim((string)($d['imagem'] ?? '')),
        trim((string)($d['descricao'] ?? '')), !empty($d['publicado']) ? 1 : 0, (int)($d['ordem'] ?? 0),
      ]);
    responder(['ok' => true]);
  }

  if ($acao === 'item_excluir') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    // pontos (tipo item OU com requisito nesse item) que dependiam dele ficariam quebrados
    $st = bd()->prepare('SELECT COUNT(*) FROM pontos WHERE (tipo = "item" AND destino = ?) OR requisito_item = ?');
    $st->execute([$id, $id]);
    $afetados = (int)$st->fetchColumn();
    bd()->prepare('DELETE FROM itens WHERE id = ?')->execute([$id]);
    responder(['ok' => true, 'pontosAfetados' => $afetados]);
  }

  if ($acao === 'item_imagem') {
    $arq = $_FILES['arquivo'] ?? null;
    if (!$arq || ($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $limite = ini_get('upload_max_filesize');
      $motivo = ($arq['error'] ?? null) === UPLOAD_ERR_INI_SIZE
        ? "A imagem passou do limite do servidor ($limite)."
        : 'Nenhum arquivo recebido.';
      responder(['erro' => $motivo], 422);
    }
    $ext = strtolower(pathinfo((string)$arq['name'], PATHINFO_EXTENSION));
    if (!isset(EXTENSOES_IMAGEM[$ext])) {
      responder(['erro' => 'Formato não aceito. Use webp, png ou jpg (webp é o mais leve).'], 422);
    }
    $info = @getimagesize($arq['tmp_name']);
    if (!$info || !in_array($info['mime'], EXTENSOES_IMAGEM, true)) {
      responder(['erro' => 'O arquivo não é uma imagem válida.'], 422);
    }
    $base = strtolower(pathinfo((string)$arq['name'], PATHINFO_FILENAME));
    $base = preg_replace('/[^a-z0-9_-]+/', '-', $base) ?: 'item';
    $base = trim($base, '-') ?: 'item';
    $nome = substr($base, 0, 60) . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!is_dir(pastaItens()) && !@mkdir(pastaItens(), 0755, true)) {
      responder(['erro' => 'Não consegui criar a pasta assets/itens no servidor.'], 500);
    }
    if (!@move_uploaded_file($arq['tmp_name'], pastaItens() . '/' . $nome)) {
      responder(['erro' => 'Não consegui gravar o arquivo em assets/itens (confira a permissão da pasta).'], 500);
    }
    responder(['ok' => true, 'imagem' => $nome, 'imagemUrl' => 'assets/itens/' . $nome]);
  }

  /* ---------- Móveis (Loja de Móveis + decoração da Casa) ---------- */

  function linhaMovel(array $m): array {
    return [
      'id' => $m['id'], 'nome' => $m['nome'], 'preco' => (int)$m['preco'], 'rotativel' => (bool)$m['rotativel'],
      'imagemFrente' => $m['imagem_frente'], 'imagemFrenteUrl' => caminhoPublicoMovel($m['imagem_frente']),
      'imagemDireita' => $m['imagem_direita'], 'imagemDireitaUrl' => caminhoPublicoMovel($m['imagem_direita']),
      'imagemVerso' => $m['imagem_verso'], 'imagemVersoUrl' => caminhoPublicoMovel($m['imagem_verso']),
      'imagemEsquerda' => $m['imagem_esquerda'], 'imagemEsquerdaUrl' => caminhoPublicoMovel($m['imagem_esquerda']),
      'diasSemana' => $m['dias_semana'], 'horaInicio' => $m['hora_inicio'], 'horaFim' => $m['hora_fim'],
      'dataInicio' => $m['data_inicio'], 'dataFim' => $m['data_fim'],
      'publicado' => (bool)$m['publicado'], 'ordem' => (int)$m['ordem'],
    ];
  }

  if ($acao === 'moveis_listar') {
    $linhas = bd()->query('SELECT id, nome, preco, rotativel, imagem_frente, imagem_direita, imagem_verso, imagem_esquerda,
      dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, publicado, ordem
      FROM moveis ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC);
    responder(['moveis' => array_map('linhaMovel', $linhas)]);
  }

  if ($acao === 'movel_obter') {
    $st = bd()->prepare('SELECT id, nome, preco, rotativel, imagem_frente, imagem_direita, imagem_verso, imagem_esquerda,
      dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, publicado, ordem
      FROM moveis WHERE id = ?');
    $st->execute([(string)($_GET['id'] ?? '')]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m) responder(['erro' => 'Móvel não encontrado.'], 404);
    responder(linhaMovel($m));
  }

  if ($acao === 'movel_salvar') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    if (!validarId($id)) responder(['erro' => '"id" inválido: use de 2 a 24 letras minúsculas ou números.'], 422);
    $erro = validarMovel($d);
    if ($erro) responder(['erro' => $erro], 422);
    bd()->prepare('INSERT INTO moveis (id, nome, preco, rotativel, imagem_frente, imagem_direita, imagem_verso, imagem_esquerda,
        dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, publicado, ordem)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE nome=VALUES(nome), preco=VALUES(preco), rotativel=VALUES(rotativel),
        imagem_frente=VALUES(imagem_frente), imagem_direita=VALUES(imagem_direita),
        imagem_verso=VALUES(imagem_verso), imagem_esquerda=VALUES(imagem_esquerda),
        dias_semana=VALUES(dias_semana), hora_inicio=VALUES(hora_inicio), hora_fim=VALUES(hora_fim),
        data_inicio=VALUES(data_inicio), data_fim=VALUES(data_fim),
        publicado=VALUES(publicado), ordem=VALUES(ordem)')
      ->execute([
        $id, trim((string)$d['nome']), (int)$d['preco'], !empty($d['rotativel']) ? 1 : 0,
        trim((string)($d['imagemFrente'] ?? '')), trim((string)($d['imagemDireita'] ?? '')),
        trim((string)($d['imagemVerso'] ?? '')), trim((string)($d['imagemEsquerda'] ?? '')),
        normalizarDiasSemana(trim((string)($d['diasSemana'] ?? ''))), trim((string)($d['horaInicio'] ?? '')), trim((string)($d['horaFim'] ?? '')),
        trim((string)($d['dataInicio'] ?? '')), trim((string)($d['dataFim'] ?? '')),
        !empty($d['publicado']) ? 1 : 0, (int)($d['ordem'] ?? 0),
      ]);
    responder(['ok' => true]);
  }

  if ($acao === 'movel_excluir') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    bd()->prepare('DELETE FROM moveis WHERE id = ?')->execute([$id]);
    responder(['ok' => true]);
  }

  if ($acao === 'movel_duplicar') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    $novoId = (string)($d['novoId'] ?? '');
    if (!validarId($novoId)) responder(['erro' => '"novoId" inválido: use de 2 a 24 letras minúsculas ou números.'], 422);
    $st = bd()->prepare('SELECT * FROM moveis WHERE id = ?');
    $st->execute([$id]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m) responder(['erro' => 'Móvel não encontrado.'], 404);
    bd()->prepare('INSERT INTO moveis (id, nome, preco, rotativel, imagem_frente, imagem_direita, imagem_verso, imagem_esquerda,
        dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, publicado, ordem)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)')
      ->execute([
        $novoId, $m['nome'] . ' (cópia)', $m['preco'], $m['rotativel'],
        $m['imagem_frente'], $m['imagem_direita'], $m['imagem_verso'], $m['imagem_esquerda'],
        $m['dias_semana'], $m['hora_inicio'], $m['hora_fim'], $m['data_inicio'], $m['data_fim'], $m['ordem'],
      ]);
    responder(['ok' => true, 'id' => $novoId]);
  }

  if ($acao === 'movel_imagem') {
    $slots = ['frente' => 'imagem_frente', 'direita' => 'imagem_direita', 'verso' => 'imagem_verso', 'esquerda' => 'imagem_esquerda'];
    $slot = (string)($_POST['slot'] ?? '');
    if (!isset($slots[$slot])) responder(['erro' => 'Rotação inválida (use frente, direita, verso ou esquerda).'], 422);
    $arq = $_FILES['arquivo'] ?? null;
    if (!$arq || ($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $limite = ini_get('upload_max_filesize');
      $motivo = ($arq['error'] ?? null) === UPLOAD_ERR_INI_SIZE
        ? "A imagem passou do limite do servidor ($limite)."
        : 'Nenhum arquivo recebido.';
      responder(['erro' => $motivo], 422);
    }
    $ext = strtolower(pathinfo((string)$arq['name'], PATHINFO_EXTENSION));
    if (!isset(EXTENSOES_IMAGEM[$ext])) {
      responder(['erro' => 'Formato não aceito. Use webp, png ou jpg (webp é o mais leve).'], 422);
    }
    $info = @getimagesize($arq['tmp_name']);
    if (!$info || !in_array($info['mime'], EXTENSOES_IMAGEM, true)) {
      responder(['erro' => 'O arquivo não é uma imagem válida.'], 422);
    }
    $base = strtolower(pathinfo((string)$arq['name'], PATHINFO_FILENAME));
    $base = preg_replace('/[^a-z0-9_-]+/', '-', $base) ?: 'movel';
    $base = trim($base, '-') ?: 'movel';
    $nome = substr($base, 0, 60) . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!is_dir(pastaMoveis()) && !@mkdir(pastaMoveis(), 0755, true)) {
      responder(['erro' => 'Não consegui criar a pasta assets/moveis no servidor.'], 500);
    }
    if (!@move_uploaded_file($arq['tmp_name'], pastaMoveis() . '/' . $nome)) {
      responder(['erro' => 'Não consegui gravar o arquivo em assets/moveis (confira a permissão da pasta).'], 500);
    }
    responder(['ok' => true, 'slot' => $slot, 'imagem' => $nome, 'imagemUrl' => 'assets/moveis/' . $nome]);
  }

  /* ---------- Configurações gerais (fundo da Casa + preço pra desbloquear) ---------- */

  if ($acao === 'casa_config_obter') {
    $c = bd()->query('SELECT casa_fundo, preco_casa FROM configuracoes WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: ['casa_fundo' => '', 'preco_casa' => 5000];
    responder(['fundo' => $c['casa_fundo'], 'fundoUrl' => caminhoPublicoMovel($c['casa_fundo']), 'precoCasa' => (int)$c['preco_casa']]);
  }

  if ($acao === 'casa_preco_salvar') {
    $d = corpo();
    if (!is_numeric($d['precoCasa'] ?? null) || (int)$d['precoCasa'] < 0 || (int)$d['precoCasa'] > 10000000) {
      responder(['erro' => '"precoCasa" precisa ser um número entre 0 e 10000000.'], 422);
    }
    bd()->prepare('UPDATE configuracoes SET preco_casa = ? WHERE id = 1')->execute([(int)$d['precoCasa']]);
    responder(['ok' => true]);
  }

  if ($acao === 'casa_fundo_imagem') {
    $arq = $_FILES['arquivo'] ?? null;
    if (!$arq || ($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $limite = ini_get('upload_max_filesize');
      $motivo = ($arq['error'] ?? null) === UPLOAD_ERR_INI_SIZE
        ? "A imagem passou do limite do servidor ($limite)."
        : 'Nenhum arquivo recebido.';
      responder(['erro' => $motivo], 422);
    }
    $ext = strtolower(pathinfo((string)$arq['name'], PATHINFO_EXTENSION));
    if (!isset(EXTENSOES_IMAGEM[$ext])) {
      responder(['erro' => 'Formato não aceito. Use webp, png ou jpg (webp é o mais leve).'], 422);
    }
    $info = @getimagesize($arq['tmp_name']);
    if (!$info || !in_array($info['mime'], EXTENSOES_IMAGEM, true)) {
      responder(['erro' => 'O arquivo não é uma imagem válida.'], 422);
    }
    $nome = 'casa-fundo-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!is_dir(pastaMoveis()) && !@mkdir(pastaMoveis(), 0755, true)) {
      responder(['erro' => 'Não consegui criar a pasta assets/moveis no servidor.'], 500);
    }
    if (!@move_uploaded_file($arq['tmp_name'], pastaMoveis() . '/' . $nome)) {
      responder(['erro' => 'Não consegui gravar o arquivo em assets/moveis (confira a permissão da pasta).'], 500);
    }
    bd()->prepare('UPDATE configuracoes SET casa_fundo = ? WHERE id = 1')->execute([$nome]);
    responder(['ok' => true, 'fundo' => $nome, 'fundoUrl' => 'assets/moveis/' . $nome]);
  }

  if ($acao === 'casa_fundo_remover') {
    bd()->prepare('UPDATE configuracoes SET casa_fundo = \'\' WHERE id = 1')->execute();
    responder(['ok' => true]);
  }

  /* ---------- Capas de cabeçalho de telas fixas (Loja de Móveis, Mural) ---------- */

  $camposCapaValidos = ['lojamoveis', 'mural'];

  if ($acao === 'capas_obter') {
    $colunasCapaFixa = ['imagem', 'ativa', 'dias_semana', 'hora_inicio', 'hora_fim', 'data_inicio', 'data_fim', 'ancora'];
    $c = bd()->query('SELECT capa_lojamoveis_imagem, capa_lojamoveis_ativa, capa_lojamoveis_dias_semana, capa_lojamoveis_hora_inicio, capa_lojamoveis_hora_fim, capa_lojamoveis_data_inicio, capa_lojamoveis_data_fim, capa_lojamoveis_ancora,
        capa_mural_imagem, capa_mural_ativa, capa_mural_dias_semana, capa_mural_hora_inicio, capa_mural_hora_fim, capa_mural_data_inicio, capa_mural_data_fim, capa_mural_ancora
      FROM configuracoes WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    if (!$c) {
      $c = [];
      foreach (['lojamoveis', 'mural'] as $campo) foreach ($colunasCapaFixa as $sufixo) $c["capa_{$campo}_{$sufixo}"] = $sufixo === 'ancora' ? 'center' : '';
    }
    $linhaCapa = fn($campo) => [
      'imagem' => $c["capa_{$campo}_imagem"], 'imagemUrl' => caminhoPublicoMovel($c["capa_{$campo}_imagem"]), 'ativa' => (bool)$c["capa_{$campo}_ativa"],
      'diasSemana' => $c["capa_{$campo}_dias_semana"], 'horaInicio' => $c["capa_{$campo}_hora_inicio"], 'horaFim' => $c["capa_{$campo}_hora_fim"],
      'dataInicio' => $c["capa_{$campo}_data_inicio"], 'dataFim' => $c["capa_{$campo}_data_fim"], 'ancora' => $c["capa_{$campo}_ancora"],
    ];
    responder(['lojamoveis' => $linhaCapa('lojamoveis'), 'mural' => $linhaCapa('mural')]);
  }

  if ($acao === 'capas_salvar') {
    $d = corpo();
    $campo = (string)($d['campo'] ?? '');
    if (!in_array($campo, $camposCapaValidos, true)) {
      responder(['erro' => '"campo" precisa ser lojamoveis ou mural.'], 422);
    }
    $ancora = (string)($d['ancora'] ?? 'center');
    $erroAncora = validarAncora($ancora);
    if ($erroAncora) responder(['erro' => $erroAncora], 422);
    $erroAgenda = validarAgenda([
      'diasSemana' => $d['diasSemana'] ?? '', 'horaInicio' => $d['horaInicio'] ?? '', 'horaFim' => $d['horaFim'] ?? '',
      'dataInicio' => $d['dataInicio'] ?? '', 'dataFim' => $d['dataFim'] ?? '',
    ]);
    if ($erroAgenda) responder(['erro' => $erroAgenda], 422);
    $ativa = !empty($d['ativa']) ? 1 : 0;
    bd()->prepare("UPDATE configuracoes SET capa_{$campo}_ativa = ?, capa_{$campo}_dias_semana = ?, capa_{$campo}_hora_inicio = ?,
        capa_{$campo}_hora_fim = ?, capa_{$campo}_data_inicio = ?, capa_{$campo}_data_fim = ?, capa_{$campo}_ancora = ? WHERE id = 1")
      ->execute([
        $ativa, normalizarDiasSemana(trim((string)($d['diasSemana'] ?? ''))), trim((string)($d['horaInicio'] ?? '')),
        trim((string)($d['horaFim'] ?? '')), trim((string)($d['dataInicio'] ?? '')), trim((string)($d['dataFim'] ?? '')), $ancora,
      ]);
    responder(['ok' => true]);
  }

  if ($acao === 'capas_imagem') {
    $campo = (string)($_POST['campo'] ?? '');
    if (!in_array($campo, $camposCapaValidos, true)) {
      responder(['erro' => '"campo" precisa ser lojamoveis ou mural.'], 422);
    }
    $arq = $_FILES['arquivo'] ?? null;
    if (!$arq || ($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $limite = ini_get('upload_max_filesize');
      $motivo = ($arq['error'] ?? null) === UPLOAD_ERR_INI_SIZE
        ? "A imagem passou do limite do servidor ($limite)."
        : 'Nenhum arquivo recebido.';
      responder(['erro' => $motivo], 422);
    }
    $ext = strtolower(pathinfo((string)$arq['name'], PATHINFO_EXTENSION));
    if (!isset(EXTENSOES_IMAGEM[$ext])) {
      responder(['erro' => 'Formato não aceito. Use webp, png ou jpg (webp é o mais leve).'], 422);
    }
    $info = @getimagesize($arq['tmp_name']);
    if (!$info || !in_array($info['mime'], EXTENSOES_IMAGEM, true)) {
      responder(['erro' => 'O arquivo não é uma imagem válida.'], 422);
    }
    $nome = 'capa-' . $campo . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!is_dir(pastaMoveis()) && !@mkdir(pastaMoveis(), 0755, true)) {
      responder(['erro' => 'Não consegui criar a pasta assets/moveis no servidor.'], 500);
    }
    if (!@move_uploaded_file($arq['tmp_name'], pastaMoveis() . '/' . $nome)) {
      responder(['erro' => 'Não consegui gravar o arquivo em assets/moveis (confira a permissão da pasta).'], 500);
    }
    bd()->prepare("UPDATE configuracoes SET capa_{$campo}_imagem = ? WHERE id = 1")->execute([$nome]);
    responder(['ok' => true, 'imagem' => $nome, 'imagemUrl' => 'assets/moveis/' . $nome]);
  }

  /* ---------- Música de fundo (opcional, um arquivo só, tocado em loop no cliente) ---------- */

  if ($acao === 'musica_obter') {
    $c = bd()->query('SELECT musica_fundo, musica_ativa FROM configuracoes WHERE id = 1')->fetch(PDO::FETCH_ASSOC)
      ?: ['musica_fundo' => '', 'musica_ativa' => 0];
    responder(['url' => caminhoPublicoMovel($c['musica_fundo']), 'ativa' => (bool)$c['musica_ativa']]);
  }

  if ($acao === 'musica_ativa_salvar') {
    $d = corpo();
    bd()->prepare('UPDATE configuracoes SET musica_ativa = ? WHERE id = 1')->execute([!empty($d['ativa']) ? 1 : 0]);
    responder(['ok' => true]);
  }

  if ($acao === 'musica_upload') {
    $arq = $_FILES['arquivo'] ?? null;
    if (!$arq || ($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $limite = ini_get('upload_max_filesize');
      $motivo = ($arq['error'] ?? null) === UPLOAD_ERR_INI_SIZE
        ? "O áudio passou do limite do servidor ($limite)."
        : 'Nenhum arquivo recebido.';
      responder(['erro' => $motivo], 422);
    }
    $ext = strtolower(pathinfo((string)$arq['name'], PATHINFO_EXTENSION));
    if (!isset(EXTENSOES_AUDIO[$ext])) {
      responder(['erro' => 'Formato não aceito. Use mp3, ogg, wav ou m4a.'], 422);
    }
    $nome = 'musica-fundo-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!is_dir(pastaMoveis()) && !@mkdir(pastaMoveis(), 0755, true)) {
      responder(['erro' => 'Não consegui criar a pasta assets/moveis no servidor.'], 500);
    }
    if (!@move_uploaded_file($arq['tmp_name'], pastaMoveis() . '/' . $nome)) {
      responder(['erro' => 'Não consegui gravar o arquivo em assets/moveis (confira a permissão da pasta).'], 500);
    }
    bd()->prepare('UPDATE configuracoes SET musica_fundo = ? WHERE id = 1')->execute([$nome]);
    responder(['ok' => true, 'url' => 'assets/moveis/' . $nome]);
  }

  if ($acao === 'musica_remover') {
    bd()->prepare('UPDATE configuracoes SET musica_fundo = \'\', musica_ativa = 0 WHERE id = 1')->execute();
    responder(['ok' => true]);
  }

  /* ---------- Sprites dos minigames do Arcade (opcional — cada slot cai pro emoji padrão) ---------- */

  if ($acao === 'minigame_sprites_listar') {
    $linhas = [];
    foreach (bd()->query('SELECT chave, imagem, escala FROM minigame_sprites')->fetchAll(PDO::FETCH_ASSOC) as $l) {
      $linhas[$l['chave']] = $l;
    }
    responder(['sprites' => array_map(function ($chave, $def) use ($linhas) {
      $l = $linhas[$chave] ?? ['imagem' => '', 'escala' => 100];
      return [
        'chave' => $chave, 'emoji' => $def['emoji'], 'rotulo' => $def['rotulo'],
        'imagem' => $l['imagem'], 'imagemUrl' => caminhoPublicoMinigame($l['imagem']), 'escala' => (int)$l['escala'],
      ];
    }, array_keys(SPRITES_MINIGAME), SPRITES_MINIGAME)]);
  }

  if ($acao === 'minigame_sprite_imagem') {
    $chave = (string)($_POST['chave'] ?? '');
    if (!array_key_exists($chave, SPRITES_MINIGAME)) responder(['erro' => 'Slot de sprite desconhecido.'], 422);
    $arq = $_FILES['arquivo'] ?? null;
    if (!$arq || ($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $limite = ini_get('upload_max_filesize');
      $motivo = ($arq['error'] ?? null) === UPLOAD_ERR_INI_SIZE
        ? "A imagem passou do limite do servidor ($limite)."
        : 'Nenhum arquivo recebido.';
      responder(['erro' => $motivo], 422);
    }
    $ext = strtolower(pathinfo((string)$arq['name'], PATHINFO_EXTENSION));
    if (!isset(EXTENSOES_IMAGEM[$ext])) {
      responder(['erro' => 'Formato não aceito. Use webp, png ou jpg (webp é o mais leve).'], 422);
    }
    $info = @getimagesize($arq['tmp_name']);
    if (!$info || !in_array($info['mime'], EXTENSOES_IMAGEM, true)) {
      responder(['erro' => 'O arquivo não é uma imagem válida.'], 422);
    }
    $nome = 'sprite-' . $chave . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!is_dir(pastaMinigames()) && !@mkdir(pastaMinigames(), 0755, true)) {
      responder(['erro' => 'Não consegui criar a pasta assets/minigames no servidor.'], 500);
    }
    if (!@move_uploaded_file($arq['tmp_name'], pastaMinigames() . '/' . $nome)) {
      responder(['erro' => 'Não consegui gravar o arquivo em assets/minigames (confira a permissão da pasta).'], 500);
    }
    bd()->prepare('INSERT INTO minigame_sprites (chave, imagem) VALUES (?, ?) ON DUPLICATE KEY UPDATE imagem = VALUES(imagem)')
      ->execute([$chave, $nome]);
    responder(['ok' => true, 'imagem' => $nome, 'imagemUrl' => caminhoPublicoMinigame($nome)]);
  }

  if ($acao === 'minigame_sprite_remover') {
    $chave = (string)(corpo()['chave'] ?? '');
    if (!array_key_exists($chave, SPRITES_MINIGAME)) responder(['erro' => 'Slot de sprite desconhecido.'], 422);
    bd()->prepare('DELETE FROM minigame_sprites WHERE chave = ?')->execute([$chave]);
    responder(['ok' => true]);
  }

  if ($acao === 'minigame_sprite_escala_salvar') {
    $d = corpo();
    $chave = (string)($d['chave'] ?? '');
    if (!array_key_exists($chave, SPRITES_MINIGAME)) responder(['erro' => 'Slot de sprite desconhecido.'], 422);
    $escala = (int)($d['escala'] ?? 100);
    if ($escala < 50 || $escala > 200) responder(['erro' => '"escala" precisa ser um número entre 50 e 200.'], 422);
    // só existe o que ajustar se já tiver imagem enviada — a linha nasce no upload
    $st = bd()->prepare('UPDATE minigame_sprites SET escala = ? WHERE chave = ? AND imagem <> \'\'');
    $st->execute([$escala, $chave]);
    if ($st->rowCount() === 0) responder(['erro' => 'Suba uma imagem pra esse slot antes de ajustar o tamanho.'], 422);
    responder(['ok' => true]);
  }

  /* ---------- Fundo e sons de cada minigame do Arcade (opcional, um por jogo) ---------- */

  if ($acao === 'minigame_configs_listar') {
    $linhas = [];
    foreach (bd()->query('SELECT jogo, fundo, som_acerto, som_erro FROM minigame_config')->fetchAll(PDO::FETCH_ASSOC) as $c) {
      $linhas[$c['jogo']] = $c;
    }
    $configs = [];
    foreach (JOGOS_ARCADE as $jogo => $nome) {
      $l = $linhas[$jogo] ?? ['fundo' => '', 'som_acerto' => '', 'som_erro' => ''];
      $configs[$jogo] = [
        'jogo' => $jogo, 'nome' => $nome,
        'fundo' => $l['fundo'], 'fundoUrl' => caminhoPublicoMinigame($l['fundo']),
        'somAcerto' => $l['som_acerto'], 'somAcertoUrl' => caminhoPublicoMinigame($l['som_acerto']),
        'somErro' => $l['som_erro'], 'somErroUrl' => caminhoPublicoMinigame($l['som_erro']),
      ];
    }
    responder(['configs' => $configs]);
  }

  if ($acao === 'minigame_fundo_imagem') {
    $jogo = (string)($_POST['jogo'] ?? '');
    if (!array_key_exists($jogo, JOGOS_ARCADE)) responder(['erro' => 'Minigame desconhecido.'], 422);
    $arq = $_FILES['arquivo'] ?? null;
    if (!$arq || ($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $limite = ini_get('upload_max_filesize');
      $motivo = ($arq['error'] ?? null) === UPLOAD_ERR_INI_SIZE
        ? "A imagem passou do limite do servidor ($limite)."
        : 'Nenhum arquivo recebido.';
      responder(['erro' => $motivo], 422);
    }
    $ext = strtolower(pathinfo((string)$arq['name'], PATHINFO_EXTENSION));
    if (!isset(EXTENSOES_IMAGEM[$ext])) {
      responder(['erro' => 'Formato não aceito. Use webp, png ou jpg (webp é o mais leve).'], 422);
    }
    $info = @getimagesize($arq['tmp_name']);
    if (!$info || !in_array($info['mime'], EXTENSOES_IMAGEM, true)) {
      responder(['erro' => 'O arquivo não é uma imagem válida.'], 422);
    }
    $nome = 'fundo-' . $jogo . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!is_dir(pastaMinigames()) && !@mkdir(pastaMinigames(), 0755, true)) {
      responder(['erro' => 'Não consegui criar a pasta assets/minigames no servidor.'], 500);
    }
    if (!@move_uploaded_file($arq['tmp_name'], pastaMinigames() . '/' . $nome)) {
      responder(['erro' => 'Não consegui gravar o arquivo em assets/minigames (confira a permissão da pasta).'], 500);
    }
    bd()->prepare('INSERT INTO minigame_config (jogo, fundo) VALUES (?, ?) ON DUPLICATE KEY UPDATE fundo = VALUES(fundo)')
      ->execute([$jogo, $nome]);
    responder(['ok' => true, 'imagem' => $nome, 'imagemUrl' => caminhoPublicoMinigame($nome)]);
  }

  if ($acao === 'minigame_fundo_remover') {
    $jogo = (string)(corpo()['jogo'] ?? '');
    if (!array_key_exists($jogo, JOGOS_ARCADE)) responder(['erro' => 'Minigame desconhecido.'], 422);
    bd()->prepare('UPDATE minigame_config SET fundo = \'\' WHERE jogo = ?')->execute([$jogo]);
    responder(['ok' => true]);
  }

  if ($acao === 'minigame_som_upload') {
    $jogo = (string)($_POST['jogo'] ?? '');
    $evento = (string)($_POST['evento'] ?? '');
    if (!array_key_exists($jogo, JOGOS_ARCADE)) responder(['erro' => 'Minigame desconhecido.'], 422);
    if (!in_array($evento, ['acerto', 'erro'], true)) responder(['erro' => '"evento" precisa ser "acerto" ou "erro".'], 422);
    $arq = $_FILES['arquivo'] ?? null;
    if (!$arq || ($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $limite = ini_get('upload_max_filesize');
      $motivo = ($arq['error'] ?? null) === UPLOAD_ERR_INI_SIZE
        ? "O áudio passou do limite do servidor ($limite)."
        : 'Nenhum arquivo recebido.';
      responder(['erro' => $motivo], 422);
    }
    $ext = strtolower(pathinfo((string)$arq['name'], PATHINFO_EXTENSION));
    if (!isset(EXTENSOES_AUDIO[$ext])) {
      responder(['erro' => 'Formato não aceito. Use mp3, ogg, wav ou m4a.'], 422);
    }
    $nome = 'som-' . $evento . '-' . $jogo . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!is_dir(pastaMinigames()) && !@mkdir(pastaMinigames(), 0755, true)) {
      responder(['erro' => 'Não consegui criar a pasta assets/minigames no servidor.'], 500);
    }
    if (!@move_uploaded_file($arq['tmp_name'], pastaMinigames() . '/' . $nome)) {
      responder(['erro' => 'Não consegui gravar o arquivo em assets/minigames (confira a permissão da pasta).'], 500);
    }
    $coluna = $evento === 'acerto' ? 'som_acerto' : 'som_erro';
    bd()->prepare("INSERT INTO minigame_config (jogo, $coluna) VALUES (?, ?) ON DUPLICATE KEY UPDATE $coluna = VALUES($coluna)")
      ->execute([$jogo, $nome]);
    responder(['ok' => true, 'arquivo' => $nome, 'arquivoUrl' => caminhoPublicoMinigame($nome)]);
  }

  if ($acao === 'minigame_som_remover') {
    $d = corpo();
    $jogo = (string)($d['jogo'] ?? '');
    $evento = (string)($d['evento'] ?? '');
    if (!array_key_exists($jogo, JOGOS_ARCADE)) responder(['erro' => 'Minigame desconhecido.'], 422);
    if (!in_array($evento, ['acerto', 'erro'], true)) responder(['erro' => '"evento" precisa ser "acerto" ou "erro".'], 422);
    $coluna = $evento === 'acerto' ? 'som_acerto' : 'som_erro';
    bd()->prepare("UPDATE minigame_config SET $coluna = '' WHERE jogo = ?")->execute([$jogo]);
    responder(['ok' => true]);
  }

  /* ---------- Temporadas: pacotes de sprites do Arcade por período (ex.: "Festa Junina") ----------
     Mesmo esquema de agenda dos NPCs/móveis/capas (dias_semana/hora_inicio/fim/data_inicio/fim) —
     quem decide se está vigente é o cliente, com a hora do aparelho de quem está jogando. Uma
     temporada não precisa sobrescrever os 20 slots: só os que tiver em temporada_sprites saem do
     padrão, o resto continua puxando o sprite (ou emoji) de sempre. */

  if ($acao === 'temporadas_listar') {
    $linhas = bd()->query('SELECT id, nome, ativa, dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, ordem FROM temporadas ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC);
    $contagem = [];
    foreach (bd()->query("SELECT temporada_id, COUNT(*) AS n FROM temporada_sprites WHERE imagem <> '' GROUP BY temporada_id")->fetchAll(PDO::FETCH_ASSOC) as $c) {
      $contagem[$c['temporada_id']] = (int)$c['n'];
    }
    responder(['temporadas' => array_map(fn($t) => [
      'id' => $t['id'], 'nome' => $t['nome'], 'ativa' => (bool)$t['ativa'], 'ordem' => (int)$t['ordem'],
      'diasSemana' => $t['dias_semana'], 'horaInicio' => $t['hora_inicio'], 'horaFim' => $t['hora_fim'],
      'dataInicio' => $t['data_inicio'], 'dataFim' => $t['data_fim'],
      'sprites' => $contagem[$t['id']] ?? 0,
    ], $linhas)]);
  }

  if ($acao === 'temporada_obter') {
    $id = (string)($_GET['id'] ?? '');
    $st = bd()->prepare('SELECT id, nome, ativa, dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, ordem FROM temporadas WHERE id = ?');
    $st->execute([$id]);
    $t = $st->fetch(PDO::FETCH_ASSOC);
    if (!$t) responder(['erro' => 'Temporada não encontrada.'], 404);
    $st2 = bd()->prepare('SELECT chave, imagem, escala FROM temporada_sprites WHERE temporada_id = ?');
    $st2->execute([$id]);
    $linhas = [];
    foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $l) $linhas[$l['chave']] = $l;
    responder([
      'id' => $t['id'], 'nome' => $t['nome'], 'ativa' => (bool)$t['ativa'], 'ordem' => (int)$t['ordem'],
      'diasSemana' => $t['dias_semana'], 'horaInicio' => $t['hora_inicio'], 'horaFim' => $t['hora_fim'],
      'dataInicio' => $t['data_inicio'], 'dataFim' => $t['data_fim'],
      'sprites' => array_map(function ($chave, $def) use ($linhas) {
        $l = $linhas[$chave] ?? ['imagem' => '', 'escala' => 100];
        return [
          'chave' => $chave, 'emoji' => $def['emoji'], 'rotulo' => $def['rotulo'],
          'imagem' => $l['imagem'], 'imagemUrl' => caminhoPublicoMinigame($l['imagem']), 'escala' => (int)$l['escala'],
        ];
      }, array_keys(SPRITES_MINIGAME), SPRITES_MINIGAME),
    ]);
  }

  if ($acao === 'temporada_salvar') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    if (!validarId($id)) responder(['erro' => '"id" inválido: use de 2 a 24 letras minúsculas ou números.'], 422);
    $nome = trim((string)($d['nome'] ?? ''));
    if ($nome === '' || mb_strlen($nome) > 60) responder(['erro' => '"nome" precisa ter de 1 a 60 caracteres.'], 422);
    $erroAgenda = validarAgenda($d);
    if ($erroAgenda) responder(['erro' => $erroAgenda], 422);
    bd()->prepare('INSERT INTO temporadas (id, nome, ativa, dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, ordem)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE nome=VALUES(nome), ativa=VALUES(ativa), dias_semana=VALUES(dias_semana),
        hora_inicio=VALUES(hora_inicio), hora_fim=VALUES(hora_fim), data_inicio=VALUES(data_inicio),
        data_fim=VALUES(data_fim), ordem=VALUES(ordem)')
      ->execute([
        $id, $nome, !empty($d['ativa']) ? 1 : 0,
        normalizarDiasSemana(trim((string)($d['diasSemana'] ?? ''))), trim((string)($d['horaInicio'] ?? '')), trim((string)($d['horaFim'] ?? '')),
        trim((string)($d['dataInicio'] ?? '')), trim((string)($d['dataFim'] ?? '')), (int)($d['ordem'] ?? 0),
      ]);
    responder(['ok' => true]);
  }

  if ($acao === 'temporada_excluir') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    bd()->prepare('DELETE FROM temporada_sprites WHERE temporada_id = ?')->execute([$id]);
    bd()->prepare('DELETE FROM temporadas WHERE id = ?')->execute([$id]);
    responder(['ok' => true]);
  }

  if ($acao === 'temporada_duplicar') {
    $d = corpo();
    $novoId = (string)($d['novoId'] ?? '');
    if (!validarId($novoId)) responder(['erro' => '"novoId" inválido: use de 2 a 24 letras minúsculas ou números.'], 422);
    $st = bd()->prepare('SELECT COUNT(*) FROM temporadas WHERE id = ?');
    $st->execute([$novoId]);
    if ($st->fetchColumn()) responder(['erro' => "Já existe uma temporada com id \"$novoId\"."], 422);
    $st = bd()->prepare('SELECT nome, dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, ordem FROM temporadas WHERE id = ?');
    $st->execute([(string)($d['id'] ?? '')]);
    $t = $st->fetch(PDO::FETCH_ASSOC);
    if (!$t) responder(['erro' => 'Temporada original não encontrada.'], 404);
    bd()->prepare('INSERT INTO temporadas (id, nome, ativa, dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, ordem)
      VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?)')
      ->execute([
        $novoId, $t['nome'] . ' (cópia)', $t['dias_semana'], $t['hora_inicio'], $t['hora_fim'],
        $t['data_inicio'], $t['data_fim'], (int)$t['ordem'],
      ]);
    $st = bd()->prepare('SELECT chave, imagem, escala FROM temporada_sprites WHERE temporada_id = ?');
    $st->execute([(string)($d['id'] ?? '')]);
    $ins = bd()->prepare('INSERT INTO temporada_sprites (temporada_id, chave, imagem, escala) VALUES (?, ?, ?, ?)');
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) $ins->execute([$novoId, $l['chave'], $l['imagem'], $l['escala']]);
    responder(['ok' => true, 'id' => $novoId]);
  }

  if ($acao === 'temporada_sprite_imagem') {
    $temporadaId = (string)($_POST['temporadaId'] ?? '');
    $chave = (string)($_POST['chave'] ?? '');
    if (!array_key_exists($chave, SPRITES_MINIGAME)) responder(['erro' => 'Slot de sprite desconhecido.'], 422);
    $st = bd()->prepare('SELECT COUNT(*) FROM temporadas WHERE id = ?');
    $st->execute([$temporadaId]);
    if (!$st->fetchColumn()) responder(['erro' => 'Temporada não encontrada.'], 404);
    $arq = $_FILES['arquivo'] ?? null;
    if (!$arq || ($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $limite = ini_get('upload_max_filesize');
      $motivo = ($arq['error'] ?? null) === UPLOAD_ERR_INI_SIZE
        ? "A imagem passou do limite do servidor ($limite)."
        : 'Nenhum arquivo recebido.';
      responder(['erro' => $motivo], 422);
    }
    $ext = strtolower(pathinfo((string)$arq['name'], PATHINFO_EXTENSION));
    if (!isset(EXTENSOES_IMAGEM[$ext])) {
      responder(['erro' => 'Formato não aceito. Use webp, png ou jpg (webp é o mais leve).'], 422);
    }
    $info = @getimagesize($arq['tmp_name']);
    if (!$info || !in_array($info['mime'], EXTENSOES_IMAGEM, true)) {
      responder(['erro' => 'O arquivo não é uma imagem válida.'], 422);
    }
    $nome = 'temporada-' . $temporadaId . '-sprite-' . $chave . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!is_dir(pastaMinigames()) && !@mkdir(pastaMinigames(), 0755, true)) {
      responder(['erro' => 'Não consegui criar a pasta assets/minigames no servidor.'], 500);
    }
    if (!@move_uploaded_file($arq['tmp_name'], pastaMinigames() . '/' . $nome)) {
      responder(['erro' => 'Não consegui gravar o arquivo em assets/minigames (confira a permissão da pasta).'], 500);
    }
    bd()->prepare('INSERT INTO temporada_sprites (temporada_id, chave, imagem) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE imagem = VALUES(imagem)')
      ->execute([$temporadaId, $chave, $nome]);
    responder(['ok' => true, 'imagem' => $nome, 'imagemUrl' => caminhoPublicoMinigame($nome)]);
  }

  if ($acao === 'temporada_sprite_remover') {
    $d = corpo();
    $temporadaId = (string)($d['temporadaId'] ?? '');
    $chave = (string)($d['chave'] ?? '');
    if (!array_key_exists($chave, SPRITES_MINIGAME)) responder(['erro' => 'Slot de sprite desconhecido.'], 422);
    bd()->prepare('DELETE FROM temporada_sprites WHERE temporada_id = ? AND chave = ?')->execute([$temporadaId, $chave]);
    responder(['ok' => true]);
  }

  if ($acao === 'temporada_sprite_escala_salvar') {
    $d = corpo();
    $temporadaId = (string)($d['temporadaId'] ?? '');
    $chave = (string)($d['chave'] ?? '');
    if (!array_key_exists($chave, SPRITES_MINIGAME)) responder(['erro' => 'Slot de sprite desconhecido.'], 422);
    $escala = (int)($d['escala'] ?? 100);
    if ($escala < 50 || $escala > 200) responder(['erro' => '"escala" precisa ser um número entre 50 e 200.'], 422);
    $st = bd()->prepare('UPDATE temporada_sprites SET escala = ? WHERE temporada_id = ? AND chave = ? AND imagem <> \'\'');
    $st->execute([$escala, $temporadaId, $chave]);
    if ($st->rowCount() === 0) responder(['erro' => 'Suba uma imagem pra esse slot antes de ajustar o tamanho.'], 422);
    responder(['ok' => true]);
  }

  /* ---------- Lojas genéricas (Lanchonete, Mercado, e as que o Hostmaster criar) ---------- */

  if ($acao === 'lojas_listar') {
    $linhas = bd()->query('SELECT id, nome, emoji, publicado, ordem, capa_imagem, capa_ativa,
      capa_dias_semana, capa_hora_inicio, capa_hora_fim, capa_data_inicio, capa_data_fim, capa_ancora FROM lojas ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC);
    responder(['lojas' => array_map(fn($l) => [
      'id' => $l['id'], 'nome' => $l['nome'], 'emoji' => $l['emoji'],
      'publicado' => (bool)$l['publicado'], 'ordem' => (int)$l['ordem'],
      'capaImagem' => $l['capa_imagem'], 'capaImagemUrl' => caminhoPublicoLoja($l['capa_imagem']), 'capaAtiva' => (bool)$l['capa_ativa'],
      'capaDiasSemana' => $l['capa_dias_semana'], 'capaHoraInicio' => $l['capa_hora_inicio'], 'capaHoraFim' => $l['capa_hora_fim'],
      'capaDataInicio' => $l['capa_data_inicio'], 'capaDataFim' => $l['capa_data_fim'], 'capaAncora' => $l['capa_ancora'],
    ], $linhas)]);
  }

  if ($acao === 'loja_obter') {
    $st = bd()->prepare('SELECT id, nome, emoji, publicado, ordem, capa_imagem, capa_ativa,
      capa_dias_semana, capa_hora_inicio, capa_hora_fim, capa_data_inicio, capa_data_fim, capa_ancora FROM lojas WHERE id = ?');
    $st->execute([(string)($_GET['id'] ?? '')]);
    $l = $st->fetch(PDO::FETCH_ASSOC);
    if (!$l) responder(['erro' => 'Loja não encontrada.'], 404);
    responder([
      'id' => $l['id'], 'nome' => $l['nome'], 'emoji' => $l['emoji'], 'publicado' => (bool)$l['publicado'], 'ordem' => (int)$l['ordem'],
      'capaImagem' => $l['capa_imagem'], 'capaImagemUrl' => caminhoPublicoLoja($l['capa_imagem']), 'capaAtiva' => (bool)$l['capa_ativa'],
      'capaDiasSemana' => $l['capa_dias_semana'], 'capaHoraInicio' => $l['capa_hora_inicio'], 'capaHoraFim' => $l['capa_hora_fim'],
      'capaDataInicio' => $l['capa_data_inicio'], 'capaDataFim' => $l['capa_data_fim'], 'capaAncora' => $l['capa_ancora'],
    ]);
  }

  if ($acao === 'loja_salvar') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    if (!validarId($id)) responder(['erro' => '"id" inválido: use de 2 a 24 letras minúsculas ou números.'], 422);
    $erro = validarLoja($d);
    if ($erro) responder(['erro' => $erro], 422);
    bd()->prepare('INSERT INTO lojas (id, nome, emoji, publicado, ordem, capa_imagem, capa_ativa,
        capa_dias_semana, capa_hora_inicio, capa_hora_fim, capa_data_inicio, capa_data_fim, capa_ancora)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE nome=VALUES(nome), emoji=VALUES(emoji), publicado=VALUES(publicado), ordem=VALUES(ordem),
        capa_imagem=VALUES(capa_imagem), capa_ativa=VALUES(capa_ativa), capa_dias_semana=VALUES(capa_dias_semana),
        capa_hora_inicio=VALUES(capa_hora_inicio), capa_hora_fim=VALUES(capa_hora_fim),
        capa_data_inicio=VALUES(capa_data_inicio), capa_data_fim=VALUES(capa_data_fim), capa_ancora=VALUES(capa_ancora)')
      ->execute([
        $id, trim((string)$d['nome']), (string)($d['emoji'] ?? '🏪'), !empty($d['publicado']) ? 1 : 0, (int)($d['ordem'] ?? 0),
        trim((string)($d['capaImagem'] ?? '')), !empty($d['capaAtiva']) ? 1 : 0,
        normalizarDiasSemana(trim((string)($d['capaDiasSemana'] ?? ''))), trim((string)($d['capaHoraInicio'] ?? '')), trim((string)($d['capaHoraFim'] ?? '')),
        trim((string)($d['capaDataInicio'] ?? '')), trim((string)($d['capaDataFim'] ?? '')), (string)($d['capaAncora'] ?? 'center'),
      ]);
    responder(['ok' => true]);
  }

  if ($acao === 'loja_imagem') {
    $arq = $_FILES['arquivo'] ?? null;
    if (!$arq || $arq['error'] !== UPLOAD_ERR_OK) responder(['erro' => 'Falha no envio da imagem.'], 422);
    $ext = strtolower(pathinfo((string)$arq['name'], PATHINFO_EXTENSION));
    if (!isset(EXTENSOES_IMAGEM[$ext])) responder(['erro' => 'Formato inválido. Use webp, png ou jpg.'], 422);
    $nome = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!is_dir(pastaLojas()) && !@mkdir(pastaLojas(), 0755, true)) {
      responder(['erro' => 'Não consegui criar a pasta de imagens das lojas no servidor.'], 500);
    }
    if (!@move_uploaded_file($arq['tmp_name'], pastaLojas() . '/' . $nome)) {
      responder(['erro' => 'Não consegui salvar a imagem no servidor.'], 500);
    }
    responder(['ok' => true, 'imagem' => $nome, 'imagemUrl' => caminhoPublicoLoja($nome)]);
  }

  if ($acao === 'loja_excluir') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    // os itens dela vão junto (FOREIGN KEY ... ON DELETE CASCADE) — avisa quantos, senão
    // o Hostmaster apaga sem saber que zerou o catálogo inteiro
    $st = bd()->prepare('SELECT COUNT(*) FROM itens_loja WHERE loja_id = ?');
    $st->execute([$id]);
    $itensAfetados = (int)$st->fetchColumn();
    bd()->prepare('DELETE FROM lojas WHERE id = ?')->execute([$id]);
    responder(['ok' => true, 'itensAfetados' => $itensAfetados]);
  }

  function linhaItemLoja(array $it): array {
    return [
      'id' => $it['id'], 'lojaId' => $it['loja_id'], 'nome' => $it['nome'], 'emoji' => $it['emoji'],
      'tipo' => $it['tipo'],
      'preco' => (int)$it['preco'], 'imagem' => $it['imagem'], 'imagemUrl' => caminhoPublicoLoja($it['imagem']),
      'fome' => (int)$it['fome'], 'alegria' => (int)$it['alegria'],
      'variantes' => array_map(fn($v) => [
        'id' => $v['id'], 'nome' => $v['nome'], 'imagem' => $v['imagem'] ?? '', 'imagemUrl' => caminhoPublicoLoja($v['imagem'] ?? ''),
      ], json_decode($it['variantes'], true) ?: []),
      'diasSemana' => $it['dias_semana'], 'horaInicio' => $it['hora_inicio'], 'horaFim' => $it['hora_fim'],
      'dataInicio' => $it['data_inicio'], 'dataFim' => $it['data_fim'],
      'publicado' => (bool)$it['publicado'], 'ordem' => (int)$it['ordem'],
      'estoqueTotal' => (int)$it['estoque_total'], 'estoqueVendido' => (int)$it['estoque_vendido'],
    ];
  }

  if ($acao === 'itens_loja_listar') {
    $lojaId = $_GET['loja'] ?? null;
    if ($lojaId) {
      $st = bd()->prepare('SELECT * FROM itens_loja WHERE loja_id = ? ORDER BY ordem, nome');
      $st->execute([(string)$lojaId]);
    } else {
      $st = bd()->query('SELECT * FROM itens_loja ORDER BY loja_id, ordem, nome');
    }
    responder(['itens' => array_map('linhaItemLoja', $st->fetchAll(PDO::FETCH_ASSOC))]);
  }

  if ($acao === 'item_loja_obter') {
    $st = bd()->prepare('SELECT * FROM itens_loja WHERE id = ?');
    $st->execute([(string)($_GET['id'] ?? '')]);
    $it = $st->fetch(PDO::FETCH_ASSOC);
    if (!$it) responder(['erro' => 'Item não encontrado.'], 404);
    responder(linhaItemLoja($it));
  }

  if ($acao === 'item_loja_salvar') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    if (!validarId($id)) responder(['erro' => '"id" inválido: use de 2 a 24 letras minúsculas ou números.'], 422);
    $lojaId = (string)($d['lojaId'] ?? '');
    $st = bd()->prepare('SELECT COUNT(*) FROM lojas WHERE id = ?');
    $st->execute([$lojaId]);
    if (!$st->fetchColumn()) responder(['erro' => 'Escolha uma loja válida.'], 422);
    $erro = validarItemLoja($d);
    if ($erro) responder(['erro' => $erro], 422);
    $variantes = array_map(fn($v) => [
      'id' => (string)$v['id'], 'nome' => trim((string)$v['nome']), 'imagem' => trim((string)($v['imagem'] ?? '')),
    ], $d['variantes'] ?? []);
    bd()->prepare('INSERT INTO itens_loja (id, loja_id, nome, emoji, tipo, preco, imagem, fome, alegria, variantes,
        dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, publicado, ordem, estoque_total)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE loja_id=VALUES(loja_id), nome=VALUES(nome), emoji=VALUES(emoji), tipo=VALUES(tipo), preco=VALUES(preco),
        imagem=VALUES(imagem), fome=VALUES(fome), alegria=VALUES(alegria), variantes=VALUES(variantes),
        dias_semana=VALUES(dias_semana), hora_inicio=VALUES(hora_inicio), hora_fim=VALUES(hora_fim),
        data_inicio=VALUES(data_inicio), data_fim=VALUES(data_fim), publicado=VALUES(publicado), ordem=VALUES(ordem),
        estoque_total=VALUES(estoque_total)')
      ->execute([
        $id, $lojaId, trim((string)$d['nome']), (string)($d['emoji'] ?? '🔹'), (string)($d['tipo'] ?? 'comida'), (int)$d['preco'],
        trim((string)($d['imagem'] ?? '')), (int)($d['fome'] ?? 0), (int)($d['alegria'] ?? 0),
        json_encode($variantes, JSON_UNESCAPED_UNICODE),
        normalizarDiasSemana(trim((string)($d['diasSemana'] ?? ''))), trim((string)($d['horaInicio'] ?? '')), trim((string)($d['horaFim'] ?? '')),
        trim((string)($d['dataInicio'] ?? '')), trim((string)($d['dataFim'] ?? '')),
        !empty($d['publicado']) ? 1 : 0, (int)($d['ordem'] ?? 0), (int)($d['estoqueTotal'] ?? 0),
      ]);
    responder(['ok' => true]);
  }

  if ($acao === 'item_loja_excluir') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    bd()->prepare('DELETE FROM itens_loja WHERE id = ?')->execute([$id]);
    responder(['ok' => true]);
  }

  if ($acao === 'item_loja_duplicar') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    $novoId = (string)($d['novoId'] ?? '');
    if (!validarId($novoId)) responder(['erro' => '"novoId" inválido: use de 2 a 24 letras minúsculas ou números.'], 422);
    $st = bd()->prepare('SELECT * FROM itens_loja WHERE id = ?');
    $st->execute([$id]);
    $it = $st->fetch(PDO::FETCH_ASSOC);
    if (!$it) responder(['erro' => 'Item não encontrado.'], 404);
    bd()->prepare('INSERT INTO itens_loja (id, loja_id, nome, emoji, tipo, preco, imagem, fome, alegria, variantes,
        dias_semana, hora_inicio, hora_fim, data_inicio, data_fim, publicado, ordem, estoque_total)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)')
      ->execute([
        $novoId, $it['loja_id'], $it['nome'] . ' (cópia)', $it['emoji'], $it['tipo'], $it['preco'], $it['imagem'], $it['fome'], $it['alegria'],
        $it['variantes'], $it['dias_semana'], $it['hora_inicio'], $it['hora_fim'], $it['data_inicio'], $it['data_fim'], $it['ordem'], $it['estoque_total'],
      ]);
    responder(['ok' => true, 'id' => $novoId]);
  }

  if ($acao === 'item_loja_estoque_resetar') {
    $d = corpo();
    $id = (string)($d['id'] ?? '');
    bd()->prepare('UPDATE itens_loja SET estoque_vendido = 0 WHERE id = ?')->execute([$id]);
    responder(['ok' => true]);
  }

  // upload de imagem pra um item de loja — "slot" opcional identifica QUAL variante recebe
  // a imagem (o id dela); sem "slot", vira a imagem base do item
  if ($acao === 'item_loja_imagem') {
    $arq = $_FILES['arquivo'] ?? null;
    if (!$arq || ($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $limite = ini_get('upload_max_filesize');
      $motivo = ($arq['error'] ?? null) === UPLOAD_ERR_INI_SIZE
        ? "A imagem passou do limite do servidor ($limite)."
        : 'Nenhum arquivo recebido.';
      responder(['erro' => $motivo], 422);
    }
    $ext = strtolower(pathinfo((string)$arq['name'], PATHINFO_EXTENSION));
    if (!isset(EXTENSOES_IMAGEM[$ext])) {
      responder(['erro' => 'Formato não aceito. Use webp, png ou jpg (webp é o mais leve).'], 422);
    }
    $info = @getimagesize($arq['tmp_name']);
    if (!$info || !in_array($info['mime'], EXTENSOES_IMAGEM, true)) {
      responder(['erro' => 'O arquivo não é uma imagem válida.'], 422);
    }
    $base = strtolower(pathinfo((string)$arq['name'], PATHINFO_FILENAME));
    $base = preg_replace('/[^a-z0-9_-]+/', '-', $base) ?: 'item';
    $base = trim($base, '-') ?: 'item';
    $nome = substr($base, 0, 60) . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!is_dir(pastaLojas()) && !@mkdir(pastaLojas(), 0755, true)) {
      responder(['erro' => 'Não consegui criar a pasta assets/lojas no servidor.'], 500);
    }
    if (!@move_uploaded_file($arq['tmp_name'], pastaLojas() . '/' . $nome)) {
      responder(['erro' => 'Não consegui gravar o arquivo em assets/lojas (confira a permissão da pasta).'], 500);
    }
    responder(['ok' => true, 'slot' => (string)($_POST['slot'] ?? ''), 'imagem' => $nome, 'imagemUrl' => 'assets/lojas/' . $nome]);
  }

  if ($acao === 'destinos') {
    // "rascunho" vai junto: um ponto que aponta pra mundo/cena/lição não publicada é
    // escondido do aluno por conteudo.php, então o painel precisa poder avisar disso
    $mundos = bd()->query('SELECT id, nome, emoji, publicado FROM mundos ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC);
    $cenas = bd()->query('SELECT id, nome, publicado FROM cenas ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC);
    $licoes = bd()->query('SELECT id, titulo, emoji, publicado FROM licoes ORDER BY mundo_id, ordem')->fetchAll(PDO::FETCH_ASSOC);
    $npcs = bd()->query('SELECT id, nome, emoji, publicado FROM npcs ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC);
    $itens = bd()->query('SELECT id, nome, emoji, publicado FROM itens ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC);
    $lojas = bd()->query('SELECT id, nome, emoji, publicado FROM lojas ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC);
    // gatilhos não são linhas próprias — são a "chave" que missões tipo "gatilho" pedem;
    // a lista vem das missões pra evitar typo entre o ponto e a missão que ele ativa
    $gatilhos = bd()->query('SELECT id, titulo, publicado, JSON_UNQUOTE(JSON_EXTRACT(objetivo, \'$.chave\')) AS chave
      FROM missoes WHERE tipo = \'gatilho\' ORDER BY ordem, titulo')->fetchAll(PDO::FETCH_ASSOC);
    $marca = fn($rotulo, $pub) => $rotulo . ($pub ? '' : ' — rascunho, não aparece pro aluno');
    responder([
      'mundo' => array_map(fn($m) => ['id' => $m['id'], 'rotulo' => $marca($m['emoji'] . ' ' . $m['nome'], $m['publicado']), 'publicado' => (bool)$m['publicado']], $mundos),
      'cena'  => array_map(fn($c) => ['id' => $c['id'], 'rotulo' => $marca('🗺️ ' . $c['nome'], $c['publicado']), 'publicado' => (bool)$c['publicado']], $cenas),
      'licao' => array_map(fn($l) => ['id' => $l['id'], 'rotulo' => $marca($l['emoji'] . ' ' . $l['titulo'], $l['publicado']), 'publicado' => (bool)$l['publicado']], $licoes),
      'tela'  => array_map(fn($t) => ['id' => $t, 'rotulo' => ROTULOS_TELA[$t] ?? ucfirst($t), 'publicado' => true], TELAS_VALIDAS),
      'npc'   => array_map(fn($n) => ['id' => $n['id'], 'rotulo' => $marca($n['emoji'] . ' ' . $n['nome'], $n['publicado']), 'publicado' => (bool)$n['publicado']], $npcs),
      'gatilho' => array_map(fn($g) => ['id' => $g['chave'], 'rotulo' => $marca('🎯 ' . $g['chave'] . ' (missão "' . $g['titulo'] . '")', $g['publicado']), 'publicado' => (bool)$g['publicado']], $gatilhos),
      'item' => array_map(fn($it) => ['id' => $it['id'], 'rotulo' => $marca($it['emoji'] . ' ' . $it['nome'], $it['publicado']), 'publicado' => (bool)$it['publicado']], $itens),
      'loja' => array_map(fn($l) => ['id' => $l['id'], 'rotulo' => $marca($l['emoji'] . ' ' . $l['nome'], $l['publicado']), 'publicado' => (bool)$l['publicado']], $lojas),
    ]);
  }

  /* ---------- Empurra-Caixas (minigame estilo Sokoban): fases ----------
     "numero" decide a ordem/identidade da fase pro cliente (não é PRIMARY KEY — "id",
     o AUTO_INCREMENT, é quem identifica a fase de verdade nesse CRUD). Trocar o texto da
     grade de uma fase já publicada não afeta quem já jogou; só reaproveita o slot. */
  if ($acao === 'sokoban_fases_listar') {
    $linhas = bd()->query('SELECT id, numero, nome, publicada, LENGTH(grade) AS tamanho FROM sokoban_fases ORDER BY numero, id')->fetchAll(PDO::FETCH_ASSOC);
    responder(['fases' => array_map(fn($f) => [
      'id' => (int)$f['id'], 'numero' => (int)$f['numero'], 'nome' => $f['nome'],
      'publicada' => (bool)$f['publicada'], 'tamanho' => (int)$f['tamanho'],
    ], $linhas)]);
  }

  if ($acao === 'sokoban_fase_obter') {
    $id = (int)($_GET['id'] ?? 0);
    $st = bd()->prepare('SELECT id, numero, nome, grade, publicada FROM sokoban_fases WHERE id = ?');
    $st->execute([$id]);
    $f = $st->fetch(PDO::FETCH_ASSOC);
    if (!$f) responder(['erro' => 'Fase não encontrada.'], 404);
    responder(['fase' => [
      'id' => (int)$f['id'], 'numero' => (int)$f['numero'], 'nome' => $f['nome'],
      'grade' => $f['grade'], 'publicada' => (bool)$f['publicada'],
    ]]);
  }

  if ($acao === 'sokoban_fase_salvar') {
    $d = corpo();
    $numero = $d['numero'] ?? null;
    if (!is_int($numero) || $numero < 1 || $numero > 999) responder(['erro' => '"numero" precisa ser um número inteiro de 1 a 999.'], 422);
    $nome = trim((string)($d['nome'] ?? ''));
    if (mb_strlen($nome) > 60) responder(['erro' => '"nome" pode ter no máximo 60 caracteres.'], 422);
    $grade = (string)($d['grade'] ?? '');
    $erro = validarSokobanGrade($grade);
    if ($erro) responder(['erro' => $erro], 422);
    $id = (int)($d['id'] ?? 0);
    $publicada = !empty($d['publicada']) ? 1 : 0;
    if ($id > 0) {
      bd()->prepare('UPDATE sokoban_fases SET numero = ?, nome = ?, grade = ?, publicada = ? WHERE id = ?')
        ->execute([$numero, $nome, $grade, $publicada, $id]);
    } else {
      bd()->prepare('INSERT INTO sokoban_fases (numero, nome, grade, publicada) VALUES (?, ?, ?, ?)')
        ->execute([$numero, $nome, $grade, $publicada]);
      $id = (int)bd()->lastInsertId();
    }
    responder(['ok' => true, 'id' => $id]);
  }

  if ($acao === 'sokoban_fase_excluir') {
    $d = corpo();
    $id = (int)($d['id'] ?? 0);
    bd()->prepare('DELETE FROM sokoban_fases WHERE id = ?')->execute([$id]);
    responder(['ok' => true]);
  }

  responder(['erro' => 'Ação desconhecida.'], 404);

} catch (Throwable $e) {
  error_log('[bichoteca-admin] ' . $e->getMessage());
  responder(['erro' => 'Deu ruim no servidor. Tente de novo.'], 500);
}
