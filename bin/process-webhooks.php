<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
if(PHP_SAPI!=='cli')exit("Somente CLI.\n");

$limit=max(1,min(100,(int)($argv[1]??25)));$pdo=db();$jobs=$pdo->query("SELECT wd.*,we.endpoint_url,we.secret_encrypted FROM webhook_deliveries wd JOIN webhook_endpoints we ON we.id=wd.endpoint_id AND we.tenant_id=wd.tenant_id WHERE wd.delivered_at IS NULL AND wd.attempts<8 AND wd.next_attempt_at<=NOW() AND we.active=1 ORDER BY wd.id LIMIT {$limit}")->fetchAll();
foreach($jobs as$job){$id=(int)$job['id'];$url=$job['endpoint_url'];$payload=$job['payload'];$attempt=(int)$job['attempts']+1;$status=null;$error=null;
    try{if(!public_https_url($url))throw new RuntimeException('Destino deixou de apontar para rede pública.');$host=(string)parse_url($url,PHP_URL_HOST);$ip=(gethostbynamel($host)?:[])[0]??null;if(!$ip)throw new RuntimeException('Destino sem IPv4 público.');$secret=decrypt_secret($job['secret_encrypted']);$timestamp=(string)time();$signature=hash_hmac('sha256',$timestamp.'.'.$payload,$secret);$curl=curl_init($url);curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_RESOLVE=>[$host.':443:'.$ip],CURLOPT_HTTPHEADER=>['Content-Type: application/json','User-Agent: MJDev-Webhook/1.0','X-MJDev-Event: '.$job['event_name'],'X-MJDev-Timestamp: '.$timestamp,'X-MJDev-Signature: sha256='.$signature]]);curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$curlError=curl_error($curl);curl_close($curl);if($curlError!==''||$status<200||$status>=300)throw new RuntimeException($curlError?:'HTTP '.$status);
        $pdo->prepare('UPDATE webhook_deliveries SET attempts=:attempts,response_status=:status,last_error=NULL,delivered_at=NOW() WHERE id=:id')->execute(['attempts'=>$attempt,'status'=>$status,'id'=>$id]);echo "OK {$id}\n";
    }catch(Throwable $e){$error=substr($e->getMessage(),0,1000);$delay=min(86400,60*(2**min($attempt,10)));$next=date('Y-m-d H:i:s',time()+$delay);$pdo->prepare('UPDATE webhook_deliveries SET attempts=:attempts,response_status=:status,last_error=:error,next_attempt_at=:next WHERE id=:id')->execute(['attempts'=>$attempt,'status'=>$status?:null,'error'=>$error,'next'=>$next,'id'=>$id]);echo "FALHA {$id}: {$error}\n";}
}

