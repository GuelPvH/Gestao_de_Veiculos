<?php

return [
    'requests' => [
        'title' => 'Solicitações', 'module' => 'solicitacoes', 'icon' => 'clipboard-list', 'url' => 'solicitacoes',
        'table' => 'vw_solicitacoes_atuais', 'owner' => 'r.solicitante_id', 'unit' => 'r.unidade_id', 'date' => 'r.saida_prevista',
        'columns' => ['protocolo' => ['r.protocolo', 'Protocolo'], 'destino' => ['r.destino', 'Destino'], 'saida_prevista' => ['r.saida_prevista', 'Saída prevista', 'datetime'], 'situacao' => ['r.situacao', 'Situação']],
        'details' => ['finalidade' => ['r.finalidade', 'Finalidade'], 'origem' => ['r.origem', 'Origem'], 'retorno_previsto' => ['r.retorno_previsto', 'Retorno previsto', 'datetime'], 'passageiros' => ['r.quantidade_passageiros', 'Passageiros'], 'solicitante' => ['r.solicitante', 'Solicitante'], 'unidade' => ['r.unidade', 'Unidade'], 'trajeto' => ['r.trajeto_planejado', 'Trajeto planejado']],
        'states' => ['rascunho', 'aguardando_analise', 'ajustes_solicitados', 'aprovada', 'negada', 'cancelada'],
        'actions' => ['edit' => ['Editar solicitação', 'editar'], 'send' => ['Revisar envio', 'enviar'], 'approve' => ['Aprovar solicitação', 'aprovar'], 'deny' => ['Negar solicitação', 'negar'], 'adjust' => ['Solicitar ajustes', 'solicitar_ajustes'], 'cancel' => ['Cancelar solicitação', 'cancelar']],
        'create' => true,
    ],
    'trips' => [
        'title' => 'Viagens', 'module' => 'viagens', 'icon' => 'route', 'url' => 'viagens',
        'table' => 'vw_viagens_detalhadas', 'owner' => 'r.solicitante_id', 'unit' => 'r.unidade_id', 'date' => 'r.saida_prevista',
        'columns' => ['protocolo' => ['r.protocolo', 'Protocolo'], 'veiculo' => ['r.veiculo', 'Veículo'], 'destino' => ['r.destino', 'Destino'], 'situacao' => ['r.situacao', 'Situação']],
        'details' => ['placa' => ['r.placa', 'Placa'], 'motorista' => ['r.motorista', 'Motorista'], 'origem' => ['r.origem', 'Origem'], 'saida_prevista' => ['r.saida_prevista', 'Saída prevista', 'datetime'], 'retorno_previsto' => ['r.retorno_previsto', 'Retorno previsto', 'datetime'], 'saida_real' => ['r.saida_real', 'Saída registrada', 'datetime'], 'retorno_real' => ['r.retorno_real', 'Retorno registrado', 'datetime'], 'quilometragem_saida' => ['r.quilometragem_saida', 'Km de saída'], 'quilometragem_retorno' => ['r.quilometragem_retorno', 'Km de retorno'], 'distancia' => ['r.distancia_real_km', 'Distância percorrida']],
        'states' => ['programada', 'em_andamento', 'concluida', 'cancelada'],
        'actions' => ['departure' => ['Registrar saída', 'registrar_saida'], 'occurrence' => ['Registrar ocorrência', 'registrar_ocorrencia'], 'return' => ['Registrar retorno', 'registrar_retorno'], 'cancel' => ['Cancelar viagem', 'cancelar']],
    ],
    'fines' => [
        'title' => 'Multas', 'module' => 'multas', 'icon' => 'file-text', 'url' => 'multas',
        'table' => 'vw_multas_detalhadas', 'owner' => 'r.responsavel_id', 'unit' => 'r.unidade_id', 'date' => 'r.ocorrido_em',
        'columns' => ['protocolo' => ['r.protocolo', 'Protocolo'], 'placa' => ['r.placa', 'Placa'], 'valor' => ['r.valor', 'Valor', 'money', 'ver_valores'], 'situacao' => ['r.situacao', 'Situação']],
        'details' => ['descricao' => ['r.descricao', 'Descrição'], 'numero_auto' => ['r.numero_auto', 'Número do auto'], 'orgao' => ['r.orgao_autuador', 'Órgão autuador'], 'ocorrido_em' => ['r.ocorrido_em', 'Ocorrência', 'datetime'], 'data_vencimento' => ['r.data_vencimento', 'Vencimento', 'date'], 'responsavel' => ['r.responsavel', 'Responsável']],
        'states' => ['sem_responsavel', 'aguardando_comprovante', 'em_conferencia', 'quitada', 'contestada', 'cancelada'],
        'actions' => ['proof' => ['Enviar comprovante', 'enviar_comprovante'], 'dispute' => ['Contestar multa', 'contestar']],
    ],
    'vehicles' => [
        'title' => 'Frota', 'module' => 'frota', 'icon' => 'car-front', 'url' => 'frota',
        'table' => 'vw_frota', 'owner' => null, 'unit' => 'r.unidade_id',
        'columns' => ['placa' => ['r.placa', 'Placa'], 'nome' => ['r.nome', 'Veículo'], 'categoria' => ['r.categoria', 'Categoria'], 'situacao' => ['r.situacao_operacional', 'Situação']],
        'details' => ['marca' => ['r.marca', 'Marca'], 'modelo' => ['r.modelo', 'Modelo'], 'ano' => ['r.ano_modelo', 'Ano'], 'capacidade' => ['r.capacidade', 'Capacidade'], 'quilometragem' => ['r.quilometragem_atual', 'Quilometragem'], 'unidade' => ['r.unidade', 'Unidade']],
        'states' => ['disponivel', 'em_viagem', 'manutencao', 'indisponivel', 'baixado'],
        'actions' => ['edit' => ['Editar veículo', 'editar']], 'create' => true,
    ],
    'monitoring' => [
        'title' => 'Monitoramento', 'module' => 'rastreamento', 'icon' => 'map-pinned', 'url' => 'monitoramento',
        'table' => 'vw_monitoramento', 'id' => 'r.veiculo_id', 'owner' => null, 'unit' => 'r.unidade_id',
        'columns' => ['placa' => ['r.placa', 'Placa'], 'nome' => ['r.nome', 'Veículo'], 'situacao' => ['r.situacao_comunicacao', 'Comunicação']],
        'details' => ['capturado_em' => ['r.capturado_em', 'Última posição', 'datetime', 'ver_localizacao'], 'latitude' => ['r.latitude', 'Latitude', 'text', 'ver_localizacao'], 'longitude' => ['r.longitude', 'Longitude', 'text', 'ver_localizacao'], 'velocidade' => ['r.velocidade_kmh', 'Velocidade (km/h)', 'text', 'ver_localizacao'], 'provedor' => ['r.provedor', 'Provedor']],
        'states' => ['transmitindo', 'sem_comunicacao', 'sem_rastreador', 'rastreador_inativo'],
        'actions' => [],
    ],
];
