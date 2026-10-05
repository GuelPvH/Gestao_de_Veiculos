@props(['rotulo', 'valor', 'nota' => ''])
<div class="card metric-card"><div class="card-body">
    <div class="metric-label">{{ $rotulo }}</div>
    <div class="metric-value">{{ $valor }}</div>
    <p class="small text-body-secondary mb-0">{{ $nota }}</p>
</div></div>
