@props(['titulo' => 'Nenhum registro encontrado', 'texto' => 'Os registros autorizados aparecerão aqui.', 'descricao' => null])
<div class="empty-state"><x-ui.icon nome="clipboard-list" /><h2 class="h6 mt-3">{{ $titulo }}</h2><p class="text-body-secondary mb-0">{{ $descricao ?? $texto }}</p></div>
