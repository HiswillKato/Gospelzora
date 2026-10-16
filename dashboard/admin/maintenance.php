<?php
require_once __DIR__ . '/../../app/bootstrap.php';
$page_title = "Maintenance settings";
$user->guard('admin');

$maintenance = $settings->maintenance();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf($_POST['csrf_token'] ?? '')) {
    $on = (string)($_POST['maintenance'] ?? '') === 'on';
    $settings->set('maintenance_mode', $on ? '1' : '0');
    $analytics->log('settings.maintenance', sprintf("Maintenance mode turned %s", $on ? 'on' : 'off'));
    $msg = $on ? "Maintenance mode enabled." : "Maintenance mode disabled.";
    $maintenance = $settings->maintenance();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $err = "Invalid security token. Please try again.";
}

require __DIR__ . '/../../app/includes/admin/header.php';
?>
<?php $alert_type = 'success'; $alert_text = $msg;
require __DIR__ . '/../../app/includes/partials/alert.php'; ?>
<?php $alert_type = 'danger'; $alert_text = $err;
require __DIR__ . '/../../app/includes/partials/alert.php'; ?>
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body">
<div class="d-flex align-items-center gap-3 mb-3">
<?= icon($maintenance ? 'screwdriver-wrench' : 'circle-check', 'fa-2x text-' . ($maintenance ? 'warning' : 'success')) ?>
<div>
<h5 class="fw-bold mb-1">Maintenance settings</h5>
<p class="text-muted mb-0">Temporarily take the site offline for admins only.</p>
</div>
</div>
<div class="mb-4"><span class="badge bg-<?= $maintenance ? 'warning text-dark' : 'success' ?> fs-6"><?= $maintenance ? "Maintenance is ON" : "Maintenance is OFF" ?></span></div>
<?php if ($maintenance) { ?>
<div class="alert alert-warning"><?= icon('triangle-exclamation', 'me-2') ?>While ON, all public pages, API endpoints and the member dashboard answer with a 503, and only administrators can sign in. You can always undo this here.</div>
<?php } ?>
<form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
<input type="hidden" name="maintenance" value="<?= $maintenance ? 'off' : 'on' ?>">
<button class="btn btn-<?= $maintenance ? 'success' : 'warning' ?>">
<?= icon($maintenance ? 'circle-check' : 'screwdriver-wrench', 'me-1') ?>
<?= $maintenance ? "Disable maintenance" : "Enable maintenance" ?>
</button>
</form>
</div></div>
<?php require __DIR__ . '/../../app/includes/admin/footer.php'; 