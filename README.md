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
app.html              o jogo em si — onboarding, trilhas, minigames, loja, mural, perfil
manifest.json         identidade do PWA (nome, ícones, cor, tela cheia; start_url aponta pro app.html)
sw.js                 service worker: funciona offline depois da 1ª visita
icone-*.png           ícones do app instalado
.htaccess             HTTPS forçado, cache e proteção das credenciais
api/
  estado.php          back-end opcional: login por usuário + senha, salvar progresso
  install.php          roda uma vez só: cria a tabela do banco sozinho
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

**Ainda não funciona:** perfil público do aluno e painel do responsável/professor — veja "Próximos passos".

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

## Decisões de projeto

**Nada de estética de dashboard.** Sem vidro fosco, sem degradê roxo-azul, sem sombra difusa. O visual é adesivo de caderno: contorno grosso cor de tinta, sombra sólida deslocada, papel creme, tipografia arredondada. Botão afunda quando aperta, como brinquedo.

**O bicho nunca morre.** Fome e alegria caem devagar e param no zero. Nenhum Tamagotchi culpado esperando a criança na segunda-feira. A vontade de voltar vem do jogo, não do medo de perder.

**Comentário sem caixa de texto.** O mural monta recados a partir de blocos prontos: `Oi, gente!` + `terminei a trilha de História` + `🎉`. Não é limitação técnica, é o desenho de segurança mais importante do projeto. Sem texto livre não existe link, telefone, combinação de encontro nem aliciamento — e some a necessidade de moderação humana 24h, que é o que inviabiliza plataforma infantil pequena. Se um dia liberar texto livre, ele precisa de fila de moderação **antes** de publicar, nunca depois.

**Apelido, nunca nome.** Sem e-mail, sem foto, sem idade, sem escola, sem cidade. O login é usuário (o próprio apelido de bicho) + senha — igual a qualquer jogo, sem PIN numérico. É menos restrito que a versão anterior (que usava só PIN de 4 dígitos por causa da LGPD, art. 14: dado de criança exige consentimento específico do responsável), mas continua sem coletar nenhum dado que identifique a criança de verdade: nenhum campo pede nome, e-mail, idade ou qualquer informação além do apelido escolhido e da senha.

---

## Ligando o back-end (opcional)

No hPanel, crie o banco em *Bancos de dados MySQL*. Depois:

```bash
cp api/config.example.php api/config.php   # e preencha com host/banco/usuário/senha do hPanel
```

**Caminho fácil:** abra `seusite.com/api/install.php` no navegador uma vez — ele cria a tabela `jogadores` sozinho (é seguro rodar mais de uma vez) — e apague o arquivo do servidor depois.

**Caminho manual:** rode isto no phpMyAdmin em vez de usar o `install.php`:

```sql
CREATE TABLE jogadores (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  apelido       VARCHAR(14)  NOT NULL UNIQUE,
  pin_hash      VARCHAR(255) NOT NULL,
  estado        JSON         NOT NULL,
  criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Com o banco pronto, o front-end já está ligado: a tela de onboarding do `app.html` tem um campo de senha opcional — em branco, o jogo continua 100% local igual antes; preenchido, `entrarNoServidor()`/`carregarDoServidor()`/`salvarNoServidor()` (fim do `app.html`) cuidam de criar a conta, restaurar progresso salvo e sincronizar a cada ação relevante (lição concluída, item comprado, item consumido, recado publicado).

> ⚠️ **Moedas e XP hoje são calculados no navegador.** Qualquer aluno com o console aberto vira milionário. Quando isso passar a valer alguma coisa (ranking, item raro), a conta precisa subir para o PHP: o cliente manda *"respondi a alternativa 2 da pergunta 3 da lição bio1"*, o servidor confere e credita.

---

## Próximos passos

1. **Painel do responsável/professor** — quem estudou o quê, quanto tempo, onde errou. É isso que faz escola pagar
2. **Conteúdo de verdade** — só `bio1` usa os blocos novos (flashcard, vídeo, cloze, caça-palavras); as outras 9 lições ainda são só leitura + quiz. Ancorar nas habilidades da BNCC (`EF08HI01` e afins) desde já organiza o currículo e vira argumento de venda
3. **Lojas de aplicativo** — o mesmo código entra num invólucro (Capacitor ou Bubblewrap/TWA) e sobe na Play Store sem reescrever nada. Foi por isso que já saiu como PWA

VPS e Node só quando o número de alunos justificar. Enquanto for protótipo e piloto, hospedagem compartilhada com PHP dá conta com folga.
