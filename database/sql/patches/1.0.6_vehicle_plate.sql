-- Mantém o formato de placas introduzido na interface sem alterar registros existentes.
-- O cadastro inicial canônico já possui a coluna ampliada e não possui este CHECK.
SET @fleet_plate_drop = IF(
  EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='veiculos' AND CONSTRAINT_NAME='ck_veiculo_placa'),
  CONCAT('ALTER TABLE veiculos DROP ', IF(VERSION() LIKE '%MariaDB%', 'CONSTRAINT ', 'CHECK '), 'ck_veiculo_placa'),
  'SELECT 1'
);
PREPARE fleet_plate_statement FROM @fleet_plate_drop;
EXECUTE fleet_plate_statement;
DEALLOCATE PREPARE fleet_plate_statement;
ALTER TABLE veiculos MODIFY COLUMN placa VARCHAR(10) NOT NULL;
INSERT INTO versoes_modelo(versao,descricao) VALUES('1.0.6','Preservar espaços e hífens no formato das placas.');
