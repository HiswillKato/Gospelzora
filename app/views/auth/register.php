<?php
require_once __DIR__ . '/../../bootstrap.php';

if ($user->authed()) {
    redirect('dashboard');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf($_POST['csrf_token'] ?? '')) {
        $error = "Invalid security token.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $role = in_array($_POST['role'] ?? '', ['artist', 'user'], true) ? $_POST['role'] : 'user';
        $country = trim($_POST['country'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            $error = "All fields are required.";
        } elseif (!valid($email)) {
            $error = "Please enter a valid email address.";
        } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
            $error = sprintf("Password must be at least %s characters long.", MIN_PASSWORD_LENGTH);
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
        } elseif (!valid($country, 'country')) {
            $error = "Please select a valid country.";
        } else {
            $result = $user->register([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'country' => $country,
            ]);
            if ($result['success']) {
                flash('success', $result['message']);
                redirect('login');
            } else {
                $error = $result['message'];
            }
        }
    }
}

$page_title = "Register";
$page_description = "Create a free Gospelzora account to save favorites and more.";
$extra_js = '
<script>
(function(){
    if (typeof $ === "undefined") return;
    var $country = $("select[name=country]");
    if (!$country.length) return;
    if ($country.val() !== "") return; // respect a selection the user already made
    var $label = $("label[for=country]");
    if (!$label.length) {
        $label = $country.closest(".mb-3").find(".form-label");
    }
    var original = $label.text();
    $label.text(original + " ' . "(detecting…)" . '");
    var done = false;
    var apply = function(code){
        if (done) return;
        done = true;
        $label.text(original);
        if (!code) return;
        var found = $country.find("option[value=\"" + code + "\"]").length;
        if (found) $country.val(code);
    };
    var urls = [
        "https://ipwho.is/",
        "https://ipapi.co/json/"
    ];
    var i = 0;
    var next = function(){
        if (i >= urls.length) { apply(null); return; }
        $.getJSON(urls[i++])
            .done(function(data){
                var code = (data && (data.country_code || data.countryCode)) || "";
                apply(code);
            })
            .fail(next);
    };
    next();
})();
</script>
';

require_once __DIR__ . '/../shared/header.php';

ob_start();
$alert_type = 'danger'; $alert_text = $error;
require __DIR__ . '/../shared/partials/alert.php';
?>
<form method="POST" action="">
<input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
<div class="mb-3">
<label class="form-label">Full Name</label>
<input type="text" name="name" class="form-control" required maxlength="100" value="<?= e($_POST['name'] ?? '') ?>">
</div>
<div class="mb-3">
<label class="form-label">Email</label>
<input type="email" name="email" class="form-control" required maxlength="150" value="<?= e($_POST['email'] ?? '') ?>">
</div>
<div class="mb-3">
<label class="form-label d-block">Are you an artist?</label>
<div class="d-flex gap-2">
<input type="radio" class="btn-check" name="role" id="role-yes" value="artist" <?= (($_POST['role'] ?? '') === 'artist') ? 'checked' : '' ?>>
<label class="btn btn-outline-secondary btn-sm" for="role-yes">Yes</label>
<input type="radio" class="btn-check" name="role" id="role-no" value="user" <?= (($_POST['role'] ?? 'user') !== 'artist') ? 'checked' : '' ?>>
<label class="btn btn-outline-secondary btn-sm" for="role-no">No</label>
</div>
</div>
<div class="mb-3">
<label class="form-label">Country</label>
<select name="country" class="form-select">
<?php
$country_selected = $_POST['country'] ?? '';
$country_required = false;
require __DIR__ . '/../shared/partials/country-options.php';
?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Password</label>
<input type="password" name="password" class="form-control" required minlength="8">
</div>
<div class="mb-4">
<label class="form-label">Confirm Password</label>
<input type="password" name="confirm_password" class="form-control" required>
</div>
<button type="submit" class="btn btn-gold w-100 btn-lg">Create Account</button>
</form>
<?php
$auth_body = ob_get_clean();
$auth_icon = 'user-plus';
$auth_icon_class = 'text-gold fs-1';
$auth_title = 'Create account';
$auth_text = 'Join ' . SITE_NAME . ' and start your worship journey';
$auth_footer = 'Already have an account? <a href="' . e(url('login')) . '" class="fw-semibold">Log in</a>';
require __DIR__ . '/../shared/partials/auth-panel.php';

require_once __DIR__ . '/../shared/footer.php'; 