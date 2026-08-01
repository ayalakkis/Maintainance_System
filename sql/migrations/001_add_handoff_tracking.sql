-- Migration: track who dispatched the item to the maintenance company and
-- who handed it back to the client, as distinct from who took it in.
-- Safe to run against an existing esolar_maintenance database: both new
-- columns are nullable, so existing rows are unaffected.

ALTER TABLE maintenance_records
    ADD COLUMN dispatched_by_salesperson_id INT UNSIGNED NULL COMMENT 'Who handed the item to the maintenance company/technician' AFTER salesperson_id,
    ADD COLUMN returned_by_salesperson_id INT UNSIGNED NULL COMMENT 'Who handed the item back to the client' AFTER date_returned,
    ADD KEY idx_maintenance_records_date_returned (date_returned),
    ADD CONSTRAINT fk_records_dispatched_by FOREIGN KEY (dispatched_by_salesperson_id) REFERENCES salespersons (id),
    ADD CONSTRAINT fk_records_returned_by FOREIGN KEY (returned_by_salesperson_id) REFERENCES salespersons (id);
