<?php
require_once __DIR__ . '/../../bootstrap.php';
if ($user->check('admin')) redirect('dashboard');
redirect('login');