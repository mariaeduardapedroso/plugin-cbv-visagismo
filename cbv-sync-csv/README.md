# CBV Sync CSV

Plugin WordPress complementar ao **CBV Formandos Manager** que sincroniza dados de `email → CBV` a partir de um **CSV enviado pelo admin**.

## O que faz

Para cada linha do CSV enviado:

1. Busca ficha existente no CPT `clientes` pelo **email** (case-insensitive)
2. **Se encontrou:** atualiza o meta `numero_do_cbv` com o valor do CSV
3. **Se não encontrou:** cria uma nova ficha já com status **"Aprovado"** (`post_status = publish` e `_cbv_status = aprovado`), preenchendo nome, email, instagram e CBV

## Instalação

1. WordPress Admin → Plugins → Adicionar → Upload
2. Suba o ZIP do plugin
3. Ative
4. Acesse **Formandos CBV → Sincronizar CSV**

## Uso

### 1. Enviar CSV
Selecione o arquivo `.csv` e clique em **Enviar CSV**.

**Colunas aceitas** (case-insensitive, aliases reconhecidos):

| Campo | Nomes aceitos |
| --- | --- |
| Email (obrigatório) | `email`, `e-mail`, `e_mail` |
| CBV (obrigatório) | `Número do CBV`, `numero_do_cbv`, `cbv` |
| Nome (opcional) | `nome_do_aluno`, `nome do aluno`, `nome`, `name` |
| Instagram (opcional) | `instagram`, `insta` |

Colunas extras são **ignoradas** (útil quando você exporta direto do WordPress com todas as colunas).

### 2. Simular (dry-run)
Clique em **"Simular sincronização"** - nada é salvo, apenas mostra o que seria feito no log.

### 3. Executar de verdade
Clique em **"Executar sincronização"** - aplica as mudanças no banco.

### 4. Conferir o log
- **Atualizado** (azul) → ficha existente teve o CBV atualizado
- **Criado** (verde) → nova ficha criada com status Aprovado
- **Ignorado** (cinza) → ficha já tinha o mesmo CBV
- **Erro** (vermelho) → falha ao atualizar/criar

## Formatos de CSV suportados

- Separador: vírgula (`,`) ou ponto-e-vírgula (`;`) - detectado automaticamente
- Encoding: UTF-8 (com ou sem BOM)
- Primeira linha: cabeçalhos

## Dependências

- WordPress 5.0+
- Plugin **CBV Formandos Manager** ativo (para o CPT `clientes` existir)
