<?php

namespace App\Services\Read;

use App\Services\Authorization\AccessContext;
use Illuminate\Support\Facades\DB;

class ReferenceReadRepository
{
    public function __construct(private AccessContext $acesso) {}

    public function vehicles(): array
    {
        $nivel = $this->acesso->level('frota', 'selecionar');
        if ($nivel === 0) {
            return [];
        }
        $consulta = DB::table('vw_frota')->where('situacao_cadastro', 'ativo');
        // Uma referência no pedido próprio não estabelece propriedade do veículo.
        // Para próprios/unidade, a seleção mínima fica limitada à unidade do vínculo.
        if ($nivel < 3) {
            $consulta->where('unidade_id', $this->acesso->link()->unidade_id);
        }

        return $consulta->orderBy('nome')->limit(100)->get(['id', 'nome', 'placa', 'capacidade'])->mapWithKeys(fn ($veiculo) => [$veiculo->id => $veiculo->placa.' · '.$veiculo->nome.' · '.$veiculo->capacidade.' lugares'])->all();
    }
}
