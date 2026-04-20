# CBV Approval Email

Plugin WordPress complementar ao **CBV Formandos Manager** que envia email automático ao aluno quando a ficha dele é aprovada pelo admin.

## Funcionalidades

- Envio automático de email ao aprovar uma ficha (se ativado)
- Configuração de assunto, mensagem e remetente
- Variáveis no template: `{nome}`, `{email}`, `{cbv}`, `{instagram}`, `{cidade}`, `{estado}`
- Botão de envio de teste (funciona mesmo sem fichas cadastradas)
- Log dos últimos 100 emails enviados
- 5 camadas de segurança para evitar disparos indesejados

## 5 camadas de segurança

| # | Camada | O que faz |
| --- | --- | --- |
| 1 | Desativado por padrão | Nenhum email é enviado até você ativar manualmente |
| 2 | Só transições reais | Só dispara quando `_cbv_status` muda para `aprovado` (não em saves comuns) |
| 3 | Exclui CSV Sync | Fichas com `_cbv_source = csv_sync` nunca recebem email |
| 4 | Só admin | Só dispara em ações do painel WordPress (não cron/API) |
| 5 | Timestamp ativação | Registra quando foi ativado pela primeira vez |

## Instalação

1. **Instale o plugin CBV Formandos Manager primeiro** (dependência)
2. WordPress Admin → Plugins → Adicionar → Upload
3. Suba o ZIP deste plugin
4. Ative
5. Acesse **Formandos CBV → Email de Aprovação**

## Configuração inicial

1. Configure o **email do remetente** (ex: `resposta-formulario@visageducation.com`)
2. Ajuste o **nome do remetente** e o **assunto**
3. Personalize a **mensagem** usando as variáveis disponíveis
4. Clique em **Salvar alterações** (ainda sem enviar nada)
5. Teste com **"Enviar email de teste"** - insira seu email pessoal
6. Só depois do teste funcionar, marque **"Ativar envio automático"** e salve

## Dependências

- WordPress 5.0+
- Plugin **CBV Formandos Manager** (para o CPT `clientes`)
- Recomendado: **WP Mail SMTP** configurado, para garantir entrega dos emails
