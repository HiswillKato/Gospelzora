<?php
require_once __DIR__ . '/../../app/bootstrap.php';
$page_title = "Messages";
$user->guard('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'read' && $id > 0) {
        $db->execute("UPDATE messages SET seen=1 WHERE id=?", [$id]);
        $analytics->log('contact.read', sprintf("Message #%s marked as read", $id));
        flash('success', sprintf("Message #%s marked as read", $id));
        redirect('dashboard/admin/messages');
    }
    if ($action === 'delete' && $id > 0) {
        $db->execute("DELETE FROM messages WHERE id=?", [$id]);
        $analytics->log('contact.delete', sprintf("Message #%s deleted", $id));
        flash('success', sprintf("Message #%s deleted", $id));
        redirect('dashboard/admin/messages');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    flash('danger', 'Invalid security token. Please try again.');
    redirect('dashboard/admin/messages');
}
$messages = $db->select("SELECT * FROM messages ORDER BY created DESC");
require __DIR__ . '/../../app/includes/admin/header.php';
?>
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body">
<?php if (empty($messages)) { ?><p class="text-muted">No messages.</p>
<?php } else { ?><div class="list-group list-group-flush">
<?php foreach ($messages as $m) { ?>
<div class="list-group-item <?= !$m['seen']?'bg-light':'' ?>">
<div class="d-flex justify-content-between"><div>
<strong><?= e($m['name']) ?></strong> &lt;<?= e($m['email']) ?>&gt;
<span class="badge bg-<?= $m['seen']?'secondary':'primary' ?> ms-2"><?= $m['seen'] ? "Read" : "Unread" ?></span>
<div class="fw-semibold mt-1"><?= e($m['subject']) ?></div>
<p class="mb-1 text-muted"><?= nl2br(e($m['message'])) ?></p>
<small class="text-muted"><?= ago($m['created']) ?></small>
</div>
<div class="text-end">
<?php if (!$m['seen']) { ?><form method="POST" class="d-inline mb-1"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="read"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-primary">Mark Read</button></form><br><?php } ?>
<?php $delete_id = (int)$m['id']; $delete_confirm = 'Delete this message?'; $delete_form_class = 'd-inline';
require __DIR__ . '/../../app/includes/partials/delete-button.php'; ?>
</div></div></div>
<?php } ?></div><?php } ?>
</div></div>
<?php require __DIR__ . '/../../app/includes/admin/footer.php'; 