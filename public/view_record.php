<?php
/**
 * View Record - full detail of a single maintenance record, plus links to
 * its handover and return receipts (if they exist).
 */

require_once __DIR__ . '/../includes/functions.php';

$record_id = filter_input(INPUT_GET, 'record_id', FILTER_VALIDATE_INT);

$record = null;
$receipts = [];
$error = null;

if (!$record_id || $record_id < 1) {
    $error = t('record_id_invalid');
} else {
    $pdo = get_db_connection();
    $record = get_record_details($pdo, $record_id);
    if ($record === null) {
        $error = t('record_not_found');
    } else {
        $receipts = get_receipts_for_record($pdo, $record_id);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <?php if ($error !== null): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <a href="records.php" class="btn btn-outline-secondary"><?= htmlspecialchars(t('btn_back')) ?></a>
        <?php else: ?>
            <?php
            $item_label = format_item_label($record);
            $status_label = t('status_' . $record['status']);
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h4 mb-0"><?= htmlspecialchars(t('view_record_title')) ?></h1>
                <div class="d-flex gap-2">
                    <a href="edit_record.php?record_id=<?= (int) $record['id'] ?>" class="btn btn-outline-primary"><?= htmlspecialchars(t('btn_edit')) ?></a>
                    <a href="records.php" class="btn btn-outline-secondary"><?= htmlspecialchars(t('btn_back')) ?></a>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body p-4">
                    <dl class="row mb-0">
                        <dt class="col-sm-4"><?= htmlspecialchars(t('field_item')) ?></dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($item_label) ?></dd>

                        <dt class="col-sm-4"><?= htmlspecialchars(t('field_owner_name')) ?></dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($record['owner_name']) ?></dd>

                        <dt class="col-sm-4"><?= htmlspecialchars(t('field_owner_phone')) ?></dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($record['owner_phone'] ?? '-') ?></dd>

                        <dt class="col-sm-4"><?= htmlspecialchars(t('field_salesperson')) ?></dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($record['salesperson_name']) ?></dd>

                        <dt class="col-sm-4"><?= htmlspecialchars(t('field_date_given')) ?></dt>
                        <dd class="col-sm-8"><?= htmlspecialchars(date('M j, Y', strtotime($record['date_given']))) ?></dd>

                        <dt class="col-sm-4"><?= htmlspecialchars(t('field_dispatched_by')) ?></dt>
                        <dd class="col-sm-8"><?= $record['dispatched_by_name'] ? htmlspecialchars($record['dispatched_by_name']) : '-' ?></dd>

                        <dt class="col-sm-4"><?= htmlspecialchars(t('field_date_dispatched')) ?></dt>
                        <dd class="col-sm-8"><?= $record['date_dispatched'] ? htmlspecialchars(date('M j, Y', strtotime($record['date_dispatched']))) : '-' ?></dd>

                        <dt class="col-sm-4"><?= htmlspecialchars(t('field_picked_up_by')) ?></dt>
                        <dd class="col-sm-8"><?= $record['picked_up_by_name'] ? htmlspecialchars($record['picked_up_by_name']) : '-' ?></dd>

                        <dt class="col-sm-4"><?= htmlspecialchars(t('field_date_retrieved')) ?></dt>
                        <dd class="col-sm-8"><?= $record['date_retrieved'] ? htmlspecialchars(date('M j, Y', strtotime($record['date_retrieved']))) : '-' ?></dd>

                        <dt class="col-sm-4"><?= htmlspecialchars(t('field_returned_by')) ?></dt>
                        <dd class="col-sm-8"><?= $record['returned_by_name'] ? htmlspecialchars($record['returned_by_name']) : '-' ?></dd>

                        <dt class="col-sm-4"><?= htmlspecialchars(t('field_date_returned')) ?></dt>
                        <dd class="col-sm-8"><?= $record['date_returned'] ? htmlspecialchars(date('M j, Y', strtotime($record['date_returned']))) : '-' ?></dd>

                        <dt class="col-sm-4"><?= htmlspecialchars(t('field_status')) ?></dt>
                        <dd class="col-sm-8"><span class="badge <?= status_badge_class($record['status']) ?>"><?= htmlspecialchars($status_label) ?></span></dd>

                        <dt class="col-sm-4"><?= htmlspecialchars(t('field_notes')) ?></dt>
                        <dd class="col-sm-8"><?= $record['notes'] ? nl2br(htmlspecialchars($record['notes'])) : '-' ?></dd>
                    </dl>
                </div>
                <div class="card-footer text-muted small">
                    <?= htmlspecialchars(t('field_logged_at')) ?>: <?= htmlspecialchars(date('M j, Y g:i A', strtotime($record['created_at']))) ?>
                    &middot;
                    <?= htmlspecialchars(t('field_updated_at')) ?>: <?= htmlspecialchars(date('M j, Y g:i A', strtotime($record['updated_at']))) ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><?= htmlspecialchars(t('receipts_section_title')) ?></div>
                <div class="card-body p-0">
                    <?php if (empty($receipts)): ?>
                        <p class="text-muted p-4 mb-0"><?= htmlspecialchars(t('no_receipts')) ?></p>
                    <?php else: ?>
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th><?= htmlspecialchars(t('receipt_type_label')) ?></th>
                                    <th><?= htmlspecialchars(t('th_receipt_number')) ?></th>
                                    <th><?= htmlspecialchars(t('th_printed_at')) ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($receipts as $receipt): ?>
                                    <?php $type_label = $receipt['receipt_type'] === 'return' ? t('receipt_type_return') : t('receipt_type_handover'); ?>
                                    <tr>
                                        <td><?= htmlspecialchars($type_label) ?></td>
                                        <td><?= htmlspecialchars($receipt['receipt_number']) ?></td>
                                        <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($receipt['printed_at']))) ?></td>
                                        <td class="text-end">
                                            <a href="print_receipt.php?receipt_id=<?= (int) $receipt['id'] ?>" class="btn btn-sm btn-outline-primary"><?= htmlspecialchars(t('btn_view_print')) ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
