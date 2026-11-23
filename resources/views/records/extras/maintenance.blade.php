@if ($custosManutencao !== null)
    <x-ui.panel titulo="Peças e serviços">
        <dl class="detail-grid mb-3">
            <div>
                <dt>Total de peças</dt>
                <dd>{{ $leituras->format($custosManutencao['total_pecas'], 'money') }}</dd>
            </div>
            <div>
                <dt>Total de serviços</dt>
                <dd>{{ $leituras->format($custosManutencao['total_servicos'], 'money') }}</dd>
            </div>
            <div>
                <dt>Total calculado dos itens</dt>
                <dd><strong>{{ $leituras->format($custosManutencao['total'], 'money') }}</strong></dd>
            </div>
        </dl>
        <div class="table-responsive mb-3" tabindex="0" role="region" aria-label="Peças e serviços da manutenção">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Tipo</th>
                        <th scope="col">Descrição</th>
                        <th scope="col">Quantidade</th>
                        <th scope="col">Valor unitário</th>
                        <th scope="col">Total</th>
                        @if ($podeGerenciarItens)
                            <th scope="col">Ações</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($custosManutencao['itens'] as $item)
                        <tr>
                            <td>{{ $item['tipo'] === 'peca' ? 'Peça' : 'Serviço' }}</td>
                            <td>{{ $item['descricao'] }}</td>
                            <td>{{ $item['quantidade'] }}</td>
                            <td>{{ $leituras->format($item['valor_unitario'], 'money') }}</td>
                            <td>{{ $leituras->format($item['total'], 'money') }}</td>
                            @if ($podeGerenciarItens)
                                <td>
                                    <details>
                                        <summary class="btn btn-outline-secondary btn-sm">Gerenciar</summary>
                                        <div class="mt-2 p-2 border rounded">
                                            <form
                                                method="post"
                                                action="{{ route('maintenance.perform', ['registro' => $registro->id, 'acao' => 'item-edit']) }}"
                                                class="mb-3"
                                            >
                                                @csrf
                                                <input type="hidden" name="versao" value="{{ $versaoManutencao }}" />
                                                <input type="hidden" name="item" value="{{ $item['id'] }}" />
                                                <x-forms.field
                                                    :id="'item-edit-tipo-'.$item['id']"
                                                    nome="tipo"
                                                    rotulo="Tipo"
                                                    tipo="select"
                                                    :valor="$item['tipo']"
                                                    :opcoes="['peca' => 'Peça', 'servico' => 'Serviço']"
                                                    :obrigatorio="true"
                                                />
                                                <x-forms.field
                                                    :id="'item-edit-descricao-'.$item['id']"
                                                    nome="descricao"
                                                    rotulo="Descrição"
                                                    :valor="$item['descricao']"
                                                    :obrigatorio="true"
                                                    maxlength="255"
                                                />
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <x-forms.field
                                                            :id="'item-edit-qtd-'.$item['id']"
                                                            nome="quantidade"
                                                            rotulo="Quantidade"
                                                            tipo="number"
                                                            :valor="$item['quantidade']"
                                                            :obrigatorio="true"
                                                            min="0.001"
                                                            step="0.001"
                                                        />
                                                    </div>
                                                    <div class="col-md-6">
                                                        <x-forms.field
                                                            :id="'item-edit-valor-'.$item['id']"
                                                            nome="valor_unitario"
                                                            rotulo="Valor unitário (R$)"
                                                            tipo="number"
                                                            :valor="$item['valor_unitario']"
                                                            :obrigatorio="true"
                                                            min="0"
                                                            step="0.01"
                                                        />
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    Salvar item
                                                </button>
                                            </form>
                                            <form
                                                method="post"
                                                action="{{ route('maintenance.perform', ['registro' => $registro->id, 'acao' => 'item-remove']) }}"
                                            >
                                                @csrf
                                                <input type="hidden" name="versao" value="{{ $versaoManutencao }}" />
                                                <input type="hidden" name="item" value="{{ $item['id'] }}" />
                                                <div class="form-check mb-2">
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        name="confirmar"
                                                        value="1"
                                                        id="item-remove-confirmar-{{ $item['id'] }}"
                                                        required
                                                    />
                                                    <label
                                                        class="form-check-label small"
                                                        for="item-remove-confirmar-{{ $item['id'] }}"
                                                        >Confirmo a remoção deste item.</label
                                                    >
                                                </div>
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    Remover item
                                                </button>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $podeGerenciarItens ? 6 : 5 }}">Nenhuma peça ou serviço registrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($podeGerenciarItens)
            <form
                method="post"
                action="{{ route('maintenance.perform', ['registro' => $registro->id, 'acao' => 'item-add']) }}"
                class="border-top pt-3"
            >
                @csrf
                <input type="hidden" name="versao" value="{{ $versaoManutencao }}" />
                <h3 class="h6 mb-3">Adicionar peça ou serviço</h3>
                <div class="row">
                    <div class="col-md-4">
                        <x-forms.field
                            id="item-add-tipo"
                            nome="tipo"
                            rotulo="Tipo"
                            tipo="select"
                            :opcoes="['peca' => 'Peça', 'servico' => 'Serviço']"
                            :obrigatorio="true"
                        />
                    </div>
                    <div class="col-md-8">
                        <x-forms.field
                            id="item-add-descricao"
                            nome="descricao"
                            rotulo="Descrição"
                            :obrigatorio="true"
                            maxlength="255"
                        />
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <x-forms.field
                            id="item-add-quantidade"
                            nome="quantidade"
                            rotulo="Quantidade"
                            tipo="number"
                            :obrigatorio="true"
                            min="0.001"
                            step="0.001"
                        />
                    </div>
                    <div class="col-md-6">
                        <x-forms.field
                            id="item-add-valor"
                            nome="valor_unitario"
                            rotulo="Valor unitário (R$)"
                            tipo="number"
                            :obrigatorio="true"
                            min="0"
                            step="0.01"
                        />
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Adicionar item</button>
            </form>
        @endif
    </x-ui.panel>
    <x-ui.panel titulo="Despesa vinculada">
        @if ($custosManutencao['despesa'] !== null)
            <dl class="detail-grid mb-3">
                <div>
                    <dt>Protocolo da despesa</dt>
                    <dd>
                        <a
                            href="{{ route('expenses.show', $custosManutencao['despesa']['id']) }}"
                            >{{ $custosManutencao['despesa']['protocolo'] }}</a
                        >
                    </dd>
                </div>
                <div>
                    <dt>Situação</dt>
                    <dd><x-ui.status :valor="$custosManutencao['despesa']['situacao']" /></dd>
                </div>
                <div>
                    <dt>Valor da despesa</dt>
                    <dd>{{ $leituras->format($custosManutencao['despesa']['valor'], 'money') }}</dd>
                </div>
                <div>
                    <dt>Diferença em relação aos itens</dt>
                    <dd>{{ $leituras->format($custosManutencao['despesa']['diferenca'], 'money') }}</dd>
                </div>
            </dl>
        @elseif ($podeVincularDespesa)
            <form
                method="post"
                action="{{ route('maintenance.perform', ['registro' => $registro->id, 'acao' => 'link-expense']) }}"
            >
                @csrf
                <input type="hidden" name="versao" value="{{ $versaoManutencao }}" />
                <x-forms.field
                    id="maintenance-link-expense"
                    nome="despesa_id"
                    rotulo="Despesa da categoria Manutenção"
                    tipo="select"
                    :opcoes="$despesasManutencao"
                    :obrigatorio="true"
                />
                @if ($despesasManutencao === [])
                    <x-ui.alert tom="info">
                        Não há despesa disponível da categoria Manutenção para este veículo. Você pode
                        <a href="{{ route('expenses.create') }}">registrar uma nova despesa</a> e vinculá-la depois.
                    </x-ui.alert>
                @endif
                <button type="submit" class="btn btn-primary" @disabled ($despesasManutencao === [])>
                    Vincular despesa
                </button>
            </form>
        @else
            <p class="text-body-secondary mb-0">Nenhuma despesa vinculada a esta manutenção.</p>
        @endif
    </x-ui.panel>
@endif
<x-ui.panel titulo="Pneus instalados">
    <div class="table-responsive" tabindex="0" role="region" aria-label="Pneus com rolagem">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Código</th>
                    <th scope="col">Posição</th>
                    <th scope="col">Medida</th>
                    <th scope="col">Instalação</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pneus as $pneu)
                    <tr>
                        <td>{{ $pneu->codigo }}</td>
                        <td>{{ $pneu->posicao }}</td>
                        <td>{{ $pneu->medida }}</td>
                        <td>{{ $leituras->format($pneu->instalado_em,'datetime') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Nenhum pneu instalado registrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table></div
></x-ui.panel>
