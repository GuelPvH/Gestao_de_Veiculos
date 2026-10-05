# Componentes e aparência

Blade em `resources/views/components`: layouts base/authenticated; navigation sidebar/header/breadcrumb/pagination; ui logo/icon/title/panel/metric/status/alert/feedback/empty/loading/modal; forms field/password/review; tables records; fleet history/attachments. Arquivos e componentes em inglês; props, variáveis de aplicação e tokens CSS em português. Exemplo: `<x-ui.modal id="review" titulo="Revisar">...</x-ui.modal>`.

Bootstrap 5.3 fornece forms, cards, modal, dropdown, offcanvas e pagination. Vite carrega um único CSS/JS. Não há Tailwind, React, jQuery ou CDN duplicado. Tema via `data-bs-theme`, tokens em theme.css e preferência visual local; identidade e permissões não ficam em localStorage.

A logo original do Figma é preservada em 16 PNGs, sem redesenho ou alteração dos bytes. As tiras recompõem a imagem integral com a geometria da instância aprovada, incluindo as margens da imagem original. A logo visível é proporcional, 200×44,994; superfície branca também no modo escuro. Fonte Figma: WBYS4p49ZYemTXuEsCoBHt, 26:13189; apresentação 56:37316. Fonte tipográfica Inter carregada localmente.

O endpoint temporário de ícones do Figma retornou Site Unavailable. Reutilizados os SVGs Lucide já exportados no protótipo (sem editar os paths ou dimensões de raiz), disponíveis em public/icons. A revisão visual registra essa adaptação, sem tratar respostas HTML como assets válidos.

Shell com sidebar 248 px, offcanvas abaixo de 992 px, cartões, tabelas com rolagem interna e cartões móveis. O breadcrumb usa Painel e a paginação calcula intervalos reais, preservando filtros. Não há monogramas decorativos, contas demonstrativas, menu Início ou atalho Ajuda. Datas operacionais podem usar a palavra início.

JS centraliza tema, senha visível, loading das requisições reais, validação e navegação do wizard, resumo seguro via textContent, modal de revisão, descarte e foco. Confirmar operações de negócio permanece desabilitado: nenhuma gravação falsa ou mensagem de sucesso é produzida. Bootstrap controla teclado, Escape e foco dos overlays.
