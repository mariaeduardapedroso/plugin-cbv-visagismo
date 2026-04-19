# CBV Sync CSV

Plugin WordPress complementar ao **CBV Formandos Manager** que sincroniza dados antigos de `email → CBV` a partir de um CSV embutido.

## O que faz

Para cada registro do CSV (254 formandos antigos exportados antes da invasão):

1. Busca ficha existente no CPT `clientes` pelo **email** (case-insensitive)
2. **Se encontrou:** atualiza o meta `numero_do_cbv` com o valor do CSV
3. **Se não encontrou:** cria uma nova ficha já com status **"Aprovado"** (`post_status = publish` e `_cbv_status = aprovado`), preenchendo nome, email, instagram e CBV

## Instalação

1. WordPress Admin → Plugins → Adicionar → Upload
2. Suba o ZIP do plugin
3. Ative
4. Acesse **Formandos CBV → Sincronizar CSV**

## Uso

### 1. Simular (dry-run)
Clique em **"Simular sincronização"** - nada é salvo, apenas mostra o que seria feito no log.

### 2. Executar de verdade
Clique em **"Executar sincronização"** - aplica as mudanças no banco.

### 3. Conferir o log
A tabela abaixo mostra cada decisão:
- **Atualizado** (azul) → ficha existente teve o CBV atualizado
- **Criado** (verde) → nova ficha criada com status Aprovado
- **Ignorado** (cinza) → ficha já tinha o mesmo CBV, nada a fazer
- **Erro** (vermelho) → falha ao atualizar/criar

## Dependências

- WordPress 5.0+
- Plugin **CBV Formandos Manager** ativo (para o CPT `clientes` existir)

## Estrutura

```
cbv-sync-csv/
├── cbv-sync-csv.php         # Plugin principal
├── data/
│   └── csv-data.php         # Array PHP com os 254 registros
└── README.md
```

## Segurança

- Hardcoded `ABSPATH` check nos arquivos PHP
- Nonces em todos os formulários
- Capability `manage_options` para acessar a página
