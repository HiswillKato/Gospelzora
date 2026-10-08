<?php
require_once __DIR__ . '/../../app/bootstrap.php';
if ($user->check('admin')) redirect('dashboard');
redirect('login');