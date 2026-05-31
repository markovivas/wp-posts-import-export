# WP Posts Import Export

Contribuidores: anomalyco
Tags: export, import, posts, migração, wordpress
Requer pelo menos: 6.0
Testado até: 6.7
Requer PHP: 8.1
Versão estável: 1.0.0
Licença: GPL-2.0+
URI da Licença: http://www.gnu.org/licenses/gpl-2.0.txt

Exporte e importe posts do WordPress preservando todo o conteúdo, imagens destacadas, categorias, tags, autores e datas de publicação.

## Descrição

WP Posts Import Export permite exportar posts do WordPress para um arquivo ZIP contendo um JSON estruturado e todas as imagens destacadas, e importá-los de volta em qualquer instalação WordPress com preservação completa dos dados.

### Funcionalidades

- Exportar posts com filtros por categoria e intervalo de datas
- Preserva título, conteúdo, slug, data (local e GMT), status, autor, categorias e tags
- Baixa e renomeia automaticamente imagens destacadas usando a data do post (formato dd-mm-YYYY)
- Lida com datas duplicadas usando sufixos incrementais
- Importa posts preservando as datas originais de publicação
- Cria categorias e tags ausentes durante a importação
- Usa o administrador atual quando o autor original não existe
- Importa imagens destacadas para a Biblioteca de Mídia do WordPress
- Barra de progresso com relatório detalhado de importação
- Suporte completo a i18n
- Compatível com PHP 8.1+
- Compatível com Multisite

## Instalação

1. Envie a pasta `wp-posts-import-export` para `/wp-content/plugins/`
2. Ative o plugin através da tela 'Plugins' no WordPress
3. Vá em Ferramentas > Importar/Exportar Posts para usar o plugin

## Uso

### Exportando

1. Vá em Ferramentas > Importar/Exportar Posts
2. Selecione a aba "Exportar"
3. Opcionalmente, filtre por categoria ou intervalo de datas
4. Clique em "Exportar Posts"
5. Baixe o arquivo ZIP gerado

### Importando

1. Vá em Ferramentas > Importar/Exportar Posts
2. Selecione a aba "Importar"
3. Envie o arquivo ZIP
4. Clique em "Importar"
5. Aguarde a barra de progresso ser concluída
6. Revise o relatório de importação

## Perguntas Frequentes

### Este plugin suporta tipos de post personalizados?

Atualmente ele exporta/importa apenas posts padrão do WordPress. Suporte para tipos de post personalizados pode ser adicionado em versões futuras.

### Anexos (além de imagens destacadas) são exportados?

Não, apenas imagens destacadas são exportadas e importadas. Outras mídias anexadas ao conteúdo do post não são incluídas.

### O que acontece se um autor não existir no site de destino?

O post é atribuído ao usuário administrador atual.

### O plugin é compatível com Multisite?

Sim, o plugin funciona em instalações WordPress Multisite.

## Changelog

### 1.0.0

- Lançamento inicial

## Aviso de Atualização

### 1.0.0

Lançamento inicial.
