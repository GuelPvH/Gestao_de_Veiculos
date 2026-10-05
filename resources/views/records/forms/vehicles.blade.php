@if($acao === 'create' || $acao === 'edit')
    @if($acao === 'create')
        <x-forms.field nome="unidade_id" rotulo="Unidade responsável" tipo="select" :valor="app(\App\Services\Authorization\AccessContext::class)->link()->unidade_id" :opcoes="$unidadesDisponiveis ?? []" :obrigatorio="true" />
    @else
        <p class="text-body-secondary">A unidade responsável é mantida neste cadastro. A transferência exige um fluxo próprio para preservar o alcance do histórico.</p>
    @endif
    <div class="row"><div class="col-md-6"><x-forms.field nome="categoria_id" rotulo="Categoria" tipo="select" :valor="$registro->categoria_id ?? ''" :opcoes="$categoriasDisponiveis ?? []" :obrigatorio="true" /></div><div class="col-md-6"><x-forms.field nome="nome" rotulo="Nome do veículo" :valor="$registro->nome ?? ''" :obrigatorio="true" maxlength="120" /></div></div>
    <div class="row"><div class="col-md-6"><x-forms.field nome="placa" rotulo="Placa" :valor="$registro->placa ?? ''" :obrigatorio="true" maxlength="10" pattern="[A-Za-z]{3}[- ]?[0-9][A-Za-z0-9][0-9]{2}" nota="A placa será gravada sem espaço ou hífen." /></div><div class="col-md-6"><x-forms.field nome="renavam" rotulo="RENAVAM" :valor="$registro->renavam ?? ''" maxlength="20" /></div></div>
    <div class="row"><div class="col-md-6"><x-forms.field nome="chassi" rotulo="Chassi" :valor="$registro->chassi ?? ''" maxlength="30" /></div><div class="col-md-6"><x-forms.field nome="marca" rotulo="Marca" :valor="$registro->marca ?? ''" maxlength="80" /></div></div>
    <div class="row"><div class="col-md-6"><x-forms.field nome="modelo" rotulo="Modelo" :valor="$registro->modelo ?? ''" maxlength="80" /></div><div class="col-md-3"><x-forms.field nome="ano_fabricacao" rotulo="Ano de fabricação" tipo="number" :valor="$registro->ano_fabricacao ?? ''" min="1900" :max="date('Y') + 1" /></div><div class="col-md-3"><x-forms.field nome="ano_modelo" rotulo="Ano do modelo" tipo="number" :valor="$registro->ano_modelo ?? ''" min="1900" :max="date('Y') + 2" /></div></div>
    <div class="row"><div class="col-md-6"><x-forms.field nome="capacidade" rotulo="Capacidade de pessoas" tipo="number" :valor="$registro->capacidade ?? ''" :obrigatorio="true" min="1" max="100" step="1" /></div><div class="col-md-6"><x-forms.field nome="quilometragem_atual" rotulo="Quilometragem atual" tipo="number" :valor="$registro->quilometragem_atual ?? 0" :obrigatorio="true" min="0" step="0.1" /></div></div>
    <x-forms.field nome="situacao_cadastro" rotulo="Situação cadastral" tipo="select" :valor="$registro->situacao_cadastro ?? 'ativo'" :obrigatorio="true" :opcoes="$acao === 'create' ? ['ativo'=>'Ativo','inativo'=>'Inativo'] : ['ativo'=>'Ativo','inativo'=>'Inativo','baixado'=>'Baixado']" nota="A disponibilidade operacional depende também de viagens e reservas." />
    <x-forms.field nome="observacoes" rotulo="Observações" tipo="textarea" :valor="$registro->observacoes ?? ''" maxlength="10000" />
    @if($acao === 'edit')
        <x-forms.field nome="justificativa" rotulo="Motivo da alteração" tipo="textarea" :obrigatorio="true" maxlength="1000" />
        <x-forms.field nome="confirmacao_baixa" rotulo="Confirmação de baixa" maxlength="6" nota="Se mudar a situação para Baixado, digite BAIXAR. A baixa não pode ser revertida neste fluxo." />
    @endif
@elseif($acao === 'block')
    <x-ui.alert tom="info">O bloqueio impede novas reservas de viagem neste período. Uma manutenção vinculada deve ser aberta na área financeira.</x-ui.alert>
    <x-forms.field nome="tipo" rotulo="Tipo de bloqueio" tipo="select" :obrigatorio="true" :opcoes="['indisponibilidade'=>'Indisponibilidade','manutencao'=>'Manutenção avulsa']" />
    <div class="row"><div class="col-md-6"><x-forms.field nome="inicio" rotulo="Início" tipo="datetime-local" :obrigatorio="true" data-date-start /></div><div class="col-md-6"><x-forms.field nome="fim" rotulo="Fim" tipo="datetime-local" :obrigatorio="true" data-date-end /></div></div>
    <x-forms.field nome="descricao" rotulo="Motivo do bloqueio" tipo="textarea" :obrigatorio="true" maxlength="500" />
@elseif($acao === 'release')
    <x-ui.alert tom="warning">A liberação encerra o bloqueio selecionado e permite novas reservas no período. Reservas de viagem e manutenções vinculadas são encerradas nos respectivos fluxos.</x-ui.alert>
    <x-forms.field nome="reserva_id" rotulo="Bloqueio manual ativo" tipo="select" :opcoes="$reservasDisponiveis ?? []" :obrigatorio="true" />
    <x-forms.field nome="confirmacao" rotulo="Confirmação" tipo="text" :obrigatorio="true" pattern="liberar" nota="Digite liberar para confirmar." />
@endif
