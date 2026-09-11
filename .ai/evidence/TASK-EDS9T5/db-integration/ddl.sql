-- Throwaway integration database for Secomm_ZaloPay corrective round 5.
-- Created and dropped by run.sh; the shared `magento` database is untouched.
-- DDL translated 1:1 from app/code/Secomm/ZaloPay/etc/db_schema.xml
-- (payment-first table). Foreign keys to quote/sales_order are omitted:
-- those tables do not exist in this throwaway database and the blocking
-- query does not reference them.
CREATE TABLE IF NOT EXISTS secomm_zalopay_payment_attempt (
  entity_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Entity Id',
  quote_id INT(10) UNSIGNED NOT NULL COMMENT 'Owning Quote Id',
  reserved_order_id VARCHAR(64) NOT NULL COMMENT 'Quote reserved order increment id',
  app_trans_id VARCHAR(64) DEFAULT NULL COMMENT 'ZaloPay app_trans_id (our merchant reference)',
  provider_transaction_id VARCHAR(64) DEFAULT NULL COMMENT 'ZaloPay zp_trans_id',
  pay_url TEXT DEFAULT NULL COMMENT 'ZaloPay pay URL',
  provider_status VARCHAR(32) DEFAULT NULL COMMENT 'Provider-side status',
  payment_status VARCHAR(32) NOT NULL DEFAULT 'initiated' COMMENT 'Attempt lifecycle status',
  amount INT(10) UNSIGNED NOT NULL COMMENT 'Snapshot amount in VND (locked at initiation)',
  currency VARCHAR(3) NOT NULL DEFAULT 'VND' COMMENT 'Currency',
  contract_hash VARCHAR(64) DEFAULT NULL COMMENT 'Quote payment-contract fingerprint (sha-256) locked at initiation',
  order_id INT(10) UNSIGNED DEFAULT NULL COMMENT 'Sales order entity id after finalize',
  last_error TEXT DEFAULT NULL COMMENT 'Last error message',
  requires_reconciliation TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Money-real but NOT auto-finalizable (amount/contract/provider-id conflict)',
  reconciliation_code VARCHAR(64) DEFAULT NULL COMMENT 'Machine-readable reason code for requires_reconciliation',
  recovery_attempts SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Proactive lost-callback query attempts (bounded recovery worker)',
  recovery_exhausted TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Explicit marker: bounded recovery worker consumed its full proactive query budget (operational; NOT money-real evidence)',
  retry_count SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of prior attempts for the same quote',
  store_id SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Store Id',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created At',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated At',
  expires_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Attempt expiry (TTL)',
  PRIMARY KEY (entity_id),
  UNIQUE KEY SECOMM_ZALOPAY_PAYMENT_ATTEMPT_APP_TRANS_ID (app_trans_id),
  UNIQUE KEY SECOMM_ZALOPAY_PAYMENT_ATTEMPT_ORDER_ID (order_id),
  KEY SECOMM_ZALOPAY_PAYMENT_ATTEMPT_QUOTE_ID (quote_id),
  KEY SECOMM_ZALOPAY_PAYMENT_ATTEMPT_RESERVED_ORDER_ID (reserved_order_id),
  KEY SECOMM_ZALOPAY_PAYMENT_ATTEMPT_PAYMENT_STATUS (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='ZaloPay payment-first initiation attempts';
