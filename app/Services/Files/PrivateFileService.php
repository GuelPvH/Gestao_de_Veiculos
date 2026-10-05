<?php

namespace App\Services\Files;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PrivateFileService
{
    /**
     * Persiste o arquivo no disco privado e os metadados; o vínculo opcional participa da mesma transação.
     * Não use o callback para procedures que iniciam sua própria transação.
     */
    /** Arquiva apenas um upload próprio sem referências; preserva metadados para auditoria. */
    public function discardUnlinked(int $arquivoId): bool
    {
        $usuario = Auth::id();
        abort_unless($usuario !== null, 401);
        $chave = DB::transaction(function () use ($arquivoId, $usuario): ?string {
            $arquivo = DB::table('arquivos')->where('id', $arquivoId)->where('enviado_por', (int) $usuario)->lockForUpdate()->first(['chave_armazenamento', 'situacao']);
            if ($arquivo === null || $arquivo->situacao !== 'disponivel') {
                return null;
            }
            foreach ([
                ['configuracao_sistema', 'logo_arquivo_id'],
                ['motoristas', 'cnh_arquivo_id'],
                ['documentos_veiculo', 'arquivo_id'],
                ['pagamentos_despesa', 'comprovante_arquivo_id'],
                ['multa_comprovantes', 'arquivo_id'],
                ['exportacoes', 'arquivo_id'],
                ['anexos', 'arquivo_id'],
            ] as [$tabela, $coluna]) {
                if (DB::table($tabela)->where($coluna, $arquivoId)->exists()) {
                    return null;
                }
            }
            DB::table('arquivos')->where('id', $arquivoId)->update(['situacao' => 'arquivado']);

            return $arquivo->chave_armazenamento;
        });
        if ($chave === null) {
            return false;
        }
        Storage::disk('local')->delete($chave);

        return true;
    }

    public function store(UploadedFile $arquivo, ?Closure $associar = null): int
    {
        $usuario = Auth::id();
        abort_unless($usuario !== null, 401);
        $tipo = $arquivo->getMimeType();
        $extensao = ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg'][$tipo] ?? null;
        $tamanho = $arquivo->getSize();
        $origem = $arquivo->getRealPath();
        if (! $arquivo->isValid() || $extensao === null || ! is_int($tamanho) || $tamanho < 1 || $tamanho > 10 * 1024 * 1024 || ! is_string($origem)) {
            throw new RuntimeException('Arquivo inválido para armazenamento privado.');
        }
        $hash = hash_file('sha256', $origem, true);
        if ($hash === false) {
            throw new RuntimeException('Não foi possível calcular o hash do anexo.');
        }
        $nome = basename(str_replace(chr(92), '/', $arquivo->getClientOriginalName()));
        $nome = trim(preg_replace('/[[:cntrl:]]/u', '', $nome) ?? '');
        $nome = mb_strcut($nome === '' ? 'anexo.'.$extensao : $nome, 0, 255, 'UTF-8');
        $chave = Storage::disk('local')->putFileAs('anexos', $arquivo, Str::random(40).'.'.$extensao);
        if (! is_string($chave)) {
            throw new RuntimeException('Não foi possível armazenar o anexo.');
        }

        try {
            return DB::transaction(function () use ($usuario, $chave, $nome, $tipo, $tamanho, $hash, $associar): int {
                $id = (int) DB::table('arquivos')->insertGetId([
                    'enviado_por' => (int) $usuario,
                    'chave_armazenamento' => $chave,
                    'nome_original' => $nome,
                    'tipo_mime' => $tipo,
                    'tamanho_bytes' => $tamanho,
                    'sha256' => $hash,
                    'situacao' => 'disponivel',
                ]);
                if ($associar !== null) {
                    $associar($id);
                }

                return $id;
            });
        } catch (Throwable $erro) {
            Storage::disk('local')->delete($chave);
            throw $erro;
        }
    }
}
