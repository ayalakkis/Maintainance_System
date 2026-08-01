<?php
/**
 * Calendar - month grid of every process-action event (intake, dispatch,
 * retrieval, return) across all maintenance records. A single record can
 * show up on up to 4 different days, one per action that actually happened
 * on that day, each attributed to whichever salesperson performed it -
 * rather than one chip per record colored by its current status. Supports
 * a text search and jumping to any month via a single ?ym=YYYY-MM param
 * (shareable, like records.php).
 */

require_once __DIR__ . '/../includes/functions.php';

$q = trim($_GET['q'] ?? '');

$ym = $_GET['ym'] ?? date('Y-m');
$first = DateTime::createFromFormat('Y-m-d', $ym . '-01');
if ($first === false || $first->format('Y-m') !== $ym) {
    $ym = date('Y-m');
    $first = DateTime::createFromFormat('Y-m-d', $ym . '-01');
}

$year = (int) $first->format('Y');
$month = (int) $first->format('n');
$days_in_month = (int) $first->format('t');
$first_weekday = (int) $first->format('w');
$last_day_str = $first->format('Y-m') . '-' . str_pad((string) $days_in_month, 2, '0', STR_PAD_LEFT);

$prev_ym = (clone $first)->modify('-1 month')->format('Y-m');
$next_ym = (clone $first)->modify('+1 month')->format('Y-m');
$today_ym = date('Y-m');
$today_day = (int) date('j');

function calendar_url(string $ym, string $q): string
{
    $params = ['ym' => $ym];
    if ($q !== '') {
        $params['q'] = $q;
    }
    return 'calendar.php?' . http_build_query($params);
}

$filters = [
    'date_from' => $first->format('Y-m-d'),
    'date_to'   => $last_day_str,
];
if ($q !== '') {
    $filters['q'] = $q;
}

$pdo = get_db_connection();
$events = get_calendar_events($pdo, $filters['date_from'], $filters['date_to'], $filters);

$action_labels = [
    'given'      => t('calendar_action_given'),
    'dispatched' => t('calendar_action_dispatched'),
    'retrieved'  => t('calendar_action_retrieved'),
    'returned'   => t('calendar_action_returned'),
];
$action_chip_classes = [
    'given'      => 'calendar-chip-given',
    'dispatched' => 'calendar-chip-dispatched',
    'retrieved'  => 'calendar-chip-retrieved',
    'returned'   => 'calendar-chip-returned',
];

$events_by_day = [];
foreach ($events as $event) {
    $day = (int) date('j', strtotime($event['event_date']));
    $events_by_day[$day][] = $event;
}

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="h4 mb-4"><?= htmlspecialchars(t('calendar_page_title')) ?></h1>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="calendar.php" class="row g-3 align-items-end">
            <div class="col-md-5">
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
            <div class="col-md-3">
                <label for="ym" class="form-label"><?= htmlspecialchars(t('field_month')) ?></label>
                <input type="month" class="form-control" id="ym" name="ym" value="<?= htmlspecialchars($ym) ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= htmlspecialchars(t('btn_search')) ?></button>
                <a href="calendar.php" class="btn btn-outline-secondary"><?= htmlspecialchars(t('btn_clear')) ?></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="<?= htmlspecialchars(calendar_url($prev_ym, $q)) ?>" class="btn btn-outline-secondary btn-sm">&laquo; <?= htmlspecialchars(t('btn_prev_month')) ?></a>
            <div class="text-center">
                <div class="h5 mb-0"><?= htmlspecialchars(t('month_' . $month)) ?> <?= $year ?></div>
                <a href="<?= htmlspecialchars(calendar_url($today_ym, $q)) ?>" class="small"><?= htmlspecialchars(t('btn_today')) ?></a>
            </div>
            <a href="<?= htmlspecialchars(calendar_url($next_ym, $q)) ?>" class="btn btn-outline-secondary btn-sm"><?= htmlspecialchars(t('btn_next_month')) ?> &raquo;</a>
        </div>

        <div class="calendar-grid">
            <?php for ($weekday = 0; $weekday <= 6; $weekday++): ?>
                <div class="calendar-weekday"><?= htmlspecialchars(t('day_' . $weekday)) ?></div>
            <?php endfor; ?>

            <?php for ($i = 0; $i < $first_weekday; $i++): ?>
                <div class="calendar-day calendar-day-empty"></div>
            <?php endfor; ?>

            <?php for ($day = 1; $day <= $days_in_month; $day++): ?>
                <?php $is_today = $ym === $today_ym && $day === $today_day; ?>
                <div class="calendar-day<?= $is_today ? ' calendar-day-today' : '' ?>">
                    <div class="calendar-day-number"><?= $day ?></div>
                    <?php foreach ($events_by_day[$day] ?? [] as $event): ?>
                        <?php
                        $action_label = $action_labels[$event['action']];
                        $chip_title = $event['actor_name'] !== null
                            ? sprintf('%s: %s (%s)', $action_label, $event['owner_name'], $event['actor_name'])
                            : sprintf('%s: %s', $action_label, $event['owner_name']);
                        ?>
                        <a
                            href="view_record.php?record_id=<?= (int) $event['id'] ?>"
                            class="calendar-chip <?= $action_chip_classes[$event['action']] ?>"
                            title="<?= htmlspecialchars($chip_title) ?>"
                        ><?= htmlspecialchars($action_label) ?>: <?= htmlspecialchars($event['owner_name']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
