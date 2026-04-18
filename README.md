# CBV Formandos Manager

Plugin WordPress para gerenciar o cadastro e aprovação de formandos da Formação Barbeiro Visagista (CBV) da Visage Education.

## Funcionalidades

- **CPT `clientes`** com meta fields: nome, email, whatsapp, país, estado, cidade, instagram, CBV, certificado
- **Taxonomias** `cbv_pais`, `cbv_estado`, `cbv_cidade` para uso com JetSmartFilters
- **Integração WPForms** → ao submeter o formulário "Cadastro CBV" (ID 5304), cria automaticamente uma ficha pendente
- **Fluxo de aprovação**: admin edita a ficha, preenche o número CBV e aprova para publicar
- **Validação** que impede publicação sem número CBV
- **Listagem customizada** com colunas, filtros por status/estado, ações rápidas (Aprovar/Rejeitar) e Quick Edit minimalista (só CBV + Status)
- **Widget no Dashboard** com resumo rápido (total / aprovados / pendentes / rejeitados)
- **Upload de certificado** (attachment) + fallback para URL externa vinda do WPForms

## Instalação

1. Baixar o ZIP (ou compactar a pasta)
2. WordPress Admin → Plugins → Adicionar → Upload
3. Ativar

## Configuração necessária

- **Formulário WPForms** com ID `5304` ("Cadastro CBV")
- **JetSmartFilters** apontando para as taxonomias `cbv_pais`, `cbv_estado`, `cbv_cidade`
- **JetEngine Listing "Modelo clientes"** usando Custom Field dynamic tags com os meta keys: `nome_do_aluno`, `email`, `nome_da_cidade`, `nome_do_estado`, `instagram`, `numero_do_cbv`

## Meta keys

| Campo | Meta key |
| --- | --- |
| Nome do Aluno | `nome_do_aluno` |
| E-mail | `email` |
| WhatsApp | `whatsapp` |
| País | `pais` |
| Estado | `nome_do_estado` |
| Cidade | `nome_da_cidade` |
| Instagram | `instagram` |
| Número do CBV | `numero_do_cbv` |
| Certificado (attachment ID) | `certificado` |
| Certificado (URL externa) | `certificado_url` |
| Status interno | `_cbv_status` (pendente / aprovado / rejeitado) |
