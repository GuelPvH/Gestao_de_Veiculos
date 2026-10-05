<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;

class ProcedureRunner
{
    /** @return list<array<string,mixed>> */
    public function call(string $procedimento, array $parametros): array
    {
        abort_unless(in_array($procedimento, ['sp_criar_administrador_inicial', 'sp_criar_sessao', 'sp_trocar_perfil_sessao', 'sp_registrar_atividade', 'sp_encerrar_sessao', 'sp_consumir_recuperacao'], true), 500);
        $pdo = DB::connection()->getPdo();
        $comando = $pdo->prepare('CALL '.$procedimento.'('.implode(',', array_fill(0, count($parametros), '?')).')');
        try {
            foreach (array_values($parametros) as $indice => $valor) {
                $comando->bindValue($indice + 1, $valor, $valor === null ? PDO::PARAM_NULL : (is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR));
            }
            $comando->execute();
            $retorno = [];
            do {
                if ($comando->columnCount() > 0) {
                    $retorno = array_merge($retorno, $comando->fetchAll(PDO::FETCH_ASSOC));
                }
            } while ($comando->nextRowset());

            return $retorno;
        } finally {
            try {
                $comando->closeCursor();
            } catch (PDOException) {
            }
        }
    }
}
