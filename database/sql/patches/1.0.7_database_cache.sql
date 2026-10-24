-- Cache e filas usam o mesmo MySQL da aplicação.
CREATE TABLE IF NOT EXISTS cache (
  `key` VARCHAR(255) NOT NULL PRIMARY KEY,
  `value` MEDIUMTEXT NOT NULL,
  expiration INT NOT NULL,
  KEY ix_cache_expiration (expiration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cache e limites de tentativas do Laravel.';

CREATE TABLE IF NOT EXISTS cache_locks (
  `key` VARCHAR(255) NOT NULL PRIMARY KEY,
  owner VARCHAR(255) NOT NULL,
  expiration INT NOT NULL,
  KEY ix_cache_locks_expiration (expiration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bloqueios compartilhados do cache e agendador Laravel.';

INSERT INTO versoes_modelo(versao,descricao) VALUES('1.0.7','Cache, limites e monitoramento no MySQL, sem Redis obrigatório.');
