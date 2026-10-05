<?php

namespace App\Models;

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
        ];
    }
}
