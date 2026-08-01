<?php
/**
 * Add Record - logs a new piece of equipment for maintenance and
 * automatically issues a handover receipt.
 */

require_once __DIR__ . '/../includes/functions.php';

$errors = [];
$old = [
    'item_type_id'                  => '',
    'inverter_size'                 => '',
    'battery_capacity_kw'           => '',
    'salesperson_id'                => '',
    'custom_salesperson_name'       => '',
    'owner_name'                    => '',
    'owner_phone'                   => '',
    'date_given'                    => date('Y-m-d'),
];

$item_types = get_item_types();
$salespersons = get_salespersons();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$old, $errors, $is_inverter, $is_battery] =
        validate_maintenance_record_form($_POST, $item_types, $salespersons);

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors['general'] = t('error_csrf');
    }

    if (empty($errors)) {
        $pdo = get_db_connection();
        try {
            $pdo->beginTransaction();

            $salesperson_id = resolve_salesperson_id($pdo, $old['salesperson_id'], $old['custom_salesperson_name']);

            $stmt = $pdo->prepare(
                'INSERT INTO maintenance_records
                    (item_type_id, inverter_size, battery_capacity_kw, owner_name, owner_phone, salesperson_id, date_given, status)
                 VALUES
                    (:item_type_id, :inverter_size, :battery_capacity_kw, :owner_name, :owner_phone, :salesperson_id, :date_given, "pending_dispatch")'
            );
            $stmt->execute([
                'item_type_id'                 => (int) $old['item_type_id'],
                'inverter_size'                => $is_inverter && $old['inverter_size'] !== '' ? $old['inverter_size'] : null,
                'battery_capacity_kw'          => $is_battery && $old['battery_capacity_kw'] !== '' ? (float) $old['battery_capacity_kw'] : null,
                'owner_name'                   => $old['owner_name'],
                'owner_phone'                  => $old['owner_phone'] !== '' ? $old['owner_phone'] : null,
                'salesperson_id'               => $salesperson_id,
                'date_given'                   => $old['date_given'],
            ]);
            $record_id = (int) $pdo->lastInsertId();

            $receipt_number = generate_receipt_number($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO receipts (record_id, receipt_type, receipt_number, printed_by)
                 VALUES (:record_id, "handover", :receipt_number, :printed_by)'
            );
            $stmt->execute([
                'record_id'      => $record_id,
                'receipt_number' => $receipt_number,
                'printed_by'     => $salesperson_id,
            ]);
            $receipt_id = (int) $pdo->lastInsertId();

            $pdo->commit();

            header('Location: print_receipt.php?receipt_id=' . $receipt_id);
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            log_exception($e);
            $errors['general'] = t('error_general');
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <h1 class="h4 mb-4"><?= htmlspecialchars(t('page_add_record_title')) ?></h1>

                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
                <?php endif; ?>

                <form method="post" action="add_record.php" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="item_type_id" class="form-label"><?= htmlspecialchars(t('field_item_type')) ?></label>
                        <select
                            class="form-select<?= isset($errors['item_type_id']) ? ' is-invalid' : '' ?>"
                            id="item_type_id"
                            name="item_type_id"
                            required
                        >
                            <option value="" disabled<?= $old['item_type_id'] === '' ? ' selected' : '' ?>><?= htmlspecialchars(t('select_placeholder')) ?></option>
                            <?php foreach ($item_types as $type): ?>
                                <option value="<?= (int) $type['id'] ?>"<?= (string) $old['item_type_id'] === (string) $type['id'] ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($type['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['item_type_id'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['item_type_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3<?= $is_inverter ?? false ? '' : ' d-none' ?>" id="inverter_size_group">
                        <label for="inverter_size" class="form-label"><?= htmlspecialchars(t('field_inverter_size')) ?></label>
                        <input
                            type="text"
                            maxlength="20"
                            class="form-control<?= isset($errors['inverter_size']) ? ' is-invalid' : '' ?>"
                            id="inverter_size"
                            name="inverter_size"
                            placeholder="<?= htmlspecialchars(t('inverter_size_placeholder')) ?>"
                            value="<?= htmlspecialchars($old['inverter_size']) ?>"
                        >
                        <?php if (isset($errors['inverter_size'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['inverter_size']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3<?= $is_battery ?? false ? '' : ' d-none' ?>" id="battery_capacity_group">
                        <label for="battery_capacity_kw" class="form-label"><?= htmlspecialchars(t('field_battery_capacity')) ?></label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control<?= isset($errors['battery_capacity_kw']) ? ' is-invalid' : '' ?>"
                            id="battery_capacity_kw"
                            name="battery_capacity_kw"
                            value="<?= htmlspecialchars($old['battery_capacity_kw']) ?>"
                        >
                        <?php if (isset($errors['battery_capacity_kw'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['battery_capacity_kw']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="salesperson_id" class="form-label"><?= htmlspecialchars(t('field_salesperson')) ?></label>
                        <select
                            class="form-select<?= isset($errors['salesperson_id']) ? ' is-invalid' : '' ?>"
                            id="salesperson_id"
                            name="salesperson_id"
                            required
                        >
                            <option value="" disabled<?= $old['salesperson_id'] === '' ? ' selected' : '' ?>><?= htmlspecialchars(t('select_placeholder')) ?></option>
                            <?php foreach ($salespersons as $person): ?>
                                <option value="<?= (int) $person['id'] ?>"<?= (string) $old['salesperson_id'] === (string) $person['id'] ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($person['name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="other"<?= $old['salesperson_id'] === 'other' ? ' selected' : '' ?>><?= htmlspecialchars(t('salesperson_other')) ?></option>
                        </select>
                        <?php if (isset($errors['salesperson_id'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['salesperson_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3<?= $old['salesperson_id'] === 'other' ? '' : ' d-none' ?>" id="custom_salesperson_group">
                        <label for="custom_salesperson_name" class="form-label"><?= htmlspecialchars(t('field_custom_salesperson_name')) ?></label>
                        <input
                            type="text"
                            class="form-control<?= isset($errors['custom_salesperson_name']) ? ' is-invalid' : '' ?>"
                            id="custom_salesperson_name"
                            name="custom_salesperson_name"
                            value="<?= htmlspecialchars($old['custom_salesperson_name']) ?>"
                        >
                        <?php if (isset($errors['custom_salesperson_name'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['custom_salesperson_name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="owner_name" class="form-label"><?= htmlspecialchars(t('field_owner_name')) ?></label>
                        <input
                            type="text"
                            class="form-control<?= isset($errors['owner_name']) ? ' is-invalid' : '' ?>"
                            id="owner_name"
                            name="owner_name"
                            value="<?= htmlspecialchars($old['owner_name']) ?>"
                            required
                        >
                        <?php if (isset($errors['owner_name'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['owner_name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="owner_phone" class="form-label"><?= htmlspecialchars(t('field_owner_phone')) ?></label>
                        <input
                            type="text"
                            class="form-control<?= isset($errors['owner_phone']) ? ' is-invalid' : '' ?>"
                            id="owner_phone"
                            name="owner_phone"
                            value="<?= htmlspecialchars($old['owner_phone']) ?>"
                        >
                        <?php if (isset($errors['owner_phone'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['owner_phone']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label for="date_given" class="form-label"><?= htmlspecialchars(t('field_date_given')) ?></label>
                        <input
                            type="date"
                            class="form-control<?= isset($errors['date_given']) ? ' is-invalid' : '' ?>"
                            id="date_given"
                            name="date_given"
                            value="<?= htmlspecialchars($old['date_given']) ?>"
                            required
                        >
                        <?php if (isset($errors['date_given'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['date_given']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><?= htmlspecialchars(t('btn_save')) ?></button>
                        <a href="index.php" class="btn btn-outline-secondary"><?= htmlspecialchars(t('btn_cancel')) ?></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
