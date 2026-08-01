<?php
/**
 * Dashboard - active maintenance records (anything not yet returned) with
 * one-click actions to advance each record through its lifecycle
 * (dispatch to technician -> in repair -> retrieve -> return to customer).
 * This is the default landing page.
 */

require_once __DIR__ . '/../includes/functions.php';

$action_names = ['dispatch', 'mark_in_repair', 'retrieve', 'return'];
$salespersons = get_salespersons();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $record_id = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);

    if ($record_id && in_array($action, $action_names, true) && verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $pdo = get_db_connection();

        if ($action === 'mark_in_repair') {
            mark_record_in_repair($pdo, $record_id);
        } else {
            [$salesperson_selected, $custom_name, $date, $action_errors] = validate_action_form($_POST, $salespersons);

            if (empty($action_errors)) {
                try {
                    $pdo->beginTransaction();

                    $salesperson_id = resolve_salesperson_id($pdo, $salesperson_selected, $custom_name);

                    if ($action === 'dispatch') {
                        dispatch_record($pdo, $record_id, $salesperson_id, $date);
                        $pdo->commit();
                    } elseif ($action === 'retrieve') {
                        retrieve_record($pdo, $record_id, $salesperson_id, $date);
                        $pdo->commit();
                    } else {
                        $receipt_id = return_record($pdo, $record_id, $salesperson_id, $date);
                        $pdo->commit();

                        if ($receipt_id !== null) {
                            header('Location: print_receipt.php?receipt_id=' . $receipt_id);
                            exit;
                        }
                    }
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    log_exception($e);
                }
            }
        }
    }

    header('Location: index.php');
    exit;
}

$pdo = get_db_connection();
$records = get_active_maintenance_records($pdo);

$overdue_count = 0;
$ready_count = 0;
$in_repair_count = 0;
foreach ($records as &$record_for_stats) {
    $record_for_stats['age_days'] = record_age_days($record_for_stats['date_given']);
    if ($record_for_stats['age_days'] > 7) {
        $overdue_count++;
    }
    if ($record_for_stats['status'] === 'ready_for_pickup') {
        $ready_count++;
    }
    if ($record_for_stats['status'] === 'in_repair') {
        $in_repair_count++;
    }
}
unset($record_for_stats);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><?= htmlspecialchars(t('dashboard_title')) ?></h1>
    <a href="add_record.php" class="btn btn-primary"><?= htmlspecialchars(t('btn_new_record')) ?></a>
</div>

<?php if (!empty($records)): ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-card-value"><?= count($records) ?></div>
                <div class="stat-card-label"><?= htmlspecialchars(t('stat_active_items')) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-card-danger">
                <div class="stat-card-value"><?= $overdue_count ?></div>
                <div class="stat-card-label"><?= htmlspecialchars(t('stat_overdue')) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-card-value"><?= $ready_count ?></div>
                <div class="stat-card-label"><?= htmlspecialchars(t('status_ready_for_pickup')) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-card-value"><?= $in_repair_count ?></div>
                <div class="stat-card-label"><?= htmlspecialchars(t('status_in_repair')) ?></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($records)): ?>
            <div class="empty-state">
                <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/>
                </svg>
                <p class="empty-state-title"><?= htmlspecialchars(t('dashboard_empty')) ?></p>
                <p class="text-muted"><?= htmlspecialchars(t('dashboard_empty_subtitle')) ?></p>
                <a href="add_record.php" class="btn btn-primary"><?= htmlspecialchars(t('btn_add_first_record')) ?></a>
            </div>
        <?php else: ?>
            <div class="p-3 border-bottom">
                <input
                    type="search"
                    class="form-control"
                    id="dashboard_search"
                    placeholder="<?= htmlspecialchars(t('dashboard_search_placeholder')) ?>"
                    aria-label="<?= htmlspecialchars(t('dashboard_search_placeholder')) ?>"
                >
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="dashboard_table">
                    <thead class="table-light">
                        <tr>
                            <th><?= htmlspecialchars(t('field_owner_name')) ?></th>
                            <th><?= htmlspecialchars(t('field_owner_phone')) ?></th>
                            <th><?= htmlspecialchars(t('field_item')) ?></th>
                            <th><?= htmlspecialchars(t('field_salesperson')) ?></th>
                            <th><?= htmlspecialchars(t('field_date_given')) ?></th>
                            <th><?= htmlspecialchars(t('th_age')) ?></th>
                            <th><?= htmlspecialchars(t('field_status')) ?></th>
                            <th><?= htmlspecialchars(t('th_actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <?php
                            $item_label = format_item_label($record);
                            $search_haystack = mb_strtolower($record['owner_name'] . ' ' . ($record['owner_phone'] ?? ''));
                            $phone_href = $record['owner_phone'] ? 'tel:' . preg_replace('/[^0-9+]/', '', $record['owner_phone']) : null;
                            ?>
                            <tr data-search="<?= htmlspecialchars($search_haystack) ?>">
                                <td><?= htmlspecialchars($record['owner_name']) ?></td>
                                <td>
                                    <?php if ($phone_href): ?>
                                        <a href="<?= htmlspecialchars($phone_href) ?>"><?= htmlspecialchars($record['owner_phone']) ?></a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($item_label) ?></td>
                                <td><?= htmlspecialchars($record['salesperson_name']) ?></td>
                                <td><?= htmlspecialchars(date('M j, Y', strtotime($record['date_given']))) ?></td>
                                <td><span class="age-badge <?= age_badge_class($record['age_days']) ?>"><?= htmlspecialchars(format_age_label($record['age_days'])) ?></span></td>
                                <td><span class="badge <?= status_badge_class($record['status']) ?>"><?= htmlspecialchars(t('status_' . $record['status'])) ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                        <?php if ($record['status'] === 'pending_dispatch'): ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-action-btn
                                                data-action="dispatch"
                                                data-record-id="<?= (int) $record['id'] ?>"
                                                data-modal-title="<?= htmlspecialchars(t('action_dispatch_title')) ?>"
                                                data-submit-label="<?= htmlspecialchars(t('btn_confirm')) ?>"
                                            ><?= htmlspecialchars(t('btn_dispatch')) ?></button>
                                        <?php endif; ?>

                                        <?php if ($record['status'] === 'with_technician'): ?>
                                            <form method="post" action="index.php" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="mark_in_repair">
                                                <input type="hidden" name="record_id" value="<?= (int) $record['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary"><?= htmlspecialchars(t('btn_mark_in_repair')) ?></button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($record['status'] === 'with_technician' || $record['status'] === 'in_repair'): ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-action-btn
                                                data-action="retrieve"
                                                data-record-id="<?= (int) $record['id'] ?>"
                                                data-modal-title="<?= htmlspecialchars(t('action_retrieve_title')) ?>"
                                                data-submit-label="<?= htmlspecialchars(t('btn_confirm')) ?>"
                                            ><?= htmlspecialchars(t('btn_retrieve')) ?></button>
                                        <?php endif; ?>

                                        <?php if ($record['status'] === 'ready_for_pickup'): ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-primary"
                                                data-action-btn
                                                data-action="return"
                                                data-record-id="<?= (int) $record['id'] ?>"
                                                data-modal-title="<?= htmlspecialchars(t('confirm_return_title')) ?>"
                                                data-submit-label="<?= htmlspecialchars(t('btn_confirm_return')) ?>"
                                            ><?= htmlspecialchars(t('btn_return')) ?></button>
                                        <?php endif; ?>

                                        <a
                                            href="view_record.php?record_id=<?= (int) $record['id'] ?>"
                                            class="icon-btn"
                                            title="<?= htmlspecialchars(t('th_view')) ?>"
                                            aria-label="<?= htmlspecialchars(t('th_view')) ?>"
                                        ><?= icon_eye_svg() ?></a>
                                        <?php if (!empty($record['handover_receipt_id'])): ?>
                                            <a
                                                href="print_receipt.php?receipt_id=<?= (int) $record['handover_receipt_id'] ?>"
                                                class="icon-btn"
                                                title="<?= htmlspecialchars(t('btn_print')) ?>"
                                                aria-label="<?= htmlspecialchars(t('btn_print')) ?>"
                                            ><?= icon_printer_svg() ?></a>
                                        <?php else: ?>
                                            <span class="icon-btn disabled" aria-hidden="true"><?= icon_printer_svg() ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="dashboard_no_results_row" class="d-none">
                            <td colspan="8" class="text-muted text-center p-4"><?= htmlspecialchars(t('no_search_results')) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="action_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="index.php" id="action_modal_form">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="action_modal_title"></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" id="action_modal_action" value="">
                    <input type="hidden" name="record_id" id="action_modal_record_id" value="">

                    <div class="alert alert-warning d-none" id="action_modal_return_note"><?= htmlspecialchars(t('confirm_return_body')) ?></div>

                    <div class="mb-3">
                        <label for="action_modal_salesperson" class="form-label"><?= htmlspecialchars(t('field_salesperson')) ?></label>
                        <select class="form-select" id="action_modal_salesperson" name="salesperson_id" required>
                            <option value="" disabled selected><?= htmlspecialchars(t('select_placeholder')) ?></option>
                            <?php foreach ($salespersons as $person): ?>
                                <option value="<?= (int) $person['id'] ?>"><?= htmlspecialchars($person['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="other"><?= htmlspecialchars(t('salesperson_other')) ?></option>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="action_modal_custom_group">
                        <label for="action_modal_custom_name" class="form-label"><?= htmlspecialchars(t('field_custom_salesperson_name')) ?></label>
                        <input type="text" class="form-control" id="action_modal_custom_name" name="custom_salesperson_name">
                    </div>

                    <div class="mb-0">
                        <label for="action_modal_date" class="form-label"><?= htmlspecialchars(t('field_date')) ?></label>
                        <input type="date" class="form-control" id="action_modal_date" name="action_date" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= htmlspecialchars(t('btn_cancel')) ?></button>
                    <button type="submit" class="btn btn-primary" id="action_modal_submit"></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
