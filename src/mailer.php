<?php
declare(strict_types=1);

function queue_transactional_email(?int $tenantId, ?int $userId, string $recipient, string $subject, string $text): void
{
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/',$subject) || strlen($subject) > 180 || strlen($text) > 20000) {
        throw new InvalidArgumentException('Mensagem transacional inválida.');
    }
    $payload = json_encode(['to'=>$recipient,'subject'=>$subject,'text'=>$text], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    db()->prepare('INSERT INTO outbound_emails (tenant_id,user_id,payload_encrypted) VALUES (:tenant,:user,:payload)')
        ->execute(['tenant'=>$tenantId,'user'=>$userId,'payload'=>encrypt_secret($payload)]);
}

function issue_email_verification(int $tenantId, int $userId, string $name, string $email): void
{
    if(env('MAIL_ENABLED','false')!=='true'||!filter_var($email,FILTER_VALIDATE_EMAIL))return;
    $token=bin2hex(random_bytes(32));$pdo=db();
    $pdo->prepare('UPDATE email_verification_tokens SET used_at=NOW() WHERE tenant_id=:tenant AND user_id=:user AND used_at IS NULL')->execute(['tenant'=>$tenantId,'user'=>$userId]);
    $pdo->prepare('INSERT INTO email_verification_tokens (tenant_id,user_id,token_hash,email_hash,requested_ip_hash,expires_at) VALUES (:tenant,:user,:token,:email,:ip,DATE_ADD(NOW(),INTERVAL 24 HOUR))')->execute(['tenant'=>$tenantId,'user'=>$userId,'token'=>hash('sha256',$token),'email'=>hash('sha256',strtolower($email)),'ip'=>client_ip_hash()]);
    $url=rtrim((string)env('APP_URL',''),'/').'/verificar-email/'.$token;
    $text="Olá, {$name}.\n\nConfirme seu e-mail para proteger a conta da MJDev Digital:\n{$url}\n\nO link é válido por 24 horas. Se você não reconhece este cadastro, ignore esta mensagem.";
    queue_transactional_email($tenantId,$userId,$email,'Confirme seu e-mail · MJDev Digital',$text);
}

function issue_two_factor_challenge(array $user): array
{
    if(env('MAIL_ENABLED','false')!=='true'||!filter_var($user['email']??'',FILTER_VALIDATE_EMAIL))throw new RuntimeException('two_factor_delivery_unavailable');
    $challengeToken=bin2hex(random_bytes(32));$code=(string)random_int(100000,999999);$pdo=db();$pdo->beginTransaction();
    try{
        $pdo->prepare('UPDATE login_two_factor_challenges SET consumed_at=NOW() WHERE tenant_id=:tenant AND user_id=:user AND consumed_at IS NULL')->execute(['tenant'=>$user['tenant_id'],'user'=>$user['id']]);
        $pdo->prepare('INSERT INTO login_two_factor_challenges (tenant_id,user_id,challenge_token_hash,code_hash,requested_ip_hash,expires_at) VALUES (:tenant,:user,:challenge,:code,:ip,DATE_ADD(NOW(),INTERVAL 10 MINUTE))')->execute(['tenant'=>$user['tenant_id'],'user'=>$user['id'],'challenge'=>hash('sha256',$challengeToken),'code'=>hash_hmac('sha256',$challengeToken.'|'.$code,(string)env('APP_KEY','')),'ip'=>client_ip_hash()]);
        $challengeId=(int)$pdo->lastInsertId();$text="Olá, {$user['name']}.\n\nSeu código de acesso à MJDev Digital é: {$code}\n\nEle expira em 10 minutos e pode ser usado somente uma vez. Nunca informe este código a terceiros.";
        queue_transactional_email((int)$user['tenant_id'],(int)$user['id'],(string)$user['email'],'Código de acesso · MJDev Digital',$text);$pdo->commit();return ['id'=>$challengeId,'token'=>$challengeToken];
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function issue_two_factor_recovery_challenge(array $user): array
{
    $challengeToken=bin2hex(random_bytes(32));$pdo=db();$pdo->prepare('UPDATE login_two_factor_challenges SET consumed_at=NOW() WHERE tenant_id=:tenant AND user_id=:user AND consumed_at IS NULL')->execute(['tenant'=>$user['tenant_id'],'user'=>$user['id']]);
    $pdo->prepare('INSERT INTO login_two_factor_challenges (tenant_id,user_id,challenge_token_hash,code_hash,requested_ip_hash,expires_at) VALUES (:tenant,:user,:challenge,:code,:ip,DATE_ADD(NOW(),INTERVAL 10 MINUTE))')->execute(['tenant'=>$user['tenant_id'],'user'=>$user['id'],'challenge'=>hash('sha256',$challengeToken),'code'=>hash_hmac('sha256',random_bytes(32),(string)env('APP_KEY','')),'ip'=>client_ip_hash()]);
    return ['id'=>(int)$pdo->lastInsertId(),'token'=>$challengeToken];
}

function issue_new_device_alert(array $user, string $deviceLabel): void
{
    if(env('MAIL_ENABLED','false')!=='true'||empty($user['email_verified_at'])||empty($user['new_device_alerts_enabled'])||!filter_var($user['email']??'',FILTER_VALIDATE_EMAIL))return;
    $when=(new DateTimeImmutable('now'))->format('d/m/Y H:i');
    $text="Olá, {$user['name']}.\n\nUm novo dispositivo acessou sua conta da MJDev Digital.\n\nDispositivo: {$deviceLabel}\nData e hora: {$when}\n\nSe foi você, nenhuma ação é necessária. Se não reconhece este acesso, entre no painel, encerre a sessão em Configurações e altere sua senha imediatamente.";
    queue_transactional_email((int)$user['tenant_id'],(int)$user['id'],(string)$user['email'],'Novo dispositivo conectado · MJDev Digital',$text);
}

function issue_suspicious_login_alert(array $user, int $attempts): void
{
    if(env('MAIL_ENABLED','false')!=='true'||empty($user['email_verified_at'])||!filter_var($user['email']??'',FILTER_VALIDATE_EMAIL))return;
    $when=(new DateTimeImmutable('now'))->format('d/m/Y H:i');$safeAttempts=max(5,min($attempts,99));
    $text="Olá, {$user['name']}.\n\nDetectamos {$safeAttempts} tentativas de acesso sem sucesso à sua conta da MJDev Digital nos últimos 15 minutos.\n\nData e hora do alerta: {$when}\n\nSe foi você, aguarde alguns minutos antes de tentar novamente. Se não reconhece as tentativas, altere sua senha, revise as sessões ativas e mantenha a verificação em duas etapas habilitada.";
    queue_transactional_email((int)$user['tenant_id'],(int)$user['id'],(string)$user['email'],'Tentativas de acesso detectadas · MJDev Digital',$text);
}

function smtp_send(string $recipient, string $subject, string $text): void
{
    $host=trim((string)env('MAIL_HOST',''));$port=(int)env('MAIL_PORT','587');$encryption=strtolower(trim((string)env('MAIL_ENCRYPTION','tls')));
    $username=(string)env('MAIL_USERNAME','');$password=(string)env('MAIL_PASSWORD','');$from=(string)env('MAIL_FROM_ADDRESS','');$fromName=trim((string)env('MAIL_FROM_NAME','MJDev Digital'));
    if($host===''||$port<1||$port>65535||!filter_var($from,FILTER_VALIDATE_EMAIL)||!filter_var($recipient,FILTER_VALIDATE_EMAIL))throw new RuntimeException('mail_configuration');
    $transport=$encryption==='ssl'?'ssl://':'';$socket=@stream_socket_client($transport.$host.':'.$port,$errorNumber,$errorMessage,10,STREAM_CLIENT_CONNECT);
    if(!is_resource($socket))throw new RuntimeException('mail_connection');
    stream_set_timeout($socket,10);
    $read=static function()use($socket):string{$response='';do{$line=fgets($socket,515);if($line===false)break;$response.=$line;}while(isset($line[3])&&$line[3]==='-');return $response;};
    $command=static function(string $value,array $expected)use($socket,$read):string{fwrite($socket,$value."\r\n");$response=$read();$code=(int)substr($response,0,3);if(!in_array($code,$expected,true))throw new RuntimeException('mail_protocol_'.$code);return $response;};
    try{
        $greeting=$read();if((int)substr($greeting,0,3)!==220)throw new RuntimeException('mail_greeting');
        $command('EHLO mjdev.local',[250]);
        if($encryption==='tls'){$command('STARTTLS',[220]);if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('mail_tls');$command('EHLO mjdev.local',[250]);}
        if($username!==''||$password!==''){$command('AUTH LOGIN',[334]);$command(base64_encode($username),[334]);$command(base64_encode($password),[235]);}
        $command('MAIL FROM:<'.$from.'>',[250]);$command('RCPT TO:<'.$recipient.'>',[250,251]);$command('DATA',[354]);
        $encodedSubject='=?UTF-8?B?'.base64_encode($subject).'?=';$safeName=str_replace(["\r","\n",'"'],'',$fromName);$message="From: \"{$safeName}\" <{$from}>\r\nTo: <{$recipient}>\r\nSubject: {$encodedSubject}\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\nDate: ".date(DATE_RFC2822)."\r\nMessage-ID: <".bin2hex(random_bytes(16)).'@'.preg_replace('/[^a-z0-9.-]/i','',$host).">\r\n\r\n".str_replace("\n.","\n..",str_replace(["\r\n","\r"],"\n",$text));
        $command($message."\r\n.",[250]);$command('QUIT',[221]);
    }finally{fclose($socket);}
}
