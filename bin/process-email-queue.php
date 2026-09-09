<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
if(env('MAIL_ENABLED','false')!=='true'){fwrite(STDERR,"Envio de e-mail está desativado.\n");exit(2);}

$limit=max(1,min(50,(int)($argv[1]??10)));$processed=0;
for($i=0;$i<$limit;$i++){
    $pdo=db();$pdo->beginTransaction();
    try{
        $row=$pdo->query("SELECT * FROM outbound_emails WHERE (status='pending' OR (status='processing' AND locked_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE))) AND next_attempt_at<=NOW() ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED")->fetch();
        if(!$row){$pdo->commit();break;}
        $pdo->prepare("UPDATE outbound_emails SET status='processing',locked_at=NOW(),attempts=attempts+1 WHERE id=:id")->execute(['id'=>$row['id']]);$pdo->commit();
        try{
            $payload=json_decode(decrypt_secret($row['payload_encrypted']),true,8,JSON_THROW_ON_ERROR);smtp_send((string)$payload['to'],(string)$payload['subject'],(string)$payload['text']);
            $pdo->prepare("UPDATE outbound_emails SET status='sent',sent_at=NOW(),locked_at=NULL,last_error_code=NULL WHERE id=:id")->execute(['id'=>$row['id']]);
        }catch(Throwable $error){$attempts=(int)$row['attempts']+1;$failed=$attempts>=5;$delay=min(3600,60*(2**min(5,$attempts)));$pdo->prepare("UPDATE outbound_emails SET status=:status,next_attempt_at=:next_attempt,locked_at=NULL,last_error_code=:code WHERE id=:id")->execute(['status'=>$failed?'failed':'pending','next_attempt'=>date('Y-m-d H:i:s',time()+$delay),'code'=>substr($error->getMessage(),0,80),'id'=>$row['id']]);}
        $processed++;
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}
echo "Processados: {$processed}\n";
