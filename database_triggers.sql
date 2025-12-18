-- Trigger untuk sinkronisasi otomatis order_history dengan orders
-- Trigger ini akan otomatis update order_history setiap kali status di orders berubah

USE yvk_store;

-- Hapus trigger jika sudah ada
DROP TRIGGER IF EXISTS sync_order_history_on_update;

-- Buat trigger untuk UPDATE
DELIMITER $$

CREATE TRIGGER sync_order_history_on_update
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    -- Update order_history jika status berubah
    IF OLD.status != NEW.status THEN
        UPDATE order_history
        SET status = NEW.status
        WHERE order_id = NEW.id;
    END IF;
END$$

DELIMITER ;

