<?php
require_once __DIR__ . '/../../bootstrap.php';
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
require __DIR__ . '/../shared/admin/header.php';
?>
<div class="card tile">
<?php if (empty($messages)) { ?>
<div class="p-5 text-center">
    <?= icon('envelope', '', 'fa-2x text-primary d-block mb-3') ?>
    <p class="text-body-secondary mb-0">No messages yet. When somebody uses the contact form, it lands here.</p>
</div>
<?php } else { ?><div class="list-group list-group-flush">
<?php foreach ($messages as $m) { ?>
<div class="list-group-item<?= !$m['seen'] ? ' bg-body-tertiary' : '' ?>">
<div class="d-flex flex-column flex-sm-row gap-3">
<span class="brand-mark brand-initials" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($m['name'], 0, 1))) ?></span>
<div class="flex-grow-1 min-width-0">
<div class="d-flex flex-wrap align-items-center gap-2">
<strong><?= e($m['name']) ?></strong>
<a class="small text-break" href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
<span class="badge rounded-pill bg-<?= $m['seen']?'secondary':'primary' ?>"><?= $m['seen'] ? "Read" : "Unread" ?></span>
</div>
<div class="fw-semibold mt-1"><?= e($m['subject']) ?></div>
<p class="mb-1 text-body-secondary"><?= nl2br(e($m['message'])) ?></p>
<small class="text-body-secondary"><?= ago($m['created']) ?></small>
</div>
<div class="d-flex flex-sm-column gap-2 align-items-sm-end flex-shrink-0">
<?php if (!$m['seen']) { ?><form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="read"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button type="submit" class="btn btn-ghost btn-sm">Mark read</button></form><?php } ?>
<?php $delete_id = (int)$m['id']; $delete_confirm = 'Delete this message?'; $delete_form_class = 'd-inline';
require __DIR__ . '/../shared/partials/delete-button.php'; ?>
</div>
</div></div>
<?php } ?></div><?php } ?>
</div>
<?php require __DIR__ . '/../shared/admin/footer.php'; 