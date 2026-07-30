# 🐾 Bichoteca

**A vila onde estudar é brincadeira.**

Plataforma de estudos gamificada para o ensino fundamental e médio, com a cara dos jogos de bichinho virtual que a gente jogava nos anos 2000 — Neopets, Club Penguin — mas com trilhas de História, Português, Biologia, Matemática e Geografia por trás.

O aluno adota uma capivara, um salsicha ou um gato. Para alimentar e vestir o bicho, precisa de moedas. Para ganhar moedas, estuda e joga minigames.

---

## Rodando

É HTML, CSS e JavaScript puro. Sem Node, sem build, sem framework, sem dependência.

```bash
git clone https://github.com/pedrobrunel/pets.git
cd pets
python3 -m http.server 8000   # http://localhost:8000/ é a home; o jogo fica em /app.html
```

Para publicar: joga tudo dentro de `public_html/` na hospedagem compartilhada e acabou. Também roda de graça no GitHub Pages (*Settings → Pages → branch `main`*) — o protótipo inteiro funciona sem back-end.

> **Requer HTTPS** para o service worker e o botão "Instalar aplicativo" funcionarem. O SSL grátis da Hostinger e o do GitHub Pages já resolvem.

---

## Estrutura

```
.github/workflows/
  deploy.yml          publica na Hostinger a cada push na main
index.html            home pública — objetivos do projeto e CTA de entrar/cadastrar
app.html              o jogo em si — onboarding, mapa de mundos, minigames, loja, mural, perfil
admin.html            painel do Hostmaster — mapas, conteúdo, jogadores, métricas, backup
responsavel.html       painel do responsável/professor — entra com o login do aluno, vê progresso e onde errou
assets/
  mapa-mundosv2.webp  arte da Ilha do saber (a cena inicial)
  cenas/              imagens de mapa enviadas pelo painel — ficam só no servidor, fora do Git
manifest.json         identidade do PWA (nome, ícones, cor, tela cheia; start_url aponta pro app.html)
sw.js                 service worker: funciona offline depois da 1ª visita
icone-*.png           ícones do app instalado
.htaccess             HTTPS forçado, cache e proteção das credenciais
api/
  bd.php              helpers de banco compartilhados (conexão PDO, respostas JSON)
  estado.php          back-end opcional: login de aluno por usuário + senha, salvar progresso
  admin.php           back-end do painel do Hostmaster (mundos, lições, jogadores, backup)
  conteudo.php        endpoint público: devolve os mundos, lições e mapas publicados pro app.html
  install.php         roda uma vez (e de novo pra trocar a senha do painel): cria as tabelas e semeia o conteúdo de exemplo
  seed-conteudo.json  conteúdo de exemplo semeado na 1ª instalação — também serve de referência do formato de JSON que o painel aceita
  config.example.php  modelo — copie para config.php e preencha (não versionado)
```

---

## O que já funciona

- Adoção de bicho: capivara, salsicha ou gato, desenhados em SVG dentro do próprio código (zero imagem externa para carregar)
- Casa com fome, alegria, energia e nível, com desgaste suave por tempo
- 5 trilhas × 2 lições × 3 perguntas, com leitura curta antes do quiz
- Moedas e XP: a primeira vez paga mais, refazer paga menos
- Minigame **Memória do Bicho** e **Chuva de Frutas** (canvas, com toque e teclado)
- Loja com comidas (viram item de mochila) e acessórios que aparecem vestidos no bicho; a comida comprada só faz efeito quando o jogador escolhe "Dar pro bicho" — comprar e usar são coisas separadas
- Mural de recados montados por blocos prontos
- Perfil com retrato do bicho, estatísticas e trilha de conquistas em sequência, estilo álbum de figurinha
- Home pública explicando o projeto pros responsáveis, com CTA de entrar/cadastrar
- Instalável como aplicativo no Android e no iOS
- Salvar progresso (opcional): com usuário + senha, o jogo sincroniza com o banco — sem conta, continua 100% local
- **Painel do Hostmaster (`admin.html`)**: quem hospeda o site cria mundos, sobe lições em JSON (com validação e pré-visualização antes de salvar), publica ou deixa em rascunho, vê jogadores (busca, reset de senha, exclusão de conta), métricas gerais, desempenho por pergunta de cada lição (taxa de acerto, pior primeiro) e exporta/importa backup completo do conteúdo. É o painel de quem administra o site — não é o painel do professor.
- **Editor de mapas (`admin.html` → Mapas)**: os cenários que o aluno navega são dados, não código. Dá pra subir a imagem de um mapa novo, desenhar os pontos clicáveis arrastando em cima dela (com zoom pra mirar) e escolher pra onde cada um leva. Um ponto pode abrir uma trilha, **outro mapa** (é assim que a lancha leva pro próximo cenário), uma lição específica, uma tela do jogo, ou só mostrar um recadinho de "em breve" — veja "O catálogo de links".
- **Painel do responsável (`responsavel.html`)**: entra com o mesmo usuário e senha que a criança já usa pra jogar (não é uma conta nova) e mostra nível, moedas, sequência de dias, lições concluídas e em quais perguntas ela mais erra — sem expor nome real, idade ou escola.

**Ainda não funciona:** perfil público do aluno pra compartilhar (o botão "Compartilhar" do perfil ainda é só demonstração) — veja "Próximos passos".

---

## Publicação automática

Todo `git push` na `main` publica em **pets.pedro.marketing** sozinho, via `.github/workflows/deploy.yml`.

**1. Crie uma conta de FTP só para o deploy.** No hPanel: *Websites → Gerenciar → Arquivos → Contas de FTP → Criar nova conta*. Não use a conta principal: se um dia você tirar o deploy do ar ou a chave vazar, você apaga essa conta e nada mais quebra.

**2. Descubra o caminho da pasta.** Abra o Gerenciador de Arquivos e veja onde o subdomínio mora. Costuma ser um destes:

```
./domains/pets.pedro.marketing/public_html/
./public_html/pets/
```

**3. Cadastre no GitHub** em *Settings → Secrets and variables → Actions*:

| Aba | Nome | Valor |
|---|---|---|
| Secrets | `FTP_SERVER` | o hostname que o hPanel mostra abaixo do formulário |
| Secrets | `FTP_USERNAME` | usuário da conta de FTP recém-criada |
| Secrets | `FTP_PASSWORD` | senha dela |
| **Variables** | `FTP_DIR` | o caminho do passo 2, com barra no fim |

O caminho vai em *Variables*, não em *Secrets*: não é segredo, e assim você lê o valor no log quando algo der errado.

**4. Empurre.** A primeira execução envia tudo; as seguintes mandam só o que mudou.

### Duas armadilhas que o workflow já resolve

**`dangerous-clean-slate` está desligado de propósito.** Ligado, ele apaga do servidor tudo que não existe no repositório — e o `api/config.php`, com a senha do banco, é justamente um arquivo que não existe no repositório. Uma publicação e o site cai.

**O service worker é carimbado com o hash do commit.** Sem isso, o navegador do aluno continuaria servindo a versão em cache para sempre, e você juraria que o deploy não funcionou. O workflow troca `bichoteca-v1` por `bichoteca-a1b2c3d` antes de enviar, então cada publicação invalida o cache anterior.

### Se o seu plano tiver SSH

Premium e Business têm acesso SSH, e aí o `rsync` fica mais rápido e mais seguro que FTP (chave em vez de senha). O detalhe é que a Hostinger usa a **porta 65002**, não a 22:

```yaml
- name: Enviar por rsync
  run: |
    mkdir -p ~/.ssh && echo "${{ secrets.SSH_KEY }}" > ~/.ssh/chave && chmod 600 ~/.ssh/chave
    rsync -avz --exclude='.git*' --exclude='api/config.php' \
      -e "ssh -i ~/.ssh/chave -p 65002 -o StrictHostKeyChecking=accept-new" \
      ./ ${{ secrets.SSH_USER }}@${{ secrets.SSH_HOST }}:${{ vars.FTP_DIR }}
```

---

## Mapas e o catálogo de links

Um **mapa** (tabela `cenas`) é uma imagem de fundo mais uma lista de **pontos clicáveis** (tabela `pontos`). Tudo isso é editado em `admin.html` → *Mapas*, sem tocar em código.

A posição de cada ponto é gravada em **porcentagem da imagem**, nunca em pixel: é o que faz o mesmo mapa funcionar do celular estreito ao monitor largo. O zoom do editor existe só pra você mirar melhor e não afeta nenhuma coordenada.

Cada ponto aponta pra um destino do catálogo abaixo. Esses cinco tipos são a lista fechada de links internos do jogo — quem valida é `validarPonto()` em `api/admin.php`, e um destino que não existe é recusado na hora de salvar, em vez de virar botão que não leva a nada:

| Tipo | Destino | O que acontece ao tocar |
|---|---|---|
| `mundo` | id de um mundo | abre a trilha de lições da matéria |
| `cena` | id de outro mapa | vai pro outro cenário (a lancha, o balão…) |
| `licao` | id de uma lição | abre aquela lição direto |
| `tela` | `casa`, `trilhas`, `arcade`, `loja`, `mural`, `perfil` | vai pra essa aba do jogo |
| `aviso` | texto livre | só mostra o recadinho (o "em breve") |

Duas proteções contra link quebrado, em camadas:

1. **Ao salvar** (`api/admin.php`): mundo/cena/lição precisam existir; `tela` só aceita nome de tela que existe de verdade em `app.html`.
2. **Ao servir** (`api/conteudo.php`): ponto cujo destino virou rascunho ou foi apagado depois é omitido, então a criança nunca vê o botão morto.

Uma cena é marcada como **inicial** — é a que abre quando o aluno toca em *Trilhas*. Só uma pode ser inicial por vez. O botão **Voltar** desfaz um passo do caminho percorrido entre mapas (ilha → porto → *Voltar* volta pra ilha); na cena inicial, ele sai pra casa.

Trocar de mapa tem uma transição animada em três tempos (`viajarPraCena` no `app.html`): uma bolinha nasce **exatamente no ponto que o aluno tocou** e cresce até cobrir a tela (íris via `clip-path`), o bicho dele salta pra dentro de um anel girando com o nome do destino e os pontinhos entrando em cascata, e a íris fecha revelando o mapa novo. ~1,6s.

A íris só fecha **depois que a imagem do mapa terminou de carregar** — ou seja, a transição também faz o papel de tela de carregamento, e nunca se vê um mapa pela metade. Tem teto de 1,8s pra imagem pesada não travar o jogo. Quem usa `prefers-reduced-motion` no sistema recebe um corte rápido (~0,5s) em vez da animação: os tempos de CSS e de JS saem do mesmo lugar (`tempoViagem`), senão o modo reduzido acaba ficando mais **lento** que o normal.

> ⚠️ **A imagem que você sobe pelo painel fica só no servidor** (`assets/cenas/`), fora do Git — por isso `assets/cenas/*` está no `.gitignore`. Duas consequências: o `dangerous-clean-slate` do deploy tem que continuar desligado (agora por dois motivos, não só pelo `api/config.php`), e essa pasta precisa entrar no seu backup, porque o "Baixar backup" do painel exporta o *desenho* dos mapas (posições e destinos), não os bytes das imagens.

---

## Decisões de projeto

**Nada de estética de dashboard.** Sem vidro fosco, sem degradê roxo-azul, sem sombra difusa. O visual é adesivo de caderno: contorno grosso cor de tinta, sombra sólida deslocada, papel creme, tipografia arredondada. Botão afunda quando aperta, como brinquedo.

**O bicho nunca morre.** Fome e alegria caem devagar e param no zero. Nenhum Tamagotchi culpado esperando a criança na segunda-feira. A vontade de voltar vem do jogo, não do medo de perder.

**Comentário sem caixa de texto.** O mural monta recados a partir de blocos prontos: `Oi, gente!` + `terminei a trilha de História` + `🎉`. Não é limitação técnica, é o desenho de segurança mais importante do projeto. Sem texto livre não existe link, telefone, combinação de encontro nem aliciamento — e some a necessidade de moderação humana 24h, que é o que inviabiliza plataforma infantil pequena. Se um dia liberar texto livre, ele precisa de fila de moderação **antes** de publicar, nunca depois.

**Apelido, nunca nome.** Sem e-mail, sem foto, sem idade, sem escola, sem cidade. O login é usuário (o próprio apelido de bicho) + senha — igual a qualquer jogo, sem PIN numérico. É menos restrito que a versão anterior (que usava só PIN de 4 dígitos por causa da LGPD, art. 14: dado de criança exige consentimento específico do responsável), mas continua sem coletar nenhum dado que identifique a criança de verdade: nenhum campo pede nome, e-mail, idade ou qualquer informação além do apelido escolhido e da senha.

---

## Ligando o back-end (opcional)

No hPanel, crie o banco em *Bancos de dados MySQL*. Depois:

```bash
cp api/config.example.php api/config.php   # preencha host/banco/usuário/senha do hPanel
                                            # e defina ADMIN_USUARIO/ADMIN_SENHA (conta do painel)
```

Abra `seusite.com/api/install.php` no navegador uma vez. Ele:

1. cria as tabelas `jogadores`, `admins`, `mundos`, `licoes`, `respostas`, `cenas` e `pontos` (é seguro rodar de novo — nunca apaga o que já existe, então rode também depois de atualizar o código, pra ganhar tabela nova);
2. cria (ou atualiza) a conta do painel a partir de `ADMIN_USUARIO`/`ADMIN_SENHA` — **pra trocar a senha do painel depois, é só editar essas duas constantes em `config.php` e abrir `install.php` de novo**;
3. semeia o conteúdo de exemplo (`api/seed-conteudo.json`) só se o banco de mundos estiver vazio — depois da 1ª vez, quem manda é o painel;
4. cria a cena inicial "Ilha do saber" com os 13 pontos clicáveis que antes eram fixos no código — só se ainda não houver nenhuma cena. Assim quem já tem o jogo no ar não perde o mapa e já começa com algo editável.

Pode deixar o `install.php` no servidor (ele nunca sobrescreve conteúdo já existente) ou apagar e subir de novo quando precisar resetar a senha do painel.

Com o banco pronto:

- **`app.html`** já está ligado: a tela de onboarding tem um campo de senha opcional — em branco, o jogo continua 100% local igual antes; preenchido, `entrarNoServidor()`/`carregarDoServidor()`/`salvarNoServidor()` (fim do `app.html`) cuidam de criar a conta, restaurar progresso salvo e sincronizar a cada ação relevante. O conteúdo (mundos e lições) também passa a vir do banco via `api/conteudo.php` — sem banco ou com ele fora do ar, cai de volta no conteúdo de exemplo embutido no próprio arquivo.
- **`admin.html`** é o painel do Hostmaster: acesse `seusite.com/admin.html` e entre com `ADMIN_USUARIO`/`ADMIN_SENHA`. De lá dá pra desenhar mapas, criar mundos, subir lições em JSON (com validação e pré-visualização antes de gravar), gerenciar jogadores e fazer backup/restauração do conteúdo. O formato do JSON de cada lição é o mesmo de `api/seed-conteudo.json` — o painel tem um botão "usar exemplo" e uma lista dos tipos de bloco aceitos.

> **Moedas e XP de lições feitas via banco já são conferidas no servidor.** Quando uma lição é salva pelo painel, o gabarito (`certa:` de cada bloco `pergunta`) vai junto pra tabela `licoes`, e `api/estado.php` credita moedas/XP comparando com esse gabarito — não com o que o navegador do aluno diz que ele acertou. Sem banco (modo 100% local) o cálculo continua no navegador, como antes.

---

## Próximos passos

1. **Mais conteúdo, mais rápido** — agora que subir lição é upload de JSON validado pelo painel (sem editar código nem fazer deploy), o gargalo passa a ser só escrever o conteúdo. Ancorar nas habilidades da BNCC (`EF08HI01` e afins) no `titulo`/`serie` de cada lição organiza o currículo e vira argumento de venda
2. **Login separado pro responsável** — hoje `responsavel.html` usa o mesmo usuário/senha do aluno (simples, sem conta nova); quando fizer sentido, dá pra evoluir pra um convite/código que o aluno gera, sem precisar dividir a senha do jogo
3. **Lojas de aplicativo** — o mesmo código entra num invólucro (Capacitor ou Bubblewrap/TWA) e sobe na Play Store sem reescrever nada. Foi por isso que já saiu como PWA

VPS e Node só quando o número de alunos justificar. Enquanto for protótipo e piloto, hospedagem compartilhada com PHP dá conta com folga.
