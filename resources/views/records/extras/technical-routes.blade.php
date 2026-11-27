<x-ui.panel titulo="Permissões exigidas pela rota">
    @if ($requisitosRota->isNotEmpty())
        <ul class="list-unstyled mb-3">
            @foreach ($requisitosRota as $regra)
                <li class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-1 border-bottom">
                    <span>{{ $regra->modulo_codigo }} / {{ $regra->acao_codigo }}</span>
                    @if ($podeEditarRequisitosRota ?? false)
                        <form method="post" action="{{ route('technical-routes.permissions.destroy', $registro->id) }}">
                            @csrf
                            <input type="hidden" name="modulo_codigo" value="{{ $regra->modulo_codigo }}" />
                            <input type="hidden" name="acao_codigo" value="{{ $regra->acao_codigo }}" />
                            <button type="submit" class="btn btn-outline-danger btn-sm">Remover</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
        <p class="mb-3">Para conceder o acesso, abra o perfil, selecione Conceder permissão e escolha esta rota e o alcance desejado.</p>
    @else
        <p class="mb-3">As permissões são verificadas por ação no módulo desta rota.</p>
    @endif

    @if (($podeEditarRequisitosRota ?? false) && ! empty($opcoesModuloAcoes))
        <hr class="my-3" />
        <form
            method="post"
            action="{{ route('technical-routes.permissions.store', $registro->id) }}"
            class="row g-2 align-items-end"
        >
            @csrf
            <div class="col-md-8">
                <x-forms.field
                    nome="combinacao"
                    rotulo="Adicionar requisito (módulo / ação)"
                    tipo="select"
                    :opcoes="$opcoesModuloAcoes"
                    :obrigatorio="true"
                    placeholder="Selecione módulo e ação"
                />
            </div>
            <div class="col-md-4 mb-3">
                <button type="submit" class="btn btn-outline-primary w-100">Vincular permissão</button>
            </div>
        </form>
    @endif
</x-ui.panel>
