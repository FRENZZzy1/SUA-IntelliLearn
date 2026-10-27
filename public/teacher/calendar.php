<?php
include 'assets/api/calendar_functions.php';

$weekdayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

// Data for the JS agenda panel, keyed by date, so clicking a day filters
// client-side without a page reload.
$itemsByDateJson = json_encode($itemsByDate, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar · SUA IntelliLearn</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/calendar.css">
</head>
<body>

<?php include '../../includes/teachers_sidebar.php'; ?>

<main class="main-content" id="dashMain">

    <?php include '../../includes/teacher_header.php'; ?>

    <div class="dash-page-title">
        <h1 class="dash-title">Calendar</h1>
        <p class="dash-subtitle">Assignment and quiz deadlines, and what's waiting to be graded</p>
    </div>

    <!-- Stats Summary Bar -->
    <section class="stats-bar">
        <div class="stat-card">
            <div class="stat-card__icon stat-card__icon--blue">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-card__info">
                <span class="stat-card__value"><?= (int) $totalDueMonth ?></span>
                <span class="stat-card__label">Deadlines This Month</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card__icon stat-card__icon--red">
                <i class="fas fa-pen-to-square"></i>
            </div>
            <div class="stat-card__info">
                <span class="stat-card__value"><?= (int) $totalToGrade ?></span>
                <span class="stat-card__label">Waiting to Grade</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card__icon stat-card__icon--amber">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="stat-card__info">
                <span class="stat-card__value"><?= (int) $dueTodayCount ?></span>
                <span class="stat-card__label">Due Today</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card__icon stat-card__icon--green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-card__info">
                <span class="stat-card__value"><?= (int) $fullyGradedCount ?></span>
                <span class="stat-card__label">Fully Graded</span>
            </div>
        </div>
        <?php if ($undatedCount > 0): ?>
        <div class="stat-card">
            <div class="stat-card__icon" style="background:#94a3b8;">
                <i class="fas fa-calendar-xmark"></i>
            </div>
            <div class="stat-card__info">
                <span class="stat-card__value"><?= (int) $undatedCount ?></span>
                <span class="stat-card__label">No Due Date</span>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <div class="calendar-layout">

        <!-- Calendar grid -->
        <section class="calendar-panel">
            <div class="calendar-nav">
                <div class="calendar-nav-btns">
                    <a class="calendar-nav-btn" href="?year=<?= (int) $prevMonthDate->format('Y') ?>&month=<?= (int) $prevMonthDate->format('n') ?>" aria-label="Previous month">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <a class="calendar-nav-today" href="?">Today</a>
                    <a class="calendar-nav-btn" href="?year=<?= (int) $nextMonthDate->format('Y') ?>&month=<?= (int) $nextMonthDate->format('n') ?>" aria-label="Next month">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <h2><?= htmlspecialchars($monthLabel) ?></h2>
            </div>

            <div class="calendar-legend">
                <span class="legend-item"><span class="legend-dot dot--activity"></span> Activity</span>
                <span class="legend-item"><span class="legend-dot dot--exam"></span> Exam</span>
                <span class="legend-item"><span class="legend-dot dot--quiz"></span> Quiz</span>
                <span class="legend-item"><span class="legend-dot dot--done"></span> All graded</span>
            </div>

            <div class="calendar-weekdays">
                <?php foreach ($weekdayLabels as $wd): ?>
                    <span><?= $wd ?></span>
                <?php endforeach; ?>
            </div>

            <div class="calendar-grid" id="calendarGrid">
                <?php foreach ($calendarCells as $cell):
                    $classes = ['calendar-cell'];
                    if (!$cell['in_month']) $classes[] = 'out-month';
                    if ($cell['is_today']) $classes[] = 'is-today';
                    if (!empty($cell['items'])) $classes[] = 'has-items';
                    $shown = array_slice($cell['items'], 0, 4);
                    $extra = count($cell['items']) - count($shown);
                ?>
                    <button type="button"
                            class="<?= implode(' ', $classes) ?>"
                            data-date="<?= htmlspecialchars($cell['date']) ?>"
                            <?= empty($cell['items']) ? 'disabled' : '' ?>>
                        <span class="cell-day"><?= (int) $cell['day'] ?></span>
                        <?php if (!empty($cell['items'])): ?>
                            <span class="cell-dots">
                                <?php foreach ($shown as $item): [$dotClass, ] = calendar_item_badge($item['type']); ?>
                                    <span class="cell-dot <?= $item['is_done'] ? 'dot--done' : $dotClass ?>"></span>
                                <?php endforeach; ?>
                                <?php if ($extra > 0): ?>
                                    <span class="cell-more">+<?= (int) $extra ?></span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Agenda panel -->
        <aside class="agenda-panel">
            <h3 id="agendaTitle">This Month</h3>
            <div id="agendaContent">
                <!-- Filled in by JS on load -->
            </div>
        </aside>

    </div>

    <?php if (!empty($backlogItems)): ?>
    <section class="backlog-panel">
        <h3><i class="fas fa-pen-to-square"></i> Waiting to Be Graded</h3>
        <p class="backlog-note">Every assignment or quiz with turned-in work that hasn't been scored yet, oldest deadline first.</p>
        <div class="backlog-list">
            <?php foreach ($backlogItems as $item): [$dotClass, $typeLabel] = calendar_item_badge($item['type']); ?>
                <a class="backlog-item" href="<?= htmlspecialchars($item['link']) ?>">
                    <span class="cell-dot <?= $dotClass ?>"></span>
                    <span class="backlog-item-main">
                        <strong><?= htmlspecialchars($item['title']) ?></strong>
                        <span><?= htmlspecialchars($item['subject_name']) ?> · <?= htmlspecialchars($item['class_label']) ?> · <?= htmlspecialchars($typeLabel) ?><?= $item['date'] ? ' · due ' . htmlspecialchars(date('M j', strtotime($item['date']))) : '' ?></span>
                    </span>
                    <span class="agenda-grade agenda-grade--pending"><?= (int) $item['to_grade'] ?> to grade</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($undatedItems)): ?>
    <section class="undated-panel">
        <h3><i class="fas fa-calendar-xmark"></i> No Due Date Set</h3>
        <p class="undated-note">These don't have a deadline, so they can't be placed on the calendar. You can set one by editing the assignment or quiz.</p>
        <div class="undated-list">
            <?php foreach ($undatedItems as $item): [$dotClass, $typeLabel] = calendar_item_badge($item['type']); ?>
                <a class="undated-item" href="<?= htmlspecialchars($item['link']) ?>">
                    <span class="cell-dot <?= $item['is_done'] ? 'dot--done' : $dotClass ?>"></span>
                    <span class="undated-item-main">
                        <strong><?= htmlspecialchars($item['title']) ?></strong>
                        <span><?= htmlspecialchars($item['subject_name']) ?> · <?= htmlspecialchars($item['class_label']) ?> · <?= htmlspecialchars($typeLabel) ?></span>
                    </span>
                    <?php if ($item['to_grade'] > 0): ?>
                        <span class="agenda-grade agenda-grade--pending"><?= (int) $item['to_grade'] ?> to grade</span>
                    <?php elseif ($item['is_done']): ?>
                        <span class="agenda-done"><i class="fas fa-check"></i> Graded</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</main>

<script>
(function () {
    const itemsByDate = <?= $itemsByDateJson ?: '{}' ?>;
    const monthLabel = <?= json_encode($monthLabel) ?>;
    const todayStr = <?= json_encode($todayStr) ?>;

    const agendaTitle = document.getElementById('agendaTitle');
    const agendaContent = document.getElementById('agendaContent');
    const cells = document.querySelectorAll('.calendar-cell');

    const typeLabels = { activity: 'Activity', exam: 'Exam', quiz: 'Quiz' };

    function fmtTime(dueDate) {
        if (!dueDate) return '—';
        const d = new Date(dueDate.replace(' ', 'T'));
        if (isNaN(d)) return '—';
        const h = d.getHours(), m = d.getMinutes();
        if (h === 0 && m === 0) return 'End of Day';
        return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    function fmtDayLabel(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        const label = d.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
        return dateStr === todayStr ? label + ' · Today' : label;
    }

    function gradeBadge(item) {
        const span = document.createElement('span');
        if (item.to_grade > 0) {
            span.className = 'agenda-grade agenda-grade--pending';
            span.innerHTML = '<i class="fas fa-pen-to-square"></i> ' + item.to_grade + ' to grade';
        } else if (item.is_done) {
            span.className = 'agenda-grade agenda-grade--clear';
            span.innerHTML = '<i class="fas fa-check"></i> All graded';
        } else {
            span.className = 'agenda-grade agenda-grade--wait';
            span.textContent = 'No submissions yet';
        }
        return span;
    }

    function renderItem(item) {
        const a = document.createElement('a');
        a.className = 'agenda-item';
        a.href = item.link;

        const top = document.createElement('div');
        top.className = 'agenda-item-top';

        const badge = document.createElement('span');
        badge.className = 'agenda-type agenda-type--' + item.type;
        badge.textContent = typeLabels[item.type] || 'Activity';
        top.appendChild(badge);
        top.appendChild(gradeBadge(item));
        a.appendChild(top);

        const h4 = document.createElement('h4');
        h4.textContent = item.title;
        a.appendChild(h4);

        const p = document.createElement('p');
        const icon1 = document.createElement('i'); icon1.className = 'fas fa-book';
        const icon2 = document.createElement('i'); icon2.className = 'fas fa-clock';
        p.append(icon1, ' ' + item.subject_name + ' · ' + item.class_label + ' · ', icon2, ' ' + fmtTime(item.due_date));
        a.appendChild(p);

        const m = document.createElement('p');
        m.className = 'agenda-meta-line';
        m.textContent = item.turned_in + ' of ' + item.enrolled + ' students turned in';
        a.appendChild(m);

        return a;
    }

    function renderMonthAgenda() {
        agendaTitle.textContent = 'This Month';
        agendaContent.innerHTML = '';

        const dates = Object.keys(itemsByDate).sort();
        if (!dates.length) {
            agendaContent.innerHTML = '<div class="agenda-empty"><i class="fas fa-calendar-check"></i>No deadlines in ' + monthLabel + '.</div>';
            return;
        }

        const list = document.createElement('div');
        list.className = 'agenda-list';
        dates.forEach(dateStr => {
            const label = document.createElement('div');
            label.className = 'agenda-day-label';
            label.textContent = fmtDayLabel(dateStr);
            list.appendChild(label);
            itemsByDate[dateStr].forEach(item => list.appendChild(renderItem(item)));
        });
        agendaContent.appendChild(list);
    }

    function renderDayAgenda(dateStr) {
        const items = itemsByDate[dateStr] || [];
        agendaTitle.textContent = fmtDayLabel(dateStr);
        agendaContent.innerHTML = '';

        const backLink = document.createElement('button');
        backLink.type = 'button';
        backLink.className = 'agenda-back-link';
        backLink.textContent = '← View whole month';
        backLink.addEventListener('click', () => {
            cells.forEach(c => c.classList.remove('is-selected'));
            renderMonthAgenda();
        });
        agendaContent.appendChild(backLink);

        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'agenda-empty';
            empty.innerHTML = '<i class="fas fa-calendar-check"></i>No deadlines this day.';
            agendaContent.appendChild(empty);
            return;
        }

        const list = document.createElement('div');
        list.className = 'agenda-list';
        items.forEach(item => list.appendChild(renderItem(item)));
        agendaContent.appendChild(list);
    }

    cells.forEach(cell => {
        cell.addEventListener('click', () => {
            if (cell.disabled) return;
            cells.forEach(c => c.classList.remove('is-selected'));
            cell.classList.add('is-selected');
            renderDayAgenda(cell.dataset.date);
        });
    });

    const todayCell = document.querySelector('.calendar-cell.is-today.has-items');
    if (todayCell) {
        todayCell.classList.add('is-selected');
        renderDayAgenda(todayStr);
    } else {
        renderMonthAgenda();
    }
})();
</script>

</body>
</html>
