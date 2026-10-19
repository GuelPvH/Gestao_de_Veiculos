<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property int $unidade_id
 * @property string $identificador
 * @property string $nome
 * @property string $senha_hash
 * @property string|null $email
 * @property string|null $telefone
 * @property bool $ativo
 * @property string $estado_identidade
 * @property int $versao_seguranca
 * @property CarbonImmutable|null $senha_temporaria_revogada_em
 * @property CarbonImmutable|null $senha_temporaria_expira_em
 * @property CarbonImmutable|null $email_verificado_em
 * @property string|null $email_pendente
 * @property bool $deve_trocar_senha
 */
class User extends Authenticatable
{
    protected $table = 'usuarios';

    protected $guarded = ['id', 'senha_hash'];

    protected $hidden = ['senha_hash'];

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';

    public function getAuthPasswordName(): string
    {
        return 'senha_hash';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'deve_trocar_senha' => 'boolean',
            'senha_alterada_em' => 'immutable_datetime',
            'email_verificado_em' => 'immutable_datetime',
            'senha_temporaria_emitida_em' => 'immutable_datetime',
            'senha_temporaria_expira_em' => 'immutable_datetime',
            'senha_temporaria_usada_em' => 'immutable_datetime',
            'senha_temporaria_revogada_em' => 'immutable_datetime',
            'versao_seguranca' => 'integer',
        ];
    }
}
