<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $mode === 'login' ? 'Entrar' : ($mode === 'register' ? 'Criar conta' : ($mode === 'two-factor' ? 'Confirmar acesso' : 'Recuperar senha')) ?> · MJDev Digital</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/login.css?v=20260903-turnstile1">
</head>
<body class="pagina-autenticacao">
<a class="atalho-conteudo" href="#conteudo-principal">Pular para o formulário de acesso</a>
<main class="auth-shell" id="conteudo-principal" tabindex="-1">
    <section class="auth-apresentacao" aria-label="Apresentação da plataforma">
        <a class="marca" href="/"><span>MJ</span><strong>MJDev Digital</strong></a>
        <div class="auth-chamada">
            <span class="sobretitulo">COMÉRCIO DIGITAL COMPLETO</span>
            <h2>VENDA MAIS.<br><em>COMPLIQUE MENOS.</em></h2>
            <p>Organize sua vitrine, receba pedidos e acompanhe a operação em um só painel.</p>
            <ul class="auth-beneficios">
                <li><i>✓</i> Catálogo personalizado para sua empresa</li>
                <li><i>✓</i> Pedidos, clientes e estoque integrados</li>
                <li><i>✓</i> 14 dias grátis após a publicação</li>
            </ul>
        </div>
        <small>MJDev Digital · Plataforma para negócios que querem crescer</small>
    </section>
    <section class="cartao-auth">
        <span class="sobretitulo"><?= $mode === 'login' ? 'ACESSO SEGURO' : ($mode === 'register' ? 'COMECE GRÁTIS' : 'PROTEÇÃO DA CONTA') ?></span>
        <h1><?= $mode === 'login' ? 'ENTRAR NO PAINEL' : ($mode === 'register' ? 'CRIE SUA VITRINE' : ($mode === 'reset' ? 'NOVA SENHA' : ($mode === 'two-factor' ? 'CONFIRME O ACESSO' : 'RECUPERAR SENHA'))) ?></h1>
        <?php if ($flash): ?><p class="aviso <?= e($flash[0]) ?>" role="<?= $flash[0] === 'error' ? 'alert' : 'status' ?>"><?= e($flash[1]) ?></p><?php endif; ?>

        <?php if ($mode === 'login'): ?>
            <?php if (!empty($loginDestination)): ?><p class="aviso informativo">Entre com o acesso do funcionário para continuar em <?= e(admin_section_labels()[array_flip(admin_operational_routes())[$loginDestination]??'']??'seu painel operacional') ?>.</p><?php endif; ?>
            <form method="post" action="/entrar">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <label>E-mail<input type="email" name="email" autocomplete="username" required></label>
                <label>Senha
                    <span class="campo-senha">
                        <input type="password" name="password" autocomplete="current-password" required>
                        <button class="alternar-senha" type="button" aria-label="Mostrar senha">Mostrar</button>
                    </span>
                </label>
                <?= turnstile_widget('login') ?>
                <button class="botao primario" type="submit">Entrar no painel</button>
            </form>
            <?php if(env('MAIL_ENABLED','false')==='true'): ?><p><a href="/esqueci-senha">Esqueci minha senha</a></p><?php endif; ?>
            <p>Primeiro acesso? <a href="/cadastro">Crie sua conta</a>.</p>
        <?php elseif ($mode === 'register'): ?>
            <p>Seus 14 dias grátis começam após a publicação do catálogo.</p>
            <form method="post" action="/cadastro" id="registerForm">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <div class="grade-campos">
                    <label>Seu nome<input name="name" autocomplete="name" required></label>
                    <label>WhatsApp<input name="phone" autocomplete="tel" required></label>
                </div>
                <label>Nome da empresa<input name="company" autocomplete="organization" required></label>
                <label>CPF ou CNPJ do responsável<input name="document" inputmode="numeric" autocomplete="off" aria-describedby="documentHelp" required></label>
                <small id="documentHelp">Informe somente o documento do responsável pelo cadastro. Ele será validado com segurança no servidor.</small>
                <div class="grade-campos">
                    <label>Tipo de negócio
                        <select name="business_type" required><option>Alimentação</option><option>Moda e Vestuário</option><option>Beleza</option><option>Pet Shop</option><option>Casa e Decoração</option><option>Farmácia</option><option>Esportes</option><option>Informática</option><option>Outro</option></select>
                    </label>
                    <label>Cidade<input name="city" autocomplete="address-level2" required></label>
                </div>
                <label id="foodPresetField">Modelo de alimentação
                    <select name="food_preset"><?php foreach(storefront_presets_for_business_type('Alimentação') as$key=>$preset):?><option value="<?= e($key) ?>"><?= e($preset['name']) ?></option><?php endforeach;?></select>
                    <small>Criaremos um produto de exemplo em rascunho. Revise o preço, a descrição e adicione sua foto antes de ativá-lo.</small>
                </label>
                <label>E-mail de acesso<input type="email" name="email" autocomplete="username" required></label>
                <div class="grade-campos">
                    <label>Crie uma senha
                        <span class="campo-senha">
                            <input id="newPassword" type="password" name="password" minlength="15" maxlength="128" autocomplete="new-password" aria-describedby="passwordHelp passwordStatus" required>
                            <button class="alternar-senha" type="button" aria-label="Mostrar senha">Mostrar</button>
                        </span>
                    </label>
                    <label>Confirme a senha
                        <span class="campo-senha">
                            <input id="confirmPassword" type="password" name="password_confirm" minlength="15" maxlength="128" autocomplete="new-password" aria-describedby="confirmStatus" required>
                            <button class="alternar-senha" type="button" aria-label="Mostrar senha">Mostrar</button>
                        </span>
                    </label>
                </div>
                <div class="orientacao-senha" id="passwordHelp">
                    <strong>Use uma frase-senha com pelo menos 15 caracteres.</strong>
                    <span>Espaços e símbolos são permitidos. Evite nome, empresa, sequências e senhas reutilizadas.</span>
                    <div class="estado-senha"><span id="passwordStatus">0 de 15 caracteres</span><span id="confirmStatus">A confirmação deve ser igual.</span></div>
                </div>
                <label class="consentimento"><input type="checkbox" name="terms" value="1" required> Li e aceito os <a href="/termos" target="_blank" rel="noopener">Termos de Uso</a> e declaro ciência da <a href="/privacidade" target="_blank" rel="noopener">Política de Privacidade</a>.</label>
                <?= turnstile_widget('register') ?>
                <button class="botao primario" type="submit">Criar minha conta grátis</button>
            </form>
            <p>Já possui conta? <a href="/entrar">Entrar</a>.</p>
        <?php elseif ($mode === 'reset-request'): ?>
            <p>Informe o e-mail da conta. Se ele estiver cadastrado, enviaremos um link válido por 30 minutos.</p>
            <form method="post" action="/esqueci-senha">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <label>E-mail de acesso<input type="email" name="email" autocomplete="email" required></label>
                <?= turnstile_widget('password_reset') ?>
                <button class="botao primario" type="submit">Enviar instruções</button>
            </form>
            <p><a href="/entrar">Voltar para o login</a></p>
        <?php elseif ($mode === 'two-factor'): ?>
            <p>Enviamos um código de seis dígitos para o e-mail protegido da conta. Ele expira em dez minutos.</p>
            <form method="post" action="/verificar-acesso">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <label>Código de acesso ou recuperação<input type="text" name="code" pattern="(?:[0-9]{6}|MJ-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4})" minlength="6" maxlength="12" autocomplete="one-time-code" autocapitalize="characters" required autofocus></label>
                <button class="botao primario" type="submit">Confirmar acesso</button>
            </form>
            <form method="post" action="/verificar-acesso/reenviar">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <button class="botao" type="submit">Enviar novo código</button>
            </form>
            <p><a href="/entrar">Cancelar e voltar ao login</a></p>
        <?php else: ?>
            <p>Crie uma frase-senha nova. Todos os acessos anteriores serão encerrados.</p>
            <form method="post" action="/redefinir-senha/<?= e($resetToken) ?>">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <label>Nova senha<input type="password" name="password" minlength="15" maxlength="128" autocomplete="new-password" required></label>
                <label>Confirme a nova senha<input type="password" name="password_confirm" minlength="15" maxlength="128" autocomplete="new-password" required></label>
                <button class="botao primario" type="submit">Redefinir senha</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<script src="/assets/auth.js?v=20260904-onboarding1" defer></script>
<?= turnstile_script() ?>
</body>
</html>

