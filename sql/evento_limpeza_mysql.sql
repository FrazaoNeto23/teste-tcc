-- ===============================================
-- EVENTO AGENDADO PARA LIMPEZA AUTOMÁTICA
-- ===============================================
-- Este script cria um evento no MySQL que executa
-- automaticamente à meia-noite todos os dias
-- para limpar os pedidos do dia anterior.
--
-- IMPORTANTE: O Event Scheduler precisa estar ativado!
-- Execute: SET GLOBAL event_scheduler = ON;
-- ===============================================

USE burger_house;

-- Ativar o agendador de eventos (precisa de privilégios de admin)
SET GLOBAL event_scheduler = ON;

-- Remover evento antigo se existir
DROP EVENT IF EXISTS limpar_pedidos_diario;

-- Criar evento de limpeza diária
DELIMITER $$

CREATE EVENT limpar_pedidos_diario
ON SCHEDULE EVERY 1 DAY
STARTS (TIMESTAMP(CURRENT_DATE) + INTERVAL 1 DAY)  -- Começa amanhã à meia-noite
COMMENT 'Limpa pedidos do dia anterior automaticamente'
DO
BEGIN
    -- Deletar itens dos pedidos antigos primeiro (por causa da foreign key)
    DELETE pi FROM pedido_itens pi
    INNER JOIN pedidos p ON pi.id_pedido = p.id
    WHERE DATE(p.criado_em) < CURDATE();
    
    -- Deletar pedidos antigos
    DELETE FROM pedidos 
    WHERE DATE(criado_em) < CURDATE();
    
    -- Limpar carrinhos abandonados (mais de 1 dia)
    DELETE FROM carrinho 
    WHERE DATE(adicionado_em) < CURDATE();
    
END$$

DELIMITER ;

-- ===============================================
-- VERIFICAR SE O EVENTO FOI CRIADO
-- ===============================================
SHOW EVENTS WHERE Name = 'limpar_pedidos_diario';

-- ===============================================
-- COMANDOS ÚTEIS
-- ===============================================

-- Ver status do Event Scheduler:
-- SHOW VARIABLES LIKE 'event_scheduler';

-- Ver todos os eventos:
-- SHOW EVENTS;

-- Desativar o evento (se precisar pausar):
-- ALTER EVENT limpar_pedidos_diario DISABLE;

-- Reativar o evento:
-- ALTER EVENT limpar_pedidos_diario ENABLE;

-- Executar limpeza manualmente (teste):
-- DELETE pi FROM pedido_itens pi
-- INNER JOIN pedidos p ON pi.id_pedido = p.id
-- WHERE DATE(p.criado_em) < CURDATE();
-- DELETE FROM pedidos WHERE DATE(criado_em) < CURDATE();

-- ===============================================
-- ALTERNATIVA: CRON JOB (Linux)
-- ===============================================
-- Se preferir usar CRON ao invés do MySQL Event:
-- 
-- 1. Abra o crontab:
--    crontab -e
--
-- 2. Adicione a linha (executa todo dia às 00:00):
--    0 0 * * * php /var/www/html/burger_house/limpar_pedidos.php >> /var/log/burger_limpeza.log 2>&1
--
-- 3. Salve e feche
