<?php
/**
 * Edit Record - corrections to an existing maintenance record (e.g. a typo
 * in the owner's name or phone). Same fields and validation as
 * add_record.php, but updates in place and never touches receipts.
 */

require_once __DIR__ . '/../includes/functions.php';

$record_id = filter_input(INPUT_GET, 'record_id', FILTER_VALIDATE_INT);

$pdo = get_db_connection();
$error_page = null;
$existing = null;

if (!$record_id || $record_id < 1) {
    $error_page = t('record_id_invalid');
} else {
    $existing = get_record_details($pdo, $record_id);
    if ($existing === null) {
        $error_page = t('record_not_found');
    }
}

$item_types = get_item_types();
$salespersons = get_salespersons();

$errors = [];
$old = [
    'item_type_id'                  => $existing['item_type_id'] ?? '',
    'inverter_size'                 => $existing['inverter_size'] ?? '',
    'battery_capacity_kw'           => isset($existing['battery_capacity_kw']) && $existing['battery_capacity_kw'] !== null ? format_kw((string) $existing['battery_capacity_kw']) : '',
    'salesperson_id'                => $existing['salesperson_id'] ?? '',
    'custom_salesperson_name'       => '',
    'owner_name'                    => $existing['owner_name'] ?? '',
    'owner_phone'                   => $existing['owner_phone'] ?? '',
    'date_given'                    => $existing['date_given'] ?? date('Y-m-d'),
];

if ($error_page === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    [$old, $errors, $is_inverter, $is_battery] =
        validate_maintenance_record_form($_POST, $item_types, $salespersons);

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors['general'] = t('error_csrf');
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $salesperson_id = resolve_salesperson_id($pdo, $old['salesperson_id'], $old['custom_salesperson_name']);

            $stmt = $pdo->prepare(
                'UPDATE maintenance_records
                 SET item_type_id = :item_type_id,
                     inverter_size = :inverter_size,
                     battery_capacity_kw = :battery_capacity_kw,
                     owner_name = :owner_name,
                     owner_phone = :owner_phone,
                     salesperson_id = :salesperson_id,
                     date_given = :date_given
                 WHERE id = :record_id'
            );
            $stmt->execute([
                'item_type_id'                 => (int) $old['item_type_id'],
                'inverter_size'                => $is_inverter && $old['inverter_size'] !== '' ? $old['inverter_size'] : null,
                'battery_capacity_kw'          => $is_battery && $old['battery_capacity_kw'] !== '' ? (float) $old['battery_capacity_kw'] : null,
                'owner_name'                   => $old['owner_name'],
                'owner_phone'                  => $old['owner_phone'] !== '' ? $old['owner_phone'] : null,
                'salesperson_id'               => $salesperson_id,
                'date_given'                   => $old['date_given'],
                'record_id'                    => $record_id,
            ]);

            $pdo->commit();

            header('Location: view_record.php?record_id=' . $record_id);
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
        <?php if ($error_page !== null): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_page) ?></div>
            <a href="records.php" class="btn btn-outline-secondary"><?= htmlspecialchars(t('btn_back')) ?></a>
        <?php else: ?>
            <div class="card">
                <div class="card-body p-4">
                    <h1 class="h4 mb-4"><?= htmlspecialchars(t('page_edit_record_title')) ?></h1>

                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
                    <?php endif; ?>

                    <form method="post" action="edit_record.php?record_id=<?= (int) $record_id ?>" novalidate>
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

                        <div class="mb-3<?= ($is_inverter ?? ($old['inverter_size'] !== '')) ? '' : ' d-none' ?>" id="inverter_size_group">
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

                        <div class="mb-3<?= ($is_battery ?? ($old['battery_capacity_kw'] !== '')) ? '' : ' d-none' ?>" id="battery_capacity_group">
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
                            <a href="view_record.php?record_id=<?= (int) $record_id ?>" class="btn btn-outline-secondary"><?= htmlspecialchars(t('btn_cancel')) ?></a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
