<?php
/**
 * Records Archive - full historical list of every maintenance record,
 * regardless of status, with search + filters submitted via GET.
 */

require_once __DIR__ . '/../includes/functions.php';

$statuses = ['pending_dispatch', 'with_technician', 'in_repair', 'ready_for_pickup', 'returned_to_customer'];

$item_types = get_item_types();
$item_type_ids = array_column($item_types, 'id');
$salespersons = get_salespersons();
$salesperson_ids = array_column($salespersons, 'id');

// --- Read + validate GET filters, keeping raw values around to repopulate the form ---
$q = trim($_GET['q'] ?? '');
$item_type_id = filter_input(INPUT_GET, 'item_type_id', FILTER_VALIDATE_INT);
$status = $_GET['status'] ?? '';
$salesperson_id = filter_input(INPUT_GET, 'salesperson_id', FILTER_VALIDATE_INT);
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

function is_valid_date_string(string $value): bool
{
    if ($value === '') {
        return false;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

$filters = [];
if ($q !== '') {
    $filters['q'] = $q;
}
if ($item_type_id && in_array($item_type_id, $item_type_ids, true)) {
    $filters['item_type_id'] = $item_type_id;
}
if (in_array($status, $statuses, true)) {
    $filters['status'] = $status;
}
if ($salesperson_id && in_array($salesperson_id, $salesperson_ids, true)) {
    $filters['salesperson_id'] = $salesperson_id;
}
if (is_valid_date_string($date_from)) {
    $filters['date_from'] = $date_from;
}
if (is_valid_date_string($date_to)) {
    $filters['date_to'] = $date_to;
}

$pdo = get_db_connection();
$records = search_records($pdo, $filters);

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="h4 mb-4"><?= htmlspecialchars(t('records_page_title')) ?></h1>

<form method="get" action="records.php" class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="q" class="form-label"><?= htmlspecialchars(t('btn_search')) ?></label>
                <input
                    type="text"
                    class="form-control"
                    id="q"
                    name="q"
                    placeholder="<?= htmlspecialchars(t('search_placeholder')) ?>"
                    value="<?= htmlspecialchars($q) ?>"
                >
            </div>

            <div class="col-md-2">
                <label for="item_type_id" class="form-label"><?= htmlspecialchars(t('field_item_type')) ?></label>
                <select class="form-select" id="item_type_id" name="item_type_id">
                    <option value=""><?= htmlspecialchars(t('filter_all')) ?></option>
                    <?php foreach ($item_types as $type): ?>
                        <option value="<?= (int) $type['id'] ?>"<?= $item_type_id === (int) $type['id'] ? ' selected' : '' ?>>
                            <?= htmlspecialchars($type['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label for="status" class="form-label"><?= htmlspecialchars(t('field_status')) ?></label>
                <select class="form-select" id="status" name="status">
                    <option value=""><?= htmlspecialchars(t('filter_all')) ?></option>
                    <?php foreach ($statuses as $status_value): ?>
                        <option value="<?= htmlspecialchars($status_value) ?>"<?= $status === $status_value ? ' selected' : '' ?>>
                            <?= htmlspecialchars(t('status_' . $status_value)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="salesperson_id" class="form-label"><?= htmlspecialchars(t('field_salesperson')) ?></label>
                <select class="form-select" id="salesperson_id" name="salesperson_id">
                    <option value=""><?= htmlspecialchars(t('filter_all')) ?></option>
                    <?php foreach ($salespersons as $person): ?>
                        <option value="<?= (int) $person['id'] ?>"<?= $salesperson_id === (int) $person['id'] ? ' selected' : '' ?>>
                            <?= htmlspecialchars($person['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label for="date_from" class="form-label"><?= htmlspecialchars(t('field_date_from')) ?></label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
            </div>

            <div class="col-md-2">
                <label for="date_to" class="form-label"><?= htmlspecialchars(t('field_date_to')) ?></label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
            </div>

            <div class="col-md-4 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary"><?= htmlspecialchars(t('btn_search')) ?></button>
                <a href="records.php" class="btn btn-outline-secondary"><?= htmlspecialchars(t('btn_clear')) ?></a>
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($records)): ?>
            <p class="text-muted p-4 mb-0"><?= htmlspecialchars(t('records_empty')) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><?= htmlspecialchars(t('field_owner_name')) ?></th>
                            <th><?= htmlspecialchars(t('field_owner_phone')) ?></th>
                            <th><?= htmlspecialchars(t('field_item')) ?></th>
                            <th><?= htmlspecialchars(t('field_salesperson')) ?></th>
                            <th><?= htmlspecialchars(t('field_date_given')) ?></th>
                            <th><?= htmlspecialchars(t('field_status')) ?></th>
                            <th><?= htmlspecialchars(t('th_dispatched')) ?></th>
                            <th><?= htmlspecialchars(t('th_retrieved')) ?></th>
                            <th><?= htmlspecialchars(t('field_date_returned')) ?></th>
                            <th><?= htmlspecialchars(t('field_returned_by')) ?></th>
                            <th><?= htmlspecialchars(t('th_view')) ?></th>
                            <th><?= htmlspecialchars(t('th_receipt')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <?php
                            $status_label = t('status_' . $record['status']);
                            $item_label = format_item_label($record);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($record['owner_name']) ?></td>
                                <td><?= htmlspecialchars($record['owner_phone'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($item_label) ?></td>
                                <td><?= htmlspecialchars($record['salesperson_name']) ?></td>
                                <td><?= htmlspecialchars(date('M j, Y', strtotime($record['date_given']))) ?></td>
                                <td><span class="badge <?= status_badge_class($record['status']) ?>"><?= htmlspecialchars($status_label) ?></span></td>
                                <td>
                                    <?php if ($record['date_dispatched']): ?>
                                        <?= htmlspecialchars(date('M j, Y', strtotime($record['date_dispatched']))) ?><br>
                                        <span class="text-muted small"><?= htmlspecialchars($record['dispatched_by_name']) ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['date_retrieved']): ?>
                                        <?= htmlspecialchars(date('M j, Y', strtotime($record['date_retrieved']))) ?><br>
                                        <span class="text-muted small"><?= htmlspecialchars($record['picked_up_by_name']) ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= $record['date_returned'] ? htmlspecialchars(date('M j, Y', strtotime($record['date_returned']))) : '-' ?></td>
                                <td><?= $record['returned_by_name'] ? htmlspecialchars($record['returned_by_name']) : '-' ?></td>
                                <td><a href="view_record.php?record_id=<?= (int) $record['id'] ?>"><?= htmlspecialchars(t('th_view')) ?></a></td>
                                <td>
                                    <?php if (!empty($record['handover_receipt_id'])): ?>
                                        <a href="print_receipt.php?receipt_id=<?= (int) $record['handover_receipt_id'] ?>"><?= htmlspecialchars(t('th_handover_receipt')) ?></a>
                                    <?php endif; ?>
                                    <?php if (!empty($record['return_receipt_id'])): ?>
                                        <?php if (!empty($record['handover_receipt_id'])): ?> &middot; <?php endif; ?>
                                        <a href="print_receipt.php?receipt_id=<?= (int) $record['return_receipt_id'] ?>"><?= htmlspecialchars(t('th_return_receipt')) ?></a>
                                    <?php endif; ?>
                                    <?php if (empty($record['handover_receipt_id']) && empty($record['return_receipt_id'])): ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
