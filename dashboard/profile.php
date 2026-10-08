<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user->guard('login');
if ($user->check('admin')) redirect('dashboard');

$dash_type = $user->role();
$dash_user = $user->current();
$dash_id = (int)$dash_user['id'];

$section = 'profile';
$page_title = "Profile";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Invalid security token. Please try again.');
        redirect('dashboard/profile');
    }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'update_profile') {
        $result = $user->update($dash_id, [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'country' => $_POST['country'] ?? '',
            'current_password' => (string)($_POST['current_password'] ?? ''),
        ], $dash_type);
        if (!empty($result['reverify'])) {
            flash($result['mailed'] ? 'success' : 'warning', $result['message']);
            redirect('login');
        }
        if ($result['success']) {
            $dash_user = $user->current(true);
        }
        flash($result['success'] ? 'success' : 'danger', $result['message']);
        redirect('dashboard/profile');
    }
    if ($action === 'change_password') {
        $result = $user->password($dash_id, $_POST['current_password'] ?? '', $_POST['new_password'] ?? '', $dash_type);
        flash($result['success'] ? 'success' : 'danger', $result['message']);
        redirect('dashboard/profile');
    }
    flash('danger', 'Invalid request.');
    redirect('dashboard/profile');
}

$overview = [
    'favorites' => $user->count($dash_id, $dash_type),
];

require __DIR__ . '/../app/includes/member/header.php';
?>
<div class="row g-4">
<div class="col-lg-4">
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body text-center p-4">
<div class="rounded-circle bg-secondary-subtle d-flex align-items-center justify-content-center mx-auto mb-3" style="width:80px;height:80px">
<?= icon('user', 'fs-3 text-secondary') ?></div>
<h5 class="fw-bold mb-1"><?= e($dash_user['name']) ?></h5>
<p class="text-muted mb-2"><?= e($dash_user['email']) ?></p>
<p class="small text-muted mb-3"><?= e(sprintf("Member since %s", date('M Y', strtotime($dash_user['created'] ?? 'now')))) ?></p>
<div class="d-flex justify-content-center gap-2 flex-wrap">
<span class="badge bg-<?= $dash_type === 'artist' ? 'purple' : 'info' ?>"><?= $dash_type === 'artist' ? "Artist" : "User" ?></span>
<?php if ($dash_type === 'artist' && !empty($dash_user['slug'])) { ?>
<a href="<?= url('artist/' . $dash_user['slug']) ?>" class="btn btn-sm btn-outline-secondary"><?= icon('arrow-up-right-from-square', 'me-1') ?>Artist page</a>
<?php } ?>
</div>
</div></div>
<div class="card border-0 shadow mt-4" style="border-radius:12px"><div class="card-body p-4">
<h5 class="fw-semibold mb-3">Account overview</h5>
<div class="d-flex justify-content-between py-2 border-bottom"><small class="text-muted">Country</small><strong><?= e(country($dash_user['country'] ?? '') ?: '—') ?></strong></div>
<div class="d-flex justify-content-between py-2 border-bottom"><small class="text-muted">Account type</small><strong><?= $dash_type === 'artist' ? "Artist" : "Member" ?></strong></div>
<div class="d-flex justify-content-between py-2 border-bottom"><small class="text-muted">Favorites</small><strong><?= abbrev($overview['favorites']) ?></strong></div>
</div></div>
</div>
<div class="col-lg-8">
<div class="card border-0 shadow mb-4" style="border-radius:12px"><div class="card-body p-4">
<h5 class="fw-semibold mb-3">Edit Profile</h5>
<form method="POST"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="update_profile">
<div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" maxlength="100" value="<?= e($dash_user['name']) ?>" required></div>
<div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" maxlength="150" value="<?= e($dash_user['email']) ?>" required></div>
<div class="mb-3"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" autocomplete="current-password"><div class="form-text">Optional</div></div>
<div class="mb-3"><label class="form-label">Country</label><select name="country" class="form-select"><?php $country_selected = country($dash_user['country'] ?? '', 'code'); $country_required = false;
require __DIR__ . '/../app/includes/partials/country-options.php'; ?></select></div>
<button type="submit" class="btn btn-gold">Save Changes</button></form>
</div></div>
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body p-4">
<h5 class="fw-semibold mb-3">Change Password</h5>
<form method="POST"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="change_password">
<div class="mb-3"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
<div class="mb-3"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required minlength="<?= (int)MIN_PASSWORD_LENGTH ?>"></div>
<button type="submit" class="btn btn-gold">Update Password</button></form>
</div></div>
</div>
</div>
<?php require __DIR__ . '/../app/includes/member/footer.php'; 