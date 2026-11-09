<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$required=[
 'app/views/auth/login.php','app/views/auth/register.php','app/views/auth/forgot.php',
 'app/views/auth/logout.php','app/views/auth/reset.php','app/views/auth/verify-email.php',
 'app/views/dashboard/partials/overview.php','app/views/dashboard/partials/songs.php',
 'app/views/admin/partials/account-form.php','app/views/admin/partials/account-list.php',
 'app/views/shared/member/header.php','app/views/shared/member/footer.php',
 'public/index.php','routes/web.php','deploy/nginx.conf.example',
];
foreach($required as $path) if(!is_file($root.'/'.$path)){fwrite(STDERR,"FAIL: missing {$path}\n");exit(1);}
foreach(['pages','dashboard','api','assets'] as $legacy) if(is_dir($root.'/'.$legacy)){fwrite(STDERR,"FAIL: legacy root {$legacy}/ remains\n");exit(1);}
foreach(['app/includes','dashboard/includes','dashboard/admin/includes'] as $legacy) if(is_dir($root.'/'.$legacy)){fwrite(STDERR,"FAIL: retired includes {$legacy}/ remains\n");exit(1);}
if(is_file($root.'/public/asset-fallback.php')){fwrite(STDERR,"FAIL: transitional asset fallback remains\n");exit(1);}
$routes=file_get_contents($root.'/routes/web.php');
if($routes===false||!str_contains($routes,"\$router->add")||!str_contains($routes,"/api/player")){fwrite(STDERR,"FAIL: front-controller route map is incomplete\n");exit(1);}
$authContracts=['login'=>'$user->login(','register'=>'$user->register(','forgot'=>'$user->recover(','logout'=>'$user->logout(','reset'=>'$user->reset(','verify-email'=>'$user->consume(\'email_verify\''];
foreach($authContracts as $view=>$contract){$source=file_get_contents($root.'/app/views/auth/'.$view.'.php');if($source===false||!str_contains($source,$contract)){fwrite(STDERR,"FAIL: auth view {$view} lacks workflow\n");exit(1);}}
echo "PASS: public document root, canonical views, and retired entry points verified\n";
