-- Migration: track the item-retrieval handoff (technician back to salesperson)
-- as its own dated, attributed step, and add a "just taken in, not yet
-- dispatched" status so intake and dispatch no longer have to happen on the
-- same visit.
--
-- Safe to run against an existing esolar_maintenance database: the new
-- columns are nullable and the new enum value is additive, so existing rows
-- are unaffected. Existing rows already sitting in 'with_technician' (the
-- old default) keep that status as-is.

ALTER TABLE maintenance_records
    MODIFY COLUMN status ENUM('pending_dispatch', 'with_technician', 'in_repair', 'ready_for_pickup', 'returned_to_customer')
        NOT NULL DEFAULT 'pending_dispatch',
    ADD COLUMN date_dispatched DATE NULL COMMENT 'Date the item was handed to the maintenance company/technician' AFTER dispatched_by_salesperson_id,
    ADD COLUMN picked_up_by_salesperson_id INT UNSIGNED NULL COMMENT 'Who retrieved the item back from the maintenance company/technician' AFTER date_dispatched,
    ADD COLUMN date_retrieved DATE NULL COMMENT 'Date the item was retrieved back from the maintenance company/technician' AFTER picked_up_by_salesperson_id,
    ADD KEY idx_maintenance_records_date_dispatched (date_dispatched),
    ADD KEY idx_maintenance_records_date_retrieved (date_retrieved),
    ADD CONSTRAINT fk_records_picked_up_by FOREIGN KEY (picked_up_by_salesperson_id) REFERENCES salespersons (id);
