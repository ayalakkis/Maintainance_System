<?php
/**
 * Print Receipt - printable handover/return receipt for a maintenance record.
 * Generic over receipts.receipt_type; renders identically for either value.
 */

require_once __DIR__ . '/../includes/functions.php';

$receipt_id = filter_input(INPUT_GET, 'receipt_id', FILTER_VALIDATE_INT);

$receipt = null;
$error = null;

if (!$receipt_id || $receipt_id < 1) {
    $error = t('receipt_id_invalid');
} else {
    $pdo = get_db_connection();
    $receipt = get_receipt_details($pdo, $receipt_id);
    if ($receipt === null) {
        $error = t('receipt_not_found');
    }
}

if ($receipt !== null) {
    $is_return = $receipt['receipt_type'] === 'return';
    $type_label = $is_return ? t('receipt_type_return') : t('receipt_type_handover');
    $relevant_date = $is_return ? $receipt['date_returned'] : $receipt['date_given'];
    $formatted_date = $relevant_date ? date('M j, Y', strtotime($relevant_date)) : '-';

    $item_label = format_item_label($receipt);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <?php if ($error !== null): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <a href="index.php" class="btn btn-outline-secondary"><?= htmlspecialchars(t('btn_back')) ?></a>
        <?php else: ?>
            <div class="card receipt-container">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <?= esolar_logo_mark_svg('receipt-logo-mark mb-2') ?>
                        <h1 class="h5 mb-0"><?= htmlspecialchars(t('receipt_page_header')) ?></h1>
                    </div>

                    <dl class="row mb-0">
                        <dt class="col-5"><?= htmlspecialchars(t('receipt_no_label')) ?></dt>
                        <dd class="col-7"><?= htmlspecialchars($receipt['receipt_number']) ?></dd>

                        <dt class="col-5"><?= htmlspecialchars(t('receipt_type_label')) ?></dt>
                        <dd class="col-7"><?= htmlspecialchars($type_label) ?></dd>

                        <dt class="col-5"><?= htmlspecialchars(t('field_owner_name')) ?></dt>
                        <dd class="col-7"><?= htmlspecialchars($receipt['owner_name']) ?></dd>

                        <dt class="col-5"><?= htmlspecialchars(t('field_owner_phone')) ?></dt>
                        <dd class="col-7"><?= htmlspecialchars($receipt['owner_phone'] ?? '-') ?></dd>

                        <dt class="col-5"><?= htmlspecialchars(t('field_item')) ?></dt>
                        <dd class="col-7"><?= htmlspecialchars($item_label) ?></dd>

                        <dt class="col-5"><?= htmlspecialchars(t('field_salesperson')) ?></dt>
                        <dd class="col-7"><?= htmlspecialchars($receipt['salesperson_name']) ?></dd>

                        <dt class="col-5"><?= htmlspecialchars(t('field_date')) ?></dt>
                        <dd class="col-7"><?= htmlspecialchars($formatted_date) ?></dd>
                    </dl>

                    <div class="mt-5 pt-4 border-top">
                        <p class="mb-0">
                            <?= htmlspecialchars(t('signature_label')) ?>:
                            <span class="receipt-signature-line">&nbsp;</span>
                        </p>
                    </div>

                    <div class="mt-4 d-flex gap-2 justify-content-center no-print">
                        <button type="button" class="btn btn-primary" id="print_btn"><?= htmlspecialchars(t('btn_print')) ?></button>
                        <a href="index.php" class="btn btn-outline-secondary"><?= htmlspecialchars(t('btn_back')) ?></a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
