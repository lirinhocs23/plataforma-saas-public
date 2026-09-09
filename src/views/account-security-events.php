<?php
$securityEventLabels=[
    'login|user'=>'Login concluído',
    'logout|user'=>'Saída da conta',
    'password_change|user'=>'Senha alterada',
    'new_device|user_session'=>'Novo dispositivo reconhecido',
    'revoke|user_session'=>'Acesso de um dispositivo encerrado',
    'revoke_others|user_session'=>'Outros acessos encerrados',
    'verify|user_email'=>'E-mail confirmado',
    'request|email_verification'=>'Confirmação de e-mail solicitada',
    'enable|two_factor'=>'Verificação em duas etapas ativada',
    'disable|two_factor'=>'Verificação em duas etapas desativada',
    'complete|two_factor_login'=>'Login confirmado com 2FA',
    'complete|two_factor_recovery_login'=>'Login confirmado com código de recuperação',
    'regenerate|two_factor_recovery_codes'=>'Códigos de recuperação renovados',
    'update|security_alert_preferences'=>'Preferência de alerta atualizada',
    'suspicious|login_attempt'=>'Tentativas de acesso suspeitas detectadas',
];
?>
<article class="cartao" id="historico-seguranca">
    <div class="cabecalho-cartao"><div><span class="sobretitulo">HISTÓRICO DE SEGURANÇA</span><h2>Atividades da sua conta</h2><p class="suave">Eventos recentes registrados pelo servidor. Dados técnicos, endereços e hashes não são exibidos.</p></div><span class="etiqueta"><?= count($accountSecurityEvents) ?> EVENTO(S)</span></div>
    <?php if(!$accountSecurityEvents):?><div class="aviso informativo">Nenhuma atividade de segurança foi registrada para esta conta.</div><?php else:?><div class="lista-sessoes-conta lista-eventos-seguranca">
        <?php foreach($accountSecurityEvents as$securityEvent):$eventKey=$securityEvent['action'].'|'.$securityEvent['entity'];$eventLabel=$securityEventLabels[$eventKey]??'Atividade de segurança';?>
            <article><div><b><?= e($eventLabel) ?></b><small><?= e((new DateTimeImmutable($securityEvent['created_at']))->format('d/m/Y H:i')) ?></small></div><span class="etiqueta verde">REGISTRADO</span></article>
        <?php endforeach;?>
    </div><?php endif;?>
</article>
