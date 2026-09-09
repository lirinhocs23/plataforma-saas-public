<?php if(privileged_two_factor_required($user)):?>
<article class="cartao aviso-politica-seguranca" id="politica-2fa">
    <span class="sobretitulo">AÇÃO DE SEGURANÇA OBRIGATÓRIA</span>
    <h2>Ative a verificação em duas etapas</h2>
    <p>Até concluir a ativação, esta conta poderá acessar somente confirmação de e-mail, senha, sessões e os controles necessários do 2FA.</p>
    <?php if(env('MAIL_ENABLED','false')!=='true'):?><div class="aviso error">O administrador da plataforma precisa configurar o SMTP antes que esta política possa ser usada.</div><?php elseif(empty($user['email_verified_at'])):?><div class="aviso informativo">Confirme primeiro o endereço de e-mail desta conta e depois ative o 2FA.</div><?php else:?><a class="botao primario" href="#seguranca-conta">Configurar agora</a><?php endif;?>
</article>
<?php elseif(env('REQUIRE_PRIVILEGED_2FA','false')==='true'&&in_array($user['role'],['platform_admin','owner','manager'],true)):?>
<article class="aviso informativo"><b>2FA obrigatório e ativo</b><span>A política de segurança não permite desativar a verificação desta conta.</span></article>
<?php endif;?>
