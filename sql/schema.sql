-- Esolar Maintenance Tracker
-- Database schema

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- item_types
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS item_types (
    id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_item_types_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO item_types (name) VALUES
    ('Inverter'),
    ('Acid Battery'),
    ('Lithium Battery');

-- ---------------------------------------------------------------------
-- salespersons
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS salespersons (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(150) NOT NULL,
    is_custom  TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO salespersons (name, is_custom) VALUES
    ('Mohamad Ali', 0),
    ('Amir', 0);

-- ---------------------------------------------------------------------
-- maintenance_records
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS maintenance_records (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_type_id     INT UNSIGNED NOT NULL,
    inverter_size    VARCHAR(20) NULL,
    battery_capacity_kw DECIMAL(6,2) NULL,
    owner_name       VARCHAR(150) NOT NULL,
    owner_phone      VARCHAR(30) NULL,
    salesperson_id   INT UNSIGNED NOT NULL COMMENT 'Salesperson who took the item from the client',
    dispatched_by_salesperson_id INT UNSIGNED NULL COMMENT 'Who handed the item to the maintenance company/technician',
    date_dispatched  DATE NULL COMMENT 'Date the item was handed to the maintenance company/technician',
    picked_up_by_salesperson_id INT UNSIGNED NULL COMMENT 'Who retrieved the item back from the maintenance company/technician',
    date_retrieved   DATE NULL COMMENT 'Date the item was retrieved back from the maintenance company/technician',
    date_given       DATE NOT NULL,
    date_returned    DATE NULL,
    returned_by_salesperson_id INT UNSIGNED NULL COMMENT 'Who handed the item back to the client',
    status           ENUM('pending_dispatch', 'with_technician', 'in_repair', 'ready_for_pickup', 'returned_to_customer')
                         NOT NULL DEFAULT 'pending_dispatch',
    notes            TEXT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_maintenance_records_status (status),
    KEY idx_maintenance_records_date_given (date_given),
    KEY idx_maintenance_records_date_dispatched (date_dispatched),
    KEY idx_maintenance_records_date_retrieved (date_retrieved),
    KEY idx_maintenance_records_date_returned (date_returned),
    CONSTRAINT fk_records_item_type
        FOREIGN KEY (item_type_id) REFERENCES item_types (id),
    CONSTRAINT fk_records_salesperson
        FOREIGN KEY (salesperson_id) REFERENCES salespersons (id),
    CONSTRAINT fk_records_dispatched_by
        FOREIGN KEY (dispatched_by_salesperson_id) REFERENCES salespersons (id),
    CONSTRAINT fk_records_picked_up_by
        FOREIGN KEY (picked_up_by_salesperson_id) REFERENCES salespersons (id),
    CONSTRAINT fk_records_returned_by
        FOREIGN KEY (returned_by_salesperson_id) REFERENCES salespersons (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- receipts
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS receipts (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    record_id         INT UNSIGNED NOT NULL,
    receipt_type      ENUM('handover', 'return') NOT NULL,
    receipt_number    VARCHAR(20) NOT NULL,
    printed_by        INT UNSIGNED NOT NULL,
    printed_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    signed_file_path  VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_receipts_receipt_number (receipt_number),
    CONSTRAINT fk_receipts_record
        FOREIGN KEY (record_id) REFERENCES maintenance_records (id),
    CONSTRAINT fk_receipts_printed_by
        FOREIGN KEY (printed_by) REFERENCES salespersons (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
