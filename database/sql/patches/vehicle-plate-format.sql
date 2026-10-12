-- Preserva espacos e hifens informados no cadastro da placa.
ALTER TABLE veiculos DROP CHECK ck_veiculo_placa, MODIFY COLUMN placa VARCHAR(10) NOT NULL;
