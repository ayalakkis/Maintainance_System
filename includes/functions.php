<?php
/**
 * Shared helper functions.
 */

require_once __DIR__ . '/../config/db.php';

const SUPPORTED_LANGUAGES = ['en', 'ar'];

/**
 * Starts the session if it hasn't been started yet. Safe to call multiple
 * times and from anywhere, including before any HTML output.
 */
function ensure_session_started(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Returns the active language code ('en' or 'ar'), read from the session
 * (set by the language toggle) or a ?lang= query param, defaulting to 'en'.
 * Starts the session if it hasn't been started yet.
 */
function get_current_lang_code(): string
{
    ensure_session_started();

    $lang_code = $_SESSION['lang'] ?? $_GET['lang'] ?? 'en';

    return in_array($lang_code, SUPPORTED_LANGUAGES, true) ? $lang_code : 'en';
}

/**
 * Loads (and caches for the rest of the request) the translation array for
 * the active language.
 */
function get_lang_strings(): array
{
    static $cache = [];

    $lang_code = get_current_lang_code();

    if (!isset($cache[$lang_code])) {
        $cache[$lang_code] = require __DIR__ . "/lang/{$lang_code}.php";
    }

    return $cache[$lang_code];
}

/**
 * Translates a UI string key for the active language. Falls back to the
 * key itself when no translation exists, so missing strings are obvious
 * instead of silently blank.
 */
function t(string $key): string
{
    $strings = get_lang_strings();

    return $strings[$key] ?? $key;
}

/**
 * If the request carries ?set_lang=en|ar, stores it in the session and
 * redirects back to the current URL (minus that param, keeping any other
 * query string intact) so the page re-renders in the new language. Must be
 * called before any HTML output.
 */
function handle_language_switch(): void
{
    ensure_session_started();

    if (!isset($_GET['set_lang']) || !in_array($_GET['set_lang'], SUPPORTED_LANGUAGES, true)) {
        return;
    }

    $_SESSION['lang'] = $_GET['set_lang'];

    $query = $_GET;
    unset($query['set_lang']);
    $path = strtok($_SERVER['REQUEST_URI'], '?');
    $redirect = $path . (!empty($query) ? '?' . http_build_query($query) : '');

    header('Location: ' . $redirect);
    exit;
}

/**
 * Builds the URL for a language-toggle link: the current path + query
 * string with set_lang swapped to $target_lang.
 */
function build_lang_switch_url(string $target_lang): string
{
    $path = strtok($_SERVER['REQUEST_URI'], '?');
    $query = $_GET;
    unset($query['set_lang']);
    $query['set_lang'] = $target_lang;

    return $path . '?' . http_build_query($query);
}

/**
 * Returns the current session's CSRF token, generating one on first call.
 * Must be called before any HTML output (it starts the session).
 */
function csrf_token(): string
{
    ensure_session_started();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Renders a hidden <input> carrying the current CSRF token, for embedding
 * directly inside a <form>.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Verifies a submitted CSRF token against the one stored in the session.
 * Constant-time comparison to avoid leaking the token via timing.
 */
function verify_csrf_token(?string $submitted_token): bool
{
    ensure_session_started();

    if (empty($_SESSION['csrf_token']) || $submitted_token === null || $submitted_token === '') {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $submitted_token);
}

/**
 * Appends a timestamped line describing $e to storage/logs/app.log (created
 * on first use), so failures caught and handled gracefully in the UI are
 * still visible to whoever operates the app.
 */
function log_exception(Throwable $e): void
{
    $log_dir = __DIR__ . '/../storage/logs';

    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0775, true);
    }

    $line = sprintf(
        '[%s] %s: %s in %s:%d%s',
        date('Y-m-d H:i:s'),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        PHP_EOL
    );

    error_log($line, 3, $log_dir . '/app.log');
}

/**
 * Returns all item types as [id => ..., name => ...] rows.
 */
function get_item_types(): array
{
    $pdo = get_db_connection();
    $stmt = $pdo->query('SELECT id, name FROM item_types ORDER BY id');
    return $stmt->fetchAll();
}

/**
 * Returns all salespersons as [id => ..., name => ..., is_custom => ...] rows.
 */
function get_salespersons(): array
{
    $pdo = get_db_connection();
    $stmt = $pdo->query('SELECT id, name, is_custom FROM salespersons ORDER BY name');
    return $stmt->fetchAll();
}

/**
 * Generates the next receipt number for the current year, e.g. ESR-2026-0007.
 * Locks on the year's existing rows within the caller's transaction to avoid
 * duplicate numbers under concurrent requests.
 */
function generate_receipt_number(PDO $pdo): string
{
    $year = date('Y');
    $prefix = "ESR-{$year}-";

    $stmt = $pdo->prepare(
        "SELECT receipt_number FROM receipts
         WHERE receipt_number LIKE :prefix
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute(['prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();

    $nextSeq = 1;
    if ($last !== false) {
        $lastSeq = (int) substr($last, strlen($prefix));
        $nextSeq = $lastSeq + 1;
    }

    return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
}

/**
 * Fetches a single receipt joined with its record, item type, inverter size,
 * and the salesperson who issued *that receipt* (the intake salesperson for
 * a handover receipt, the salesperson who processed the return for a return
 * receipt - joined on receipts.printed_by, not the record's intake
 * salesperson, so each receipt shows who actually issued it). Returns null
 * when the receipt does not exist.
 */
function get_receipt_details(PDO $pdo, int $receipt_id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
            r.id AS receipt_id,
            r.receipt_number,
            r.receipt_type,
            r.printed_at,
            mr.id AS record_id,
            mr.owner_name,
            mr.owner_phone,
            mr.date_given,
            mr.date_returned,
            mr.status,
            mr.battery_capacity_kw,
            mr.inverter_size,
            it.name AS item_type_name,
            sp.name AS salesperson_name
         FROM receipts r
         JOIN maintenance_records mr ON mr.id = r.record_id
         JOIN item_types it ON it.id = mr.item_type_id
         JOIN salespersons sp ON sp.id = r.printed_by
         WHERE r.id = :receipt_id'
    );
    $stmt->execute(['receipt_id' => $receipt_id]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Returns active maintenance records (anything not yet returned to the
 * customer), joined with item type, inverter size, and the salesperson
 * behind each of the four process actions (intake, dispatch, retrieval -
 * the latter two nullable until they happen), plus the id of the handover
 * receipt, ordered by longest-outstanding first.
 */
function get_active_maintenance_records(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT
            mr.id,
            mr.owner_name,
            mr.owner_phone,
            mr.date_given,
            mr.date_dispatched,
            mr.date_retrieved,
            mr.status,
            mr.battery_capacity_kw,
            mr.inverter_size,
            it.name AS item_type_name,
            sp.name AS salesperson_name,
            dispatcher.name AS dispatched_by_name,
            retriever.name AS picked_up_by_name,
            h.id AS handover_receipt_id
         FROM maintenance_records mr
         JOIN item_types it ON it.id = mr.item_type_id
         JOIN salespersons sp ON sp.id = mr.salesperson_id
         LEFT JOIN salespersons dispatcher ON dispatcher.id = mr.dispatched_by_salesperson_id
         LEFT JOIN salespersons retriever ON retriever.id = mr.picked_up_by_salesperson_id
         LEFT JOIN receipts h ON h.record_id = mr.id AND h.receipt_type = "handover"
         WHERE mr.status != "returned_to_customer"
         ORDER BY mr.date_given ASC, mr.id ASC'
    );

    return $stmt->fetchAll();
}

/**
 * Searches the full historical archive of maintenance records with optional
 * filters. Every value is bound via a prepared statement placeholder - never
 * concatenated into the SQL - regardless of which filters are present.
 *
 * Recognized $filters keys (all optional): q, item_type_id, status,
 * salesperson_id, date_from, date_to.
 */
function search_records(PDO $pdo, array $filters): array
{
    $sql = 'SELECT
                mr.id,
                mr.owner_name,
                mr.owner_phone,
                mr.date_given,
                mr.date_dispatched,
                mr.date_retrieved,
                mr.date_returned,
                mr.status,
                mr.battery_capacity_kw,
                mr.inverter_size,
                it.name AS item_type_name,
                sp.name AS salesperson_name,
                dispatcher.name AS dispatched_by_name,
                retriever.name AS picked_up_by_name,
                returner.name AS returned_by_name,
                h.id AS handover_receipt_id,
                rt.id AS return_receipt_id
            FROM maintenance_records mr
            JOIN item_types it ON it.id = mr.item_type_id
            JOIN salespersons sp ON sp.id = mr.salesperson_id
            LEFT JOIN salespersons dispatcher ON dispatcher.id = mr.dispatched_by_salesperson_id
            LEFT JOIN salespersons retriever ON retriever.id = mr.picked_up_by_salesperson_id
            LEFT JOIN salespersons returner ON returner.id = mr.returned_by_salesperson_id
            LEFT JOIN receipts h ON h.record_id = mr.id AND h.receipt_type = "handover"
            LEFT JOIN receipts rt ON rt.record_id = mr.id AND rt.receipt_type = "return"
            WHERE 1 = 1';

    $params = [];

    if (!empty($filters['q'])) {
        // Each occurrence needs its own placeholder name: PDO's native
        // (non-emulated) prepares don't allow reusing one named parameter
        // more than once in the same query.
        $sql .= ' AND (mr.owner_name LIKE :q_owner OR mr.owner_phone LIKE :q_phone
                   OR h.receipt_number LIKE :q_handover OR rt.receipt_number LIKE :q_return)';
        $like = '%' . $filters['q'] . '%';
        $params['q_owner'] = $like;
        $params['q_phone'] = $like;
        $params['q_handover'] = $like;
        $params['q_return'] = $like;
    }

    if (!empty($filters['item_type_id'])) {
        $sql .= ' AND mr.item_type_id = :item_type_id';
        $params['item_type_id'] = $filters['item_type_id'];
    }

    if (!empty($filters['status'])) {
        $sql .= ' AND mr.status = :status';
        $params['status'] = $filters['status'];
    }

    if (!empty($filters['salesperson_id'])) {
        $sql .= ' AND mr.salesperson_id = :salesperson_id';
        $params['salesperson_id'] = $filters['salesperson_id'];
    }

    if (!empty($filters['date_from'])) {
        $sql .= ' AND mr.date_given >= :date_from';
        $params['date_from'] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $sql .= ' AND mr.date_given <= :date_to';
        $params['date_to'] = $filters['date_to'];
    }

    $sql .= ' ORDER BY mr.date_given DESC, mr.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Fetches every process-action "event" (intake, dispatch, retrieval, return)
 * whose date falls within [$range_start, $range_end] (inclusive, Y-m-d), for
 * plotting on the calendar. A single record contributes up to 4 events, one
 * per action that has actually happened, each attributed to whichever
 * salesperson performed it - so the whole lifecycle of an item shows up
 * across however many days it actually spans. Supports the same free-text
 * 'q' filter (owner name/phone/receipt number) as search_records().
 *
 * Returns a flat, date-then-id sorted list of
 * [id, owner_name, status, action, event_date, actor_name] rows, where
 * action is one of 'given', 'dispatched', 'retrieved', 'returned'.
 */
function get_calendar_events(PDO $pdo, string $range_start, string $range_end, array $filters = []): array
{
    $sql = 'SELECT
                mr.id,
                mr.owner_name,
                mr.owner_phone,
                mr.status,
                mr.date_given,
                mr.date_dispatched,
                mr.date_retrieved,
                mr.date_returned,
                sp.name AS salesperson_name,
                dispatcher.name AS dispatched_by_name,
                retriever.name AS picked_up_by_name,
                returner.name AS returned_by_name
            FROM maintenance_records mr
            JOIN salespersons sp ON sp.id = mr.salesperson_id
            LEFT JOIN salespersons dispatcher ON dispatcher.id = mr.dispatched_by_salesperson_id
            LEFT JOIN salespersons retriever ON retriever.id = mr.picked_up_by_salesperson_id
            LEFT JOIN salespersons returner ON returner.id = mr.returned_by_salesperson_id
            LEFT JOIN receipts h ON h.record_id = mr.id AND h.receipt_type = "handover"
            LEFT JOIN receipts rt ON rt.record_id = mr.id AND rt.receipt_type = "return"
            WHERE (
                mr.date_given BETWEEN :range_start_given AND :range_end_given
                OR mr.date_dispatched BETWEEN :range_start_dispatched AND :range_end_dispatched
                OR mr.date_retrieved BETWEEN :range_start_retrieved AND :range_end_retrieved
                OR mr.date_returned BETWEEN :range_start_returned AND :range_end_returned
            )';

    $params = [
        'range_start_given'      => $range_start,
        'range_end_given'        => $range_end,
        'range_start_dispatched' => $range_start,
        'range_end_dispatched'   => $range_end,
        'range_start_retrieved'  => $range_start,
        'range_end_retrieved'    => $range_end,
        'range_start_returned'   => $range_start,
        'range_end_returned'     => $range_end,
    ];

    if (!empty($filters['q'])) {
        // Each occurrence needs its own placeholder name: PDO's native
        // (non-emulated) prepares don't allow reusing one named parameter
        // more than once in the same query.
        $sql .= ' AND (mr.owner_name LIKE :q_owner OR mr.owner_phone LIKE :q_phone
                   OR h.receipt_number LIKE :q_handover OR rt.receipt_number LIKE :q_return)';
        $like = '%' . $filters['q'] . '%';
        $params['q_owner'] = $like;
        $params['q_phone'] = $like;
        $params['q_handover'] = $like;
        $params['q_return'] = $like;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    // action => [date column, actor-name column]
    $action_fields = [
        'given'      => ['date_given', 'salesperson_name'],
        'dispatched' => ['date_dispatched', 'dispatched_by_name'],
        'retrieved'  => ['date_retrieved', 'picked_up_by_name'],
        'returned'   => ['date_returned', 'returned_by_name'],
    ];

    $events = [];
    foreach ($records as $record) {
        foreach ($action_fields as $action => [$date_field, $actor_field]) {
            $event_date = $record[$date_field];
            if ($event_date === null || $event_date < $range_start || $event_date > $range_end) {
                continue;
            }
            $events[] = [
                'id'         => $record['id'],
                'owner_name' => $record['owner_name'],
                'status'     => $record['status'],
                'action'     => $action,
                'event_date' => $event_date,
                'actor_name' => $record[$actor_field],
            ];
        }
    }

    usort($events, function (array $a, array $b): int {
        return ($a['event_date'] <=> $b['event_date']) ?: ($a['id'] <=> $b['id']);
    });

    return $events;
}

/**
 * Fetches a single maintenance record joined with item type, inverter size,
 * and the salesperson behind each of the four process actions: who took it
 * from the client, who dispatched it to the maintenance company, who
 * retrieved it back, and who returned it to the client (the latter three
 * nullable until those steps happen). Returns null when the record does not
 * exist.
 */
function get_record_details(PDO $pdo, int $record_id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
            mr.id,
            mr.item_type_id,
            mr.inverter_size,
            mr.battery_capacity_kw,
            mr.owner_name,
            mr.owner_phone,
            mr.salesperson_id,
            mr.dispatched_by_salesperson_id,
            mr.picked_up_by_salesperson_id,
            mr.returned_by_salesperson_id,
            mr.date_given,
            mr.date_dispatched,
            mr.date_retrieved,
            mr.date_returned,
            mr.status,
            mr.notes,
            mr.created_at,
            mr.updated_at,
            it.name AS item_type_name,
            sp.name AS salesperson_name,
            dispatcher.name AS dispatched_by_name,
            retriever.name AS picked_up_by_name,
            returner.name AS returned_by_name
         FROM maintenance_records mr
         JOIN item_types it ON it.id = mr.item_type_id
         JOIN salespersons sp ON sp.id = mr.salesperson_id
         LEFT JOIN salespersons dispatcher ON dispatcher.id = mr.dispatched_by_salesperson_id
         LEFT JOIN salespersons retriever ON retriever.id = mr.picked_up_by_salesperson_id
         LEFT JOIN salespersons returner ON returner.id = mr.returned_by_salesperson_id
         WHERE mr.id = :record_id'
    );
    $stmt->execute(['record_id' => $record_id]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Fetches every receipt (handover and/or return) issued for a record,
 * handover first.
 */
function get_receipts_for_record(PDO $pdo, int $record_id): array
{
    $stmt = $pdo->prepare(
        'SELECT id, receipt_type, receipt_number, printed_at
         FROM receipts
         WHERE record_id = :record_id
         ORDER BY FIELD(receipt_type, "handover", "return")'
    );
    $stmt->execute(['record_id' => $record_id]);

    return $stmt->fetchAll();
}

/**
 * Maps a maintenance_records.status value to its brand badge class
 * (defined in style.css), keeping the color scheme in one place instead
 * of duplicated per page.
 */
function status_badge_class(string $status): string
{
    $classes = [
        'pending_dispatch'     => 'badge-status-pending_dispatch',
        'with_technician'      => 'badge-status-with_technician',
        'in_repair'            => 'badge-status-in_repair',
        'ready_for_pickup'     => 'badge-status-ready_for_pickup',
        'returned_to_customer' => 'badge-status-returned_to_customer',
    ];

    return $classes[$status] ?? 'badge-status-pending_dispatch';
}

/**
 * Whole days elapsed between $date_given (Y-m-d) and today, floored at 0
 * so a record logged earlier today never shows as negative.
 */
function record_age_days(string $date_given): int
{
    $today = new DateTimeImmutable('today');
    $given = new DateTimeImmutable($date_given);

    return max(0, $today->diff($given)->days);
}

/**
 * Maps an age in days to the badge class (defined in style.css) used to
 * flag records that have been sitting too long: normal, warning (>7 days),
 * danger (>14 days).
 */
function age_badge_class(int $days): string
{
    if ($days > 14) {
        return 'age-badge-danger';
    }
    if ($days > 7) {
        return 'age-badge-warning';
    }
    return 'age-badge-normal';
}

/**
 * Renders an age in days as "Today", "1 day", or "N days" (translated).
 */
function format_age_label(int $days): string
{
    if ($days <= 0) {
        return t('age_today');
    }
    if ($days === 1) {
        return t('age_one_day');
    }
    return sprintf(t('age_days'), $days);
}

/**
 * Whether an item type name is one of the battery types (as opposed to
 * Inverter), which get a KW capacity field instead of a size field.
 */
function is_battery_item_type(?string $item_type_name): bool
{
    return in_array($item_type_name, ['Acid Battery', 'Lithium Battery'], true);
}

/**
 * Validates and normalizes the maintenance-record form fields shared by
 * add_record.php and edit_record.php (item type, inverter size / battery
 * capacity, salesperson, owner name/phone, date given).
 *
 * Returns a tuple: [$old, $errors, $is_inverter, $is_battery, $is_other_salesperson].
 * $old holds the trimmed/normalized submitted values (for redisplaying the
 * form), keyed the same whether or not each entry passed validation.
 */
function validate_maintenance_record_form(array $post, array $item_types, array $salespersons): array
{
    $errors = [];
    $old = [
        'item_type_id'              => $post['item_type_id'] ?? '',
        'inverter_size'             => trim($post['inverter_size'] ?? ''),
        'battery_capacity_kw'       => trim($post['battery_capacity_kw'] ?? ''),
        'salesperson_id'            => $post['salesperson_id'] ?? '',
        'custom_salesperson_name'   => trim($post['custom_salesperson_name'] ?? ''),
        'owner_name'                => trim($post['owner_name'] ?? ''),
        'owner_phone'               => trim($post['owner_phone'] ?? ''),
        'date_given'                => $post['date_given'] ?? '',
    ];

    // --- Item type -----------------------------------------------------
    $selected_item_type_name = null;
    foreach ($item_types as $type) {
        if ((string) $type['id'] === (string) $old['item_type_id']) {
            $selected_item_type_name = $type['name'];
            break;
        }
    }
    if ($old['item_type_id'] === '' || $selected_item_type_name === null) {
        $errors['item_type_id'] = t('error_item_type_required');
    }
    $is_inverter = $selected_item_type_name === 'Inverter';
    $is_battery = is_battery_item_type($selected_item_type_name);

    // --- Inverter size (only required when item type is Inverter) -----
    if ($is_inverter) {
        if ($old['inverter_size'] === '') {
            $errors['inverter_size'] = t('error_inverter_size_required');
        } elseif (mb_strlen($old['inverter_size']) > 20) {
            $errors['inverter_size'] = t('error_inverter_size_too_long');
        }
    } else {
        $old['inverter_size'] = '';
    }

    // --- Battery capacity (optional, only used when item type is a battery) ---
    if ($is_battery) {
        if ($old['battery_capacity_kw'] !== '' && !is_numeric($old['battery_capacity_kw'])) {
            $errors['battery_capacity_kw'] = t('error_battery_capacity_invalid');
        }
    } else {
        $old['battery_capacity_kw'] = '';
    }

    // --- Salesperson -----------------------------------------------------
    $salesperson_ids = array_map('strval', array_column($salespersons, 'id'));
    $is_other_salesperson = $old['salesperson_id'] === 'other';
    if ($old['salesperson_id'] === '') {
        $errors['salesperson_id'] = t('error_salesperson_required');
    } elseif ($is_other_salesperson) {
        if ($old['custom_salesperson_name'] === '') {
            $errors['custom_salesperson_name'] = t('error_custom_salesperson_required');
        }
    } elseif (!in_array((string) $old['salesperson_id'], $salesperson_ids, true)) {
        $errors['salesperson_id'] = t('error_salesperson_invalid');
    }

    // --- Owner name --------------------------------------------------
    if ($old['owner_name'] === '') {
        $errors['owner_name'] = t('error_owner_name_required');
    }

    // --- Owner phone (optional, loosely validated if provided) -------
    if ($old['owner_phone'] !== '' && !preg_match('/^[0-9+\-\s()]{5,30}$/', $old['owner_phone'])) {
        $errors['owner_phone'] = t('error_owner_phone_invalid');
    }

    // --- Date given ----------------------------------------------------
    $date_given_obj = DateTime::createFromFormat('Y-m-d', $old['date_given']);
    if (!$date_given_obj || $date_given_obj->format('Y-m-d') !== $old['date_given']) {
        $errors['date_given'] = t('error_date_given_invalid');
    }

    return [$old, $errors, $is_inverter, $is_battery, $is_other_salesperson];
}

/**
 * Resolves a submitted salesperson select value to a salespersons.id,
 * creating a new custom salesperson row first if $selected_value === 'other'.
 * Shared by every place a record action attributes itself to a salesperson
 * (intake, dispatch, retrieval, return).
 */
function resolve_salesperson_id(PDO $pdo, string $selected_value, string $custom_name): int
{
    if ($selected_value === 'other') {
        $stmt = $pdo->prepare('INSERT INTO salespersons (name, is_custom) VALUES (:name, 1)');
        $stmt->execute(['name' => $custom_name]);
        return (int) $pdo->lastInsertId();
    }

    return (int) $selected_value;
}

/**
 * Action 2: marks a record as handed to the maintenance company/technician.
 * No-op (0 rows affected) unless the record is currently 'pending_dispatch'.
 */
function dispatch_record(PDO $pdo, int $record_id, int $salesperson_id, string $date): void
{
    $stmt = $pdo->prepare(
        'UPDATE maintenance_records
         SET dispatched_by_salesperson_id = :salesperson_id,
             date_dispatched = :date,
             status = "with_technician"
         WHERE id = :record_id AND status = "pending_dispatch"'
    );
    $stmt->execute([
        'salesperson_id' => $salesperson_id,
        'date'           => $date,
        'record_id'      => $record_id,
    ]);
}

/**
 * Marks a record as actively being worked on. Purely informational - no
 * actor or date is attached, since it's the technician (not a salesperson)
 * doing this step. No-op unless the record is currently 'with_technician'.
 */
function mark_record_in_repair(PDO $pdo, int $record_id): void
{
    $stmt = $pdo->prepare(
        'UPDATE maintenance_records SET status = "in_repair" WHERE id = :record_id AND status = "with_technician"'
    );
    $stmt->execute(['record_id' => $record_id]);
}

/**
 * Action 3: marks a record as retrieved back from the maintenance
 * company/technician (now sitting at the shop, ready for the customer).
 * No-op unless the record is currently 'with_technician' or 'in_repair'.
 */
function retrieve_record(PDO $pdo, int $record_id, int $salesperson_id, string $date): void
{
    $stmt = $pdo->prepare(
        'UPDATE maintenance_records
         SET picked_up_by_salesperson_id = :salesperson_id,
             date_retrieved = :date,
             status = "ready_for_pickup"
         WHERE id = :record_id AND status IN ("with_technician", "in_repair")'
    );
    $stmt->execute([
        'salesperson_id' => $salesperson_id,
        'date'           => $date,
        'record_id'      => $record_id,
    ]);
}

/**
 * Action 4: marks a record as returned to the customer and issues a return
 * receipt printed by whichever salesperson handed it back. Returns the new
 * receipt's id, or null if the record wasn't in a returnable
 * ('ready_for_pickup') state - including if it doesn't exist.
 */
function return_record(PDO $pdo, int $record_id, int $salesperson_id, string $date): ?int
{
    $stmt = $pdo->prepare('SELECT status FROM maintenance_records WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $record_id]);
    $record = $stmt->fetch();

    if (!$record || $record['status'] !== 'ready_for_pickup') {
        return null;
    }

    $stmt = $pdo->prepare(
        'UPDATE maintenance_records
         SET status = "returned_to_customer",
             returned_by_salesperson_id = :salesperson_id,
             date_returned = :date
         WHERE id = :record_id'
    );
    $stmt->execute([
        'salesperson_id' => $salesperson_id,
        'date'           => $date,
        'record_id'      => $record_id,
    ]);

    $receipt_number = generate_receipt_number($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO receipts (record_id, receipt_type, receipt_number, printed_by)
         VALUES (:record_id, "return", :receipt_number, :printed_by)'
    );
    $stmt->execute([
        'record_id'      => $record_id,
        'receipt_number' => $receipt_number,
        'printed_by'     => $salesperson_id,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Validates the shared "who did it, and when" fields used by the dispatch,
 * retrieve, and return dashboard actions: a salesperson (existing or
 * 'other' + a custom name) and a not-in-the-future date. Returns
 * [$salesperson_selected, $custom_name, $date, $errors]; $errors is empty
 * when valid.
 */
function validate_action_form(array $post, array $salespersons): array
{
    $errors = [];
    $salesperson_selected = $post['salesperson_id'] ?? '';
    $custom_name = trim($post['custom_salesperson_name'] ?? '');
    $date = $post['action_date'] ?? '';

    $salesperson_ids = array_map('strval', array_column($salespersons, 'id'));
    if ($salesperson_selected === '') {
        $errors['salesperson_id'] = t('error_salesperson_required');
    } elseif ($salesperson_selected === 'other') {
        if ($custom_name === '') {
            $errors['custom_salesperson_name'] = t('error_custom_salesperson_required');
        }
    } elseif (!in_array((string) $salesperson_selected, $salesperson_ids, true)) {
        $errors['salesperson_id'] = t('error_salesperson_invalid');
    }

    $date_obj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
        $errors['action_date'] = t('error_date_given_invalid');
    }

    return [$salesperson_selected, $custom_name, $date, $errors];
}

/**
 * Trims a DECIMAL-as-string value's trailing zeros/decimal point for
 * display, e.g. "5.00" -> "5", "5.50" -> "5.5".
 */
function format_kw(string $value): string
{
    return rtrim(rtrim($value, '0'), '.');
}

/**
 * Builds the "Item (size)" label used across the dashboard, records,
 * view record, and receipt pages: appends the inverter size (KVA) or the
 * battery capacity (KW), whichever applies to that record.
 */
function format_item_label(array $row): string
{
    $label = $row['item_type_name'];

    if (!empty($row['inverter_size'])) {
        $label .= ' (' . $row['inverter_size'] . ')';
    } elseif (isset($row['battery_capacity_kw']) && $row['battery_capacity_kw'] !== null && $row['battery_capacity_kw'] !== '') {
        $label .= ' (' . format_kw((string) $row['battery_capacity_kw']) . ' KW)';
    }

    return $label;
}
