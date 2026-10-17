<?php

namespace App\Services\Reports;

use App\Support\Value;
use stdClass;

final readonly class ReportColumn
{
    public function __construct(
        public int $id,
        public string $chave,
        public string $rotulo,
        public ?string $acao_adicional,
    ) {}

    public static function fromRow(stdClass $row): self
    {
        return new self(
            Value::integer($row->id ?? 0),
            Value::string($row->chave),
            Value::string($row->rotulo),
            Value::nullableString($row->acao_adicional ?? null),
        );
    }
}
