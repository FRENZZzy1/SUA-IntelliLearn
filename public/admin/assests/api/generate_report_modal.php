<?php
/**
 * Generate Report modal (Quick Actions widget).
 * Include once inside <body> of admin/dashboard.php, after config.php is loaded.
 * Opened with openGenerateReportModal(); submits to generate_report.php.
 */
$grm_csrf = function_exists('generateCSRFToken') ? generateCSRFToken() : '';

$grm_years = [];
try {
    $grm_years = $pdo->query("SELECT school_year_id, label, is_current FROM schoolyears ORDER BY start_date DESC")->fetchAll();
} catch (Throwable $e) {
    $grm_years = [];
}
?>
<style>
    .grm-overlay {
        position: fixed; inset: 0; z-index: 1100; display: none;
        align-items: center; justify-content: center; padding: 16px;
        background: rgba(15, 23, 42, .55); backdrop-filter: blur(2px);
    }
    .grm-overlay.open { display: flex; }
    .grm-modal {
        width: 100%; max-width: 520px; max-height: 92vh; overflow-y: auto;
        background: #fff; border-radius: 14px; box-shadow: 0 20px 50px rgba(0, 0, 0, .25);
    }
    .grm-bar { height: 5px; background: linear-gradient(90deg, #ef4444, #f97316); border-radius: 14px 14px 0 0; }
    .grm-header { display: flex; align-items: center; gap: 12px; padding: 18px 22px 8px; }
    .grm-header-icon {
        width: 42px; height: 42px; border-radius: 10px; display: grid; place-items: center;
        background: #fee2e2; color: #dc2626; font-size: 18px;
    }
    .grm-header h2 { margin: 0; font-size: 18px; color: #0f172a; }
    .grm-header p { margin: 2px 0 0; font-size: 13px; color: #64748b; }
    .grm-close { margin-left: auto; cursor: pointer; color: #64748b; padding: 6px; border-radius: 6px; }
    .grm-close:hover { background: #f1f5f9; }
    .grm-body { padding: 10px 22px 6px; }
    .grm-label { display: block; margin: 14px 0 8px; font-size: 13px; font-weight: 600; color: #334155; }
    .grm-types { display: grid; gap: 8px; }
    .grm-type {
        display: flex; align-items: center; gap: 12px; padding: 11px 13px; cursor: pointer;
        border: 1.5px solid #e2e8f0; border-radius: 10px; transition: .15s;
    }
    .grm-type:hover { border-color: #cbd5e1; background: #f8fafc; }
    .grm-type input { display: none; }
    .grm-type.active { border-color: #2563eb; background: #eff6ff; }
    .grm-type i { width: 34px; height: 34px; display: grid; place-items: center; border-radius: 8px; background: #f1f5f9; color: #475569; }
    .grm-type.active i { background: #2563eb; color: #fff; }
    .grm-type strong { display: block; font-size: 14px; color: #0f172a; }
    .grm-type small { font-size: 12px; color: #64748b; }
    .grm-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .grm-select {
        width: 100%; padding: 10px 12px; font-size: 14px; color: #0f172a;
        border: 1.5px solid #e2e8f0; border-radius: 8px; background: #fff;
    }
    .grm-select:focus { outline: none; border-color: #2563eb; }
    .grm-alert { display: none; margin: 12px 22px 0; padding: 10px 12px; border-radius: 8px; font-size: 13px; background: #fee2e2; color: #b91c1c; }
    .grm-alert.show { display: block; }
    .grm-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 22px 20px; }
    .grm-btn { padding: 10px 18px; font-size: 14px; font-weight: 600; border-radius: 8px; cursor: pointer; border: 1.5px solid transparent; }
    .grm-btn-secondary { background: #fff; border-color: #e2e8f0; color: #475569; }
    .grm-btn-secondary:hover { background: #f8fafc; }
    .grm-btn-primary { background: #2563eb; color: #fff; }
    .grm-btn-primary:hover { background: #1d4ed8; }
    .grm-btn:disabled { opacity: .65; cursor: not-allowed; }
    @media (max-width: 480px) { .grm-row { grid-template-columns: 1fr; } }
</style>

<div class="grm-overlay" id="grmOverlay" onclick="if (event.target === this) closeGenerateReportModal()">
    <div class="grm-modal" role="dialog" aria-modal="true" aria-labelledby="grmTitle">
        <div class="grm-bar"></div>
        <div class="grm-header">
            <div class="grm-header-icon"><i class="fas fa-file-export"></i></div>
            <div>
                <h2 id="grmTitle">Generate Report</h2>
                <p>Download an Excel report from live data</p>
            </div>
            <div class="grm-close" onclick="closeGenerateReportModal()" title="Close"><i class="fas fa-times"></i></div>
        </div>

        <div class="grm-alert" id="grmAlert"></div>

        <form id="grmForm" novalidate onsubmit="return submitGenerateReport(event)">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($grm_csrf) ?>">

            <div class="grm-body">
                <span class="grm-label">Report type</span>
                <div class="grm-types">
                    <label class="grm-type active">
                        <input type="radio" name="report_type" value="enrollment" checked>
                        <i class="fas fa-user-graduate"></i>
                        <span><strong>Enrollment Summary</strong><small>Capacity, enrolled, pending and seats left per class</small></span>
                    </label>
                    <label class="grm-type">
                        <input type="radio" name="report_type" value="attendance">
                        <i class="fas fa-calendar-check"></i>
                        <span><strong>Attendance Summary</strong><small>Present, late, excused and absent totals per class</small></span>
                    </label>
                    <label class="grm-type">
                        <input type="radio" name="report_type" value="grades">
                        <i class="fas fa-chart-line"></i>
                        <span><strong>Grades Summary</strong><small>Average Prelim to Final grades per class</small></span>
                    </label>
                </div>

                <div class="grm-row">
                    <div>
                        <label class="grm-label" for="grmYear">School year</label>
                        <select class="grm-select" id="grmYear" name="school_year_id">
                            <?php if (!$grm_years): ?>
                                <option value="">No school years set up</option>
                            <?php endif; ?>
                            <?php foreach ($grm_years as $y): ?>
                                <option value="<?= (int) $y['school_year_id'] ?>" <?= $y['is_current'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($y['label']) ?><?= $y['is_current'] ? ' (current)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="grm-label" for="grmTerm">Term</label>
                        <select class="grm-select" id="grmTerm" name="term">
                            <option value="">All terms</option>
                            <option value="TRM 1">TRM 1</option>
                            <option value="TRM 2">TRM 2</option>
                            <option value="TRM 3">TRM 3</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="grm-footer">
                <button type="button" class="grm-btn grm-btn-secondary" onclick="closeGenerateReportModal()">Cancel</button>
                <button type="submit" class="grm-btn grm-btn-primary" id="grmSubmit">
                    <i class="fas fa-download"></i> Download Excel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const overlay = document.getElementById('grmOverlay');
        const form    = document.getElementById('grmForm');
        const alertEl = document.getElementById('grmAlert');
        const submit  = document.getElementById('grmSubmit');
        let busy = false;

        function setAlert(msg) {
            alertEl.textContent = msg || '';
            alertEl.classList.toggle('show', !!msg);
        }

        window.openGenerateReportModal = function () {
            setAlert('');
            overlay.classList.add('open');
        };

        window.closeGenerateReportModal = function () {
            if (busy) return;
            overlay.classList.remove('open');
        };

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('open')) closeGenerateReportModal();
        });

        // Highlight the selected report type card.
        form.querySelectorAll('.grm-type').forEach(function (card) {
            card.addEventListener('click', function () {
                form.querySelectorAll('.grm-type').forEach(function (c) { c.classList.remove('active'); });
                card.classList.add('active');
            });
        });

        window.submitGenerateReport = function (e) {
            e.preventDefault();
            if (busy) return false;

            if (!form.school_year_id.value) {
                setAlert('Please set up a school year first.');
                return false;
            }

            busy = true;
            setAlert('');
            const originalHtml = submit.innerHTML;
            submit.disabled = true;
            submit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

            fetch('assests/api/generate_report.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(new FormData(form)).toString()
            })
                .then(async function (res) {
                    const type = res.headers.get('Content-Type') || '';

                    // Errors come back as JSON; success is the .xlsx file itself.
                    if (type.includes('application/json')) {
                        const data = await res.json();
                        throw new Error((data.errors && data.errors[0]) || 'Could not generate the report.');
                    }
                    if (!res.ok) throw new Error('Could not generate the report (server error ' + res.status + ').');

                    const disposition = res.headers.get('Content-Disposition') || '';
                    const match = disposition.match(/filename="?([^";]+)"?/);
                    const filename = match ? match[1] : 'report_' + Date.now() + '.xlsx';

                    const blob = await res.blob();
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(url);

                    busy = false;
                    overlay.classList.remove('open');
                    if (typeof showToast === 'function') showToast('Report downloaded successfully.');
                })
                .catch(function (err) {
                    setAlert(err.message || 'Could not generate the report.');
                })
                .finally(function () {
                    busy = false;
                    submit.disabled = false;
                    submit.innerHTML = originalHtml;
                });

            return false;
        };
    })();
</script>
