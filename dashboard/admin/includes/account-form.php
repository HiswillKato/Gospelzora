<?php
/**
 * Shared "add or edit an account" form.
 *
 * /dashboard/admin/add-user and /dashboard/admin/add-admin are the same form for
 * two account types. Rather than copy the markup twice, each page sets $role
 * ('user' or 'admin') and includes this file, which resolves the few real
 * differences below and then does all the work.
 *
 * Partials are only ever `require`d, never reached by a URL - the deny rule for
 * include directories in .htaccess is what enforces that. The guard below is a
 * second line of defence for anyone hitting the file directly.
 */
if (!isset($role) || !in_array($role, ['user', 'admin'], true)) {
    http_response_code(403);
    exit;
}

$accountTypes = [
    'user' => [
        'list'      => 'dashboard/admin/users',
        'add_title' => 'Add User',
        'edit_title' => 'Edit User',
        'not_found' => 'User not found.',
    ],
    'admin' => [
        'list'      => 'dashboard/admin/admins',
        'add_title' => 'Add Admin',
        'edit_title' => 'Edit Admin',
        'not_found' => 'Admin not found.',
    ],
];
$type = $accountTypes[$role];

$page_title = e(isset($_GET['edit']) ? $type['edit_title'] : $type['add_title']);
$user->guard('admin');
$err = '';

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $user->find((int)$_GET['edit'], $role);
    if (!$edit) $err = $type['not_found'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf($_POST['csrf_token'] ?? '')) {
    if (($_POST['action'] ?? '') === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $result = $user->save($role, [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'status' => $_POST['status'] ?? 'active',
            'country' => trim($_POST['country'] ?? ''),
            'password' => $_POST['password'] ?? '',
        ], $id);
        if ($result['success']) {
            flash('success', $result['message']);
            redirect($type['list']);
        }
        $err = $result['message'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $err = "Invalid security token. Please try again.";
}

require __DIR__ . '/../../../app/includes/admin/header.php';
?>
<?php $alert_type = 'danger'; $alert_text = $err;
require __DIR__ . '/../../../app/includes/partials/alert.php'; ?>
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body">
<h5 class="fw-semibold mb-3"><?= $edit ? e($type['edit_title']) : e($type['add_title']) ?></h5>
<form method="POST"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="save">
<?php if ($edit) { ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php } ?>
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required maxlength="100" value="<?= e($edit['name']??'') ?>"></div>
<div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required maxlength="150" value="<?= e($edit['email']??'') ?>"></div>
<div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select">
<option value="active" <?= ($edit['status']??'active')==='active'?'selected':'' ?>>Active</option>
<option value="disabled" <?= ($edit['status']??'')==='disabled'?'selected':'' ?>>Disabled</option>
</select></div>
<div class="col-md-3"><label class="form-label">Country</label><select name="country" class="form-select"><?php $country_selected = country($edit['country'] ?? '', 'code'); $country_required = false;
require __DIR__ . '/../../../app/includes/partials/country-options.php'; ?></select></div>
<div class="col-md-3">
<label class="form-label">Password <?= $edit ? '' : '*' ?></label>
<input type="password" name="password" class="form-control" <?= $edit?'':'required' ?> autocomplete="new-password" title="<?= $edit ? "Leave empty to keep the current password unchanged" : '' ?>">
<?php if ($edit) { ?><div class="form-text password-hint">Leave empty to keep the current password unchanged.</div><?php } ?>
</div>
<div class="col-md-3"><label class="form-label text-muted">&nbsp;</label><div class="pt-1"><button type="submit" class="btn btn-gold">Save</button> <a href="<?= url($type['list']) ?>" class="btn btn-outline-secondary">Cancel</a></div></div>
</div></form></div></div>
<?php require __DIR__ . '/../../../app/includes/admin/footer.php'; 
