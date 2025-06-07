-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 06/06/2025 às 23:29
-- Versão do servidor: 10.6.18-MariaDB-0ubuntu0.22.04.1
-- Versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `reve_cielo`
--

DELIMITER $$
--
-- Procedimentos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdatePaymentStatus` (IN `p_link_id` INT, IN `p_new_status` VARCHAR(50), IN `p_user_id` INT)   BEGIN
    DECLARE v_current_status VARCHAR(50);
    DECLARE v_user_level VARCHAR(20);
    
    -- Get current status and user level
    SELECT pl.status, u.nivel_acesso
    INTO v_current_status, v_user_level
    FROM payment_links pl
    JOIN usuarios u ON u.id = p_user_id
    WHERE pl.id = p_link_id;
    
    -- Check permissions
    IF v_user_level NOT IN ('admin', 'editor') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Acesso negado';
    END IF;
    
    -- Check if status change is valid
    IF p_new_status = 'Crédito Gerado' AND v_current_status != 'Pago' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Só é possível alterar para Crédito Gerado quando o status for Pago';
    END IF;
    
    -- Update status
    UPDATE payment_links 
    SET status = p_new_status, updated_at = NOW()
    WHERE id = p_link_id;
    
    -- Log the change
    INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values)
    VALUES (p_user_id, 'UPDATE', 'payment_links', p_link_id, 
            JSON_OBJECT('status', v_current_status),
            JSON_OBJECT('status', p_new_status));
END$$

--
-- Funções
--
CREATE DEFINER=`root`@`localhost` FUNCTION `CalculateInterest` (`amount` DECIMAL(10,2), `installments` INT) RETURNS DECIMAL(10,2) DETERMINISTIC READS SQL DATA BEGIN
    IF installments >= 4 AND installments <= 6 THEN
        RETURN amount * 0.04;
    END IF;
    RETURN 0.00;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'STATUS_CHANGE', 'payment_links', 3, '{\"status\": \"Criado\"}', '{\"status\": \"Inativo\"}', NULL, NULL, '2025-06-06 23:13:01'),
(2, 1, 'STATUS_CHANGE', 'payment_links', 3, '{\"status\": \"Inativo\"}', '{\"status\": \"Utilizado\"}', NULL, NULL, '2025-06-06 23:14:33');

-- --------------------------------------------------------

--
-- Estrutura para tabela `payment_links`
--

CREATE TABLE `payment_links` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `valor_original` decimal(10,2) NOT NULL,
  `valor_juros` decimal(10,2) DEFAULT 0.00,
  `valor_final` decimal(10,2) NOT NULL,
  `parcelas` int(11) NOT NULL DEFAULT 1,
  `link_url` text DEFAULT NULL,
  `payment_id` varchar(255) DEFAULT NULL,
  `product_id` varchar(255) DEFAULT NULL,
  `status` enum('Criado','Crédito','Utilizado','Inativo') DEFAULT 'Criado',
  `status_cielo` int(11) DEFAULT 0,
  `descricao` text DEFAULT NULL,
  `tipo_link` varchar(50) DEFAULT NULL,
  `data_expiracao` datetime DEFAULT NULL,
  `url_completa` text DEFAULT NULL,
  `url_curta` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `payment_links`
--

INSERT INTO `payment_links` (`id`, `user_id`, `valor_original`, `valor_juros`, `valor_final`, `parcelas`, `link_url`, `payment_id`, `product_id`, `status`, `status_cielo`, `descricao`, `tipo_link`, `data_expiracao`, `url_completa`, `url_curta`, `created_at`, `updated_at`) VALUES
(3, 1, 10.00, 0.00, 10.00, 2, 'https://cielolink.com.br/4kyi15J', '32fabe76-0977-471a-b6d1-700d349a4d81', '32fabe76-0977-471a-b6d1-700d349a4d81', 'Utilizado', 0, 'TESTE', 'Digital', '2025-06-11 21:53:54', NULL, 'https://cielolink.com.br/4kyi15J', '2025-06-06 21:53:55', '2025-06-06 23:14:33');

--
-- Acionadores `payment_links`
--
DELIMITER $$
CREATE TRIGGER `tr_audit_payment_links` AFTER UPDATE ON `payment_links` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values)
        VALUES (NEW.user_id, 'STATUS_CHANGE', 'payment_links', NEW.id,
                JSON_OBJECT('status', OLD.status),
                JSON_OBJECT('status', NEW.status));
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_calculate_final_amount` BEFORE INSERT ON `payment_links` FOR EACH ROW BEGIN
    SET NEW.valor_juros = CalculateInterest(NEW.valor_original, NEW.parcelas);
    SET NEW.valor_final = NEW.valor_original + NEW.valor_juros;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `nivel_acesso` enum('admin','editor','usuario') DEFAULT 'usuario',
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha_hash`, `nivel_acesso`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 'Administrador', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, '2025-06-06 16:07:59', '2025-06-06 16:07:59'),
(2, 'Editor', 'editor@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'editor', 1, '2025-06-06 16:07:59', '2025-06-06 16:07:59');

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_payment_summary`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_payment_summary` (
`usuario_nome` varchar(255)
,`usuario_email` varchar(255)
,`nivel_acesso` enum('admin','editor','usuario')
,`total_links` bigint(21)
,`total_valor_original` decimal(32,2)
,`total_valor_final` decimal(32,2)
,`links_pagos` decimal(22,0)
,`links_pendentes` decimal(22,0)
,`links_credito_gerado` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Estrutura para view `vw_payment_summary`
--
DROP TABLE IF EXISTS `vw_payment_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_payment_summary`  AS SELECT `u`.`nome` AS `usuario_nome`, `u`.`email` AS `usuario_email`, `u`.`nivel_acesso` AS `nivel_acesso`, count(`pl`.`id`) AS `total_links`, sum(`pl`.`valor_original`) AS `total_valor_original`, sum(`pl`.`valor_final`) AS `total_valor_final`, sum(case when `pl`.`status` = 'Crédito' then 1 else 0 end) AS `links_pagos`, sum(case when `pl`.`status` = 'Criado' then 1 else 0 end) AS `links_pendentes`, sum(case when `pl`.`status` = 'Utilizado' then 1 else 0 end) AS `links_credito_gerado` FROM (`usuarios` `u` left join `payment_links` `pl` on(`u`.`id` = `pl`.`user_id`)) WHERE `u`.`ativo` = 1 GROUP BY `u`.`id`, `u`.`nome`, `u`.`email`, `u`.`nivel_acesso` ;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_table_name` (`table_name`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `payment_links`
--
ALTER TABLE `payment_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_payment_links_composite` (`user_id`,`status`,`created_at`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_nivel_acesso` (`nivel_acesso`),
  ADD KEY `idx_ativo` (`ativo`),
  ADD KEY `idx_usuarios_login` (`email`,`ativo`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `payment_links`
--
ALTER TABLE `payment_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `payment_links`
--
ALTER TABLE `payment_links`
  ADD CONSTRAINT `payment_links_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
