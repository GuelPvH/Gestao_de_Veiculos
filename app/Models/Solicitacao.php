<?php

namespace App\Models;

use App\Services\Requests\RequestReadRepository;

/** @deprecated Use RequestReadRepository para consultas e RequestWorkflow para escrita. */
class Solicitacao extends RequestReadRepository {}
