# Instruções do projeto

## Deploy automático — sempre suba pro servidor

Depois de implementar o que o Pedro pediu e confirmar que funciona (teste
manual, screenshot, o que for razoável pro tamanho da mudança), mescle a
branch de trabalho na `main` e faça `git push origin main` **sem perguntar
antes** — isso já dispara o deploy pra `pets.pedro.marketing` via
`.github/workflows/deploy.yml` (veja o README, seção "Publicação
automática"). Essa autorização já vale de forma permanente, não é só
"dessa vez".

Depois do push, confira se o workflow "Publicar na Hostinger" terminou com
`conclusion: success` (via `mcp__github__actions_get` / `actions_list`) antes
de avisar o Pedro que subiu — não é só disparar e assumir que deu certo.

Continue pedindo confirmação antes de ações destrutivas que essa instrução
não cobre (ex.: force-push, apagar branch, mexer em segredo/credencial) —
isso continua exigindo confirmação normal, como qualquer outro projeto.
