<?php
/**
 * Shared account list ("Users" / "Admins").
 *
 * /dashboard/admin/users and /dashboard/admin/admins are the same table and the
 * same two POST actions for two account types. Rather than copy both twice, each
 * page sets $role ('user' or 'admin') and includes this file, which resolves the
 * wording and the one real behavioural difference — admins may not disable or
 * delete the account they are signed in with — and then does all the work.
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
        'title'       => "Users",
        'one'         => "user",
        'One'         => "User",
        'list'        => 'dashboard/admin/users',
        'add'         => 'dashboard/admin/users/add',
        'edit'        => 'dashboard/admin/users/edit',
        'empty'       => "No users found",
        'toggle_done' => "User status updated.",
        'delete_done' => "User account deleted.",
        'self_toggle' => '',           // a user may disable or delete themselves
        'self_delete' => '',
    ],
    'admin' => [
        'title'       => "Admins",
        'one'         => "admin",
        'One'         => "Admin",
        'list'        => 'dashboard/admin/admins',
        'add'         => 'dashboard/admin/admins/add',
        'edit'        => 'dashboard/admin/admins/edit',
        'empty'       => "No admins found",
        'toggle_done' => "Admin status updated.",
        'delete_done' => "Admin account deleted.",
        'self_toggle' => "You cannot disable your own account.",
        'self_delete' => "You cannot delete your own account.",
    ],
];
$type = $accountTypes[$role];
$one  = $type['one'];

// Only the admin list needs to know who is signed in, to protect that row.
$self_id = $role === 'admin' ? (int)($_SESSION['admin_id'] ?? 0) : 0;

$page_title = $type['title'];
$user->guard('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'toggle' && $id > 0) {
        if ($self_id && $id === $self_id) {
            flash('danger', $type['self_toggle']);
            redirect($type['list']);
        }
        $user->toggle($id, $role);
        flash('success', $type['toggle_done']);
        redirect($type['list']);
    }
    if ($action === 'delete' && $id > 0) {
        if ($self_id && $id === $self_id) {
            flash('danger', $type['self_delete']);
            redirect($type['list']);
        }
        $user->delete($role, $id);
        flash('success', $type['delete_done']);
        redirect($type['list']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    flash('danger', 'Invalid security token. Please try again.');
    redirect($type['list']);
}

$accounts = $user->roster($role);
$header_actions = '<a class="btn btn-gold btn-sm" href="' . url($type['add']) . '">' . icon('plus', 'me-1') . "Add " . $type['One'] . '</a>';
require __DIR__ . '/../../../app/includes/admin/header.php';
?>
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body"><div class="table-responsive">
<table class="table table-hover"><thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Joined</th><th>Last Login</th><th></th></tr></thead><tbody>
<?php if (empty($accounts)) { ?><tr><td colspan="6" class="text-center text-muted py-4"><?= $type['empty'] ?></td></tr>
<?php } else { foreach ($accounts as $u) { ?><tr>
<td><?= e($u['name']) ?><?= $self_id === (int)$u['id'] ? ' <span class="badge bg-info text-dark">' . "You" . '</span>' : '' ?></td><td><?= e($u['email']) ?></td>
<td><span class="badge bg-<?= $u['status']==='active'?'success':'secondary' ?>"><?= e(status((string)$u['status'])) ?></span></td>
<td><?= date('M j, Y', strtotime($u['created'])) ?></td>
<td><?= $u['login'] ? ago($u['login']) : "Never" ?></td>
<td class="text-end">
<a href="<?= url($type['edit']) ?>?edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit <?= $one ?>"><?= icon('pen') ?></a>
<form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-sm btn-outline-warning" title="Toggle status" aria-label="<?= e(sprintf("Toggle status of %s", $u['name'])) ?>"><?= icon('toggle-on') ?></button></form>
<?php if ($self_id !== (int)$u['id']) { $delete_id = (int)$u['id']; $delete_confirm = sprintf("Delete this %s?", $one); $delete_form_class = 'd-inline';
require __DIR__ . '/../../../app/includes/partials/delete-button.php'; ?><?php } ?>
</td></tr><?php } } ?></tbody></table></div></div></div>
<?php require __DIR__ . '/../../../app/includes/admin/footer.php'; 