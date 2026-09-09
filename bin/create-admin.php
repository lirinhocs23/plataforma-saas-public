<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') exit("Somente CLI.\n");

function read_hidden_password(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    $stty = PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec') ? trim((string)shell_exec('command -v stty 2>/dev/null')) : '';
    if ($stty !== '') shell_exec('stty -echo');
    try { $password = trim((string)fgets(STDIN)); }
    finally { if ($stty !== '') { shell_exec('stty echo'); fwrite(STDOUT, PHP_EOL); } }
    return $password;
}

$pdo = db();
if ((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='platform_admin'")->fetchColumn() > 0) {
    exit("Já existe um administrador da plataforma. Crie outros usuários pelo painel.\n");
}
$email = strtolower(trim((string)readline('E-mail do administrador: ')));
$name = trim((string)readline('Nome: '));
$password = read_hidden_password('Senha (mínimo 15 caracteres): ');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($name) < 2 || strlen($password) < 15 || strlen($password) > 128) exit("Dados inválidos.\n");

$tenant = $pdo->query('SELECT id FROM tenants ORDER BY id LIMIT 1')->fetchColumn();
if (!$tenant) exit("Crie a primeira conta pela página de cadastro antes de promover um administrador.\n");
$stmt = $pdo->prepare("INSERT INTO users (tenant_id,name,email,password_hash,role,email_verified_at) VALUES (:tenant,:name,:email,:password,'platform_admin',NOW())");
try { $stmt->execute(['tenant'=>$tenant,'name'=>$name,'email'=>$email,'password'=>password_hash($password, password_algorithm())]); }
catch (PDOException $error) { exit($error->getCode() === '23000' ? "E-mail já cadastrado.\n" : "Não foi possível criar o administrador.\n"); }
echo "Administrador criado com sucesso.\n";

