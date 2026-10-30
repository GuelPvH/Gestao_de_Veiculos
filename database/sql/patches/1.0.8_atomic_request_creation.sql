-- Salva a revisão, coordenadas, passageiros e anexo antes de confirmar o rascunho.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+00:00';
CREATE TEMPORARY TABLE frota_request_atomic_guard (ok TINYINT NOT NULL CHECK (ok = 1));
INSERT INTO frota_request_atomic_guard(ok)
SELECT IF(
  (SELECT COUNT(*) FROM versoes_modelo WHERE versao='1.0.7')=1
  AND (SELECT COUNT(*) FROM versoes_modelo WHERE versao='1.0.8')=0
  AND (SELECT COUNT(*) FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA=DATABASE()
    AND SPECIFIC_NAME='sp_criar_solicitacao_completa' AND ORDINAL_POSITION>0)=12,1,0);
DROP TEMPORARY TABLE frota_request_atomic_guard;

DELIMITER $$
DROP PROCEDURE sp_criar_solicitacao_completa$$
CREATE PROCEDURE sp_criar_solicitacao_completa(
  IN p_vinculo BIGINT UNSIGNED, IN p_finalidade TEXT, IN p_origem VARCHAR(255), IN p_destino VARCHAR(255),
  IN p_saida DATETIME(6), IN p_retorno DATETIME(6), IN p_trajeto TEXT,
  IN p_quantidade SMALLINT UNSIGNED, IN p_necessita_motorista TINYINT UNSIGNED,
  IN p_veiculo BIGINT UNSIGNED, IN p_observacoes TEXT, IN p_passageiros JSON,
  IN p_origem_latitude DECIMAL(10,7), IN p_origem_longitude DECIMAL(10,7),
  IN p_destino_latitude DECIMAL(10,7), IN p_destino_longitude DECIMAL(10,7),
  IN p_arquivo BIGINT UNSIGNED)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED;
  DECLARE v_id BIGINT UNSIGNED; DECLARE v_revisao BIGINT UNSIGNED; DECLARE v_nivel INT;
  DECLARE v_arquivo BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_veiculo_unidade BIGINT UNSIGNED; DECLARE v_capacidade INT; DECLARE v_situacao VARCHAR(30);
  DECLARE v_categoria CHAR(1); DECLARE v_categoria_ativa TINYINT;
  DECLARE v_motorista BIGINT UNSIGNED DEFAULT NULL; DECLARE v_cnh VARCHAR(5); DECLARE v_validade DATE;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT usuario_id,unidade_id INTO v_usuario,v_unidade FROM vw_vinculos_ativos WHERE vinculo_id=p_vinculo;
  CALL sp_exigir_permissao(p_vinculo,'solicitacoes','criar',v_usuario,v_unidade);
  IF p_finalidade IS NULL OR CHAR_LENGTH(TRIM(p_finalidade))=0 OR CHAR_LENGTH(p_finalidade)>3000
    OR p_origem IS NULL OR CHAR_LENGTH(TRIM(p_origem))=0 OR p_destino IS NULL OR CHAR_LENGTH(TRIM(p_destino))=0
    OR p_saida IS NULL OR p_retorno IS NULL OR p_retorno<=p_saida
    OR p_quantidade IS NULL OR p_quantidade NOT BETWEEN 1 AND 100
    OR p_necessita_motorista IS NULL OR p_necessita_motorista NOT IN (0,1)
    OR p_origem_latitude IS NULL OR p_origem_latitude NOT BETWEEN -90 AND 90
    OR p_origem_longitude IS NULL OR p_origem_longitude NOT BETWEEN -180 AND 180
    OR p_destino_latitude IS NULL OR p_destino_latitude NOT BETWEEN -90 AND 90
    OR p_destino_longitude IS NULL OR p_destino_longitude NOT BETWEEN -180 AND 180
    OR CHAR_LENGTH(COALESCE(p_trajeto,''))>3000 OR CHAR_LENGTH(COALESCE(p_observacoes,''))>3000 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Formulário da solicitação incompleto ou inválido.';
  END IF;
  SELECT v.unidade_id,v.capacidade,v.situacao_cadastro,c.categoria_cnh_requerida,c.ativa
  INTO v_veiculo_unidade,v_capacidade,v_situacao,v_categoria,v_categoria_ativa
  FROM veiculos v JOIN categorias_veiculo c ON c.id=v.categoria_id WHERE v.id=p_veiculo FOR UPDATE;
  IF v_situacao IS NULL OR v_situacao<>'ativo' OR v_categoria_ativa<>1 OR v_capacidade<p_quantidade+1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Veículo inexistente, inativo ou com capacidade insuficiente.';
  END IF;
  SELECT COALESCE(MAX(nivel_alcance),0) INTO v_nivel FROM vw_permissoes_efetivas
  WHERE vinculo_id=p_vinculo AND modulo_codigo='frota' AND acao_codigo='selecionar';
  IF v_nivel=0 OR (v_nivel<3 AND v_veiculo_unidade<>v_unidade) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Veículo fora do alcance de seleção autorizado.';
  END IF;
  IF p_necessita_motorista=0 THEN
    SELECT m.usuario_id,m.categorias_autorizadas,m.validade_cnh INTO v_motorista,v_cnh,v_validade
    FROM motoristas m JOIN usuarios u ON u.id=m.usuario_id AND u.ativo=1
    WHERE m.usuario_id=v_usuario AND m.ativo=1 FOR UPDATE;
    IF v_motorista IS NULL OR v_validade<DATE(p_retorno) OR LOCATE(v_categoria,v_cnh)=0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitante sem habilitação elegível para conduzir a viagem.';
    END IF;
  END IF;
  INSERT INTO solicitacoes(protocolo,solicitante_id,unidade_id)
  VALUES(CONCAT('SOL-',REPLACE(UUID(),'-','')),v_usuario,v_unidade);
  SET v_id=LAST_INSERT_ID();
  INSERT INTO solicitacao_revisoes(solicitacao_id,numero,criado_por,finalidade,origem,destino,origem_latitude,origem_longitude,
    destino_latitude,destino_longitude,saida_prevista,retorno_previsto,
    trajeto_planejado,quantidade_passageiros,necessita_motorista,motorista_sugerido_id,veiculo_pretendido_id,observacoes,etapa_atual)
  VALUES(v_id,1,v_usuario,p_finalidade,p_origem,p_destino,p_origem_latitude,p_origem_longitude,
    p_destino_latitude,p_destino_longitude,p_saida,p_retorno,p_trajeto,p_quantidade,p_necessita_motorista,v_motorista,p_veiculo,p_observacoes,4);
  SET v_revisao=LAST_INSERT_ID();
  -- A validação e inserção da lista ainda fazem parte da mesma transação do cabeçalho e revisão.
  IF p_passageiros IS NULL OR JSON_TYPE(p_passageiros)<>'ARRAY' OR JSON_LENGTH(p_passageiros)>p_quantidade THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lista de passageiros inválida.';
  END IF;
  IF EXISTS(SELECT 1 FROM JSON_TABLE(p_passageiros,'$[*]' COLUMNS(pos FOR ORDINALITY,nome VARCHAR(16383) PATH '$' ERROR ON ERROR)) nomes
    WHERE JSON_TYPE(JSON_EXTRACT(p_passageiros,CONCAT('$[',nomes.pos-1,']')))<>'STRING'
      OR CHAR_LENGTH(TRIM(nomes.nome))=0 OR CHAR_LENGTH(nomes.nome)>150) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cada passageiro deve ter um nome com até 150 caracteres.';
  END IF;
  INSERT INTO solicitacao_passageiros(revisao_id,nome)
  SELECT v_revisao,nomes.nome FROM JSON_TABLE(p_passageiros,'$[*]' COLUMNS(nome VARCHAR(150) PATH '$' ERROR ON ERROR)) nomes;
  IF p_arquivo IS NOT NULL THEN
    SELECT id INTO v_arquivo FROM arquivos
    WHERE id=p_arquivo AND enviado_por=v_usuario AND situacao='disponivel' FOR UPDATE;
    IF v_arquivo IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Anexo indisponível para esta solicitação.';
    END IF;
    INSERT INTO anexos(arquivo_id,revisao_id) VALUES(v_arquivo,v_revisao);
  END IF;
  UPDATE solicitacoes SET revisao_atual_id=v_revisao,protocolo=CONCAT('SOL-',LPAD(v_id,8,'0')),versao=2 WHERE id=v_id;
  INSERT INTO solicitacao_eventos(solicitacao_id,revisao_id,ator_vinculo_id,tipo,situacao_nova)
  VALUES(v_id,v_revisao,p_vinculo,'criada','rascunho');
  CALL sp_auditar(p_vinculo,'solicitacao_criada','solicitacoes',v_id,'Primeira revisão e passageiros criados de forma atômica.');
  COMMIT;
  SELECT v_id AS solicitacao_id,v_revisao AS revisao_id;
END$$
DELIMITER ;

INSERT INTO versoes_modelo(versao,descricao)
VALUES('1.0.8','Criação atômica de solicitações com coordenadas e anexos vinculados antes da confirmação.');
