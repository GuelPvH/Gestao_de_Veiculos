<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;

class ProcedureRunner
{
    /**
     * @param  array<array-key, mixed>  $parametros
     * @return list<array<string, scalar|null>>
     */
    public function call(string $procedimento, array $parametros): array
    {
        abort_unless(in_array($procedimento, ['sp_criar_administrador_inicial', 'sp_criar_sessao', 'sp_trocar_perfil_sessao', 'sp_registrar_atividade', 'sp_encerrar_sessao', 'sp_expirar_sessao', 'sp_consumir_recuperacao', 'sp_marcar_notificacao', 'sp_criar_solicitacao', 'sp_criar_solicitacao_completa', 'sp_enviar_solicitacao', 'sp_aprovar_solicitacao', 'sp_decidir_solicitacao', 'sp_abrir_revisao', 'sp_cancelar_solicitacao', 'sp_registrar_saida', 'sp_registrar_retorno', 'sp_abrir_chamado', 'sp_responder_chamado', 'sp_atender_chamado', 'sp_atribuir_responsavel_multa', 'sp_enviar_comprovante_multa', 'sp_conferir_comprovante_multa', 'sp_alterar_situacao_multa', 'sp_preparar_exportacao', 'sp_enfileirar_exportacao', 'sp_criar_usuario', 'sp_vincular_perfil', 'sp_desativar_vinculo', 'sp_conceder_permissao', 'sp_duplicar_perfil', 'sp_exigir_permissao', 'sp_auditar', 'sp_pagar_despesa'], true), 500);
        $conexao = DB::connection();
        $inicio = hrtime(true);
        $sql = 'CALL '.$procedimento.'('.implode(',', array_fill(0, count($parametros), '?')).')';
        $pdo = $conexao->getPdo();
        $comando = $pdo->prepare($sql);
        try {
            foreach (array_values($parametros) as $indice => $valor) {
                if (! is_scalar($valor) && $valor !== null) {
                    throw new \UnexpectedValueException('Stored procedure parameters must be scalar.');
                }
                $comando->bindValue($indice + 1, $valor, $valor === null ? PDO::PARAM_NULL : (is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR));
            }
            $comando->execute();
            $retorno = [];
            do {
                if ($comando->columnCount() > 0) {
                    /** @var list<array<string, scalar|null>> $rows */
                    $rows = $comando->fetchAll(PDO::FETCH_ASSOC);
                    $retorno = array_merge($retorno, $rows);
                }
            } while ($comando->nextRowset());

            return $retorno;
        } finally {
            try {
                $comando->closeCursor();
            } catch (PDOException) {
            }
            // Procedure bindings can contain session tokens. Only expose SQL and timing.
            $conexao->logQuery($sql, [], (hrtime(true) - $inicio) / 1e6);
        }
    }
}
