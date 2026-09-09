<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
if(PHP_SAPI!=='cli'){fwrite(STDERR,"Somente CLI.\n");exit(2);}

$limit=max(100,min(5000,(int)($argv[1]??1000)));
$tasks=[
    'tentativas de login antigas'=>"DELETE FROM login_attempts WHERE created_at<DATE_SUB(NOW(),INTERVAL 7 DAY) ORDER BY id LIMIT {$limit}",
    'limites expirados'=>"DELETE FROM rate_limits WHERE expires_at<DATE_SUB(NOW(),INTERVAL 1 DAY) LIMIT {$limit}",
    'recuperações de senha antigas'=>"DELETE FROM password_reset_tokens WHERE expires_at<DATE_SUB(NOW(),INTERVAL 7 DAY) OR (used_at IS NOT NULL AND used_at<DATE_SUB(NOW(),INTERVAL 7 DAY)) ORDER BY id LIMIT {$limit}",
    'confirmações de e-mail antigas'=>"DELETE FROM email_verification_tokens WHERE expires_at<DATE_SUB(NOW(),INTERVAL 7 DAY) OR (used_at IS NOT NULL AND used_at<DATE_SUB(NOW(),INTERVAL 7 DAY)) ORDER BY id LIMIT {$limit}",
    'desafios 2FA antigos'=>"DELETE FROM login_two_factor_challenges WHERE expires_at<DATE_SUB(NOW(),INTERVAL 7 DAY) OR (consumed_at IS NOT NULL AND consumed_at<DATE_SUB(NOW(),INTERVAL 7 DAY)) ORDER BY id LIMIT {$limit}",
    'códigos de recuperação usados'=>"DELETE FROM user_two_factor_recovery_codes WHERE used_at IS NOT NULL AND used_at<DATE_SUB(NOW(),INTERVAL 30 DAY) ORDER BY id LIMIT {$limit}",
    'sessões antigas'=>"DELETE FROM user_sessions WHERE expires_at<DATE_SUB(NOW(),INTERVAL 30 DAY) OR (revoked_at IS NOT NULL AND revoked_at<DATE_SUB(NOW(),INTERVAL 30 DAY)) ORDER BY id LIMIT {$limit}",
    'e-mails enviados antigos'=>"DELETE FROM outbound_emails WHERE status='sent' AND sent_at<DATE_SUB(NOW(),INTERVAL 30 DAY) ORDER BY id LIMIT {$limit}",
    'e-mails falhos antigos'=>"DELETE FROM outbound_emails WHERE status='failed' AND created_at<DATE_SUB(NOW(),INTERVAL 90 DAY) ORDER BY id LIMIT {$limit}",
];

$total=0;
foreach($tasks as$label=>$sql){$removed=db()->exec($sql);if($removed===false)throw new RuntimeException('Falha na manutenção: '.$label);$total+=$removed;echo $label.': '.$removed."\n";}
echo 'Total removido: '.$total."\n";
