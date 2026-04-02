<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'cardoso_estoque');
define('DB_USER', 'root');
define('DB_PASS', '');


try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}


$createTables = function($pdo) {
    $sqlFornecedores = "CREATE TABLE IF NOT EXISTS fornecedores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        cnpj VARCHAR(18) UNIQUE NOT NULL,
        telefone VARCHAR(20),
        email VARCHAR(100),
        endereco TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $sqlProdutos = "CREATE TABLE IF NOT EXISTS produtos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        codigo_barras VARCHAR(50) UNIQUE NOT NULL,
        preco DECIMAL(10,2) NOT NULL,
        quantidade INT NOT NULL DEFAULT 0,
        fornecedor_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL
    )";
    
    $sqlPessoas = "CREATE TABLE IF NOT EXISTS pessoas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        cpf VARCHAR(14) UNIQUE NOT NULL,
        telefone VARCHAR(20),
        email VARCHAR(100),
        endereco TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sqlFornecedores);
    $pdo->exec($sqlProdutos);
    $pdo->exec($sqlPessoas);
};
$createTables($pdo);


$success_msg = null;
$error_msg = null;


$fornecedor_errors = [];
$produto_errors = [];
$pessoa_errors = [];


$old_fornecedor = [];
$old_produto = [];
$old_pessoa = [];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_fornecedor') {
    $nome = trim($_POST['nome'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    
    $old_fornecedor = ['nome' => $nome, 'cnpj' => $cnpj, 'telefone' => $telefone, 'email' => $email, 'endereco' => $endereco];
    
    if (empty($nome)) $fornecedor_errors['nome'] = 'Nome é obrigatório';
    if (empty($cnpj)) $fornecedor_errors['cnpj'] = 'CNPJ é obrigatório';
    elseif (!preg_match('/^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$/', $cnpj) && !preg_match('/^\d{14}$/', $cnpj)) {
        $fornecedor_errors['cnpj'] = 'CNPJ inválido (formato: 00.000.000/0000-00 ou 14 dígitos)';
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $fornecedor_errors['email'] = 'E-mail inválido';
    
    if (empty($fornecedor_errors)) {
        $stmt = $pdo->prepare("SELECT id FROM fornecedores WHERE cnpj = ?");
        $stmt->execute([$cnpj]);
        if ($stmt->fetch()) {
            $fornecedor_errors['cnpj'] = 'Fornecedor com esse CNPJ já está cadastrado!';
        }
    }
    
    if (empty($fornecedor_errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO fornecedores (nome, cnpj, telefone, email, endereco) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $cnpj, $telefone, $email, $endereco]);
            $success_msg = "Fornecedor cadastrado com sucesso!";
            $old_fornecedor = [];
        } catch (PDOException $e) {
            $error_msg = "Erro ao cadastrar fornecedor: " . $e->getMessage();
        }
    } else {
        $error_msg = "Corrija os erros no formulário de fornecedor.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_produto') {
    $nome = trim($_POST['produto_nome'] ?? '');
    $codigo_barras = trim($_POST['codigo_barras'] ?? '');
    $preco = str_replace(',', '.', trim($_POST['preco'] ?? ''));
    $quantidade = intval($_POST['quantidade'] ?? 0);
    $fornecedor_id = intval($_POST['fornecedor_id'] ?? 0);
    
    $old_produto = ['nome' => $nome, 'codigo_barras' => $codigo_barras, 'preco' => $preco, 'quantidade' => $quantidade, 'fornecedor_id' => $fornecedor_id];
    

    if (empty($nome)) $produto_errors['nome'] = 'Nome do produto é obrigatório';
    if (empty($codigo_barras)) $produto_errors['codigo_barras'] = 'Código de barras é obrigatório';
    if (empty($preco) || !is_numeric($preco) || $preco <= 0) $produto_errors['preco'] = 'Preço deve ser um número positivo';
    if ($quantidade < 0) $produto_errors['quantidade'] = 'Quantidade não pode ser negativa';
    if ($fornecedor_id <= 0) $produto_errors['fornecedor_id'] = 'Selecione um fornecedor';
    else {
        $checkForn = $pdo->prepare("SELECT id FROM fornecedores WHERE id = ?");
        $checkForn->execute([$fornecedor_id]);
        if (!$checkForn->fetch()) $produto_errors['fornecedor_id'] = 'Fornecedor inválido';
    }
    
    
    if (empty($produto_errors) && !empty($codigo_barras)) {
        $stmt = $pdo->prepare("SELECT id FROM produtos WHERE codigo_barras = ?");
        $stmt->execute([$codigo_barras]);
        if ($stmt->fetch()) {
            $produto_errors['codigo_barras'] = 'Produto com este código de barras já está cadastrado!';
        }
    }
    
    if (empty($produto_errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO produtos (nome, codigo_barras, preco, quantidade, fornecedor_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $codigo_barras, $preco, $quantidade, $fornecedor_id]);
            $success_msg = "Produto cadastrado com sucesso!";
            $old_produto = [];
        } catch (PDOException $e) {
            $error_msg = "Erro ao cadastrar produto: " . $e->getMessage();
        }
    } else {
        $error_msg = "Corrija os erros no formulário de produto.";
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_pessoa') {
    $nome = trim($_POST['pessoa_nome'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $telefone = trim($_POST['pessoa_telefone'] ?? '');
    $email = trim($_POST['pessoa_email'] ?? '');
    $endereco = trim($_POST['pessoa_endereco'] ?? '');
    
    $old_pessoa = ['nome' => $nome, 'cpf' => $cpf, 'telefone' => $telefone, 'email' => $email, 'endereco' => $endereco];
    
    if (empty($nome)) $pessoa_errors['nome'] = 'Nome é obrigatório';
    if (empty($cpf)) $pessoa_errors['cpf'] = 'CPF é obrigatório';
    elseif (!preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', $cpf) && !preg_match('/^\d{11}$/', $cpf)) {
        $pessoa_errors['cpf'] = 'CPF inválido (formato: 000.000.000-00 ou 11 dígitos)';
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $pessoa_errors['email'] = 'E-mail inválido';
    
    if (empty($pessoa_errors)) {
        $stmt = $pdo->prepare("SELECT id FROM pessoas WHERE cpf = ?");
        $stmt->execute([$cpf]);
        if ($stmt->fetch()) {
            $pessoa_errors['cpf'] = 'Pessoa com este CPF já está cadastrada!';
        }
    }
    
    if (empty($pessoa_errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO pessoas (nome, cpf, telefone, email, endereco) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $cpf, $telefone, $email, $endereco]);
            $success_msg = "Pessoa cadastrada com sucesso!";
            $old_pessoa = [];
        } catch (PDOException $e) {
            $error_msg = "Erro ao cadastrar pessoa: " . $e->getMessage();
        }
    } else {
        $error_msg = "Corrija os erros no formulário de pessoa.";
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    $produto_id = intval($_POST['produto_id'] ?? 0);
    $delta = intval($_POST['delta'] ?? 0);
    
    if ($produto_id > 0 && $delta != 0) {
        try {
            
            $stmt = $pdo->prepare("SELECT quantidade FROM produtos WHERE id = ?");
            $stmt->execute([$produto_id]);
            $prod = $stmt->fetch();
            if ($prod) {
                $nova_quantidade = $prod['quantidade'] + $delta;
                if ($nova_quantidade < 0) {
                    $error_msg = "Estoque não pode ficar negativo!";
                } else {
                    $update = $pdo->prepare("UPDATE produtos SET quantidade = ? WHERE id = ?");
                    $update->execute([$nova_quantidade, $produto_id]);
                    $success_msg = "Estoque atualizado com sucesso!";
                }
            } else {
                $error_msg = "Produto não encontrado.";
            }
        } catch (PDOException $e) {
            $error_msg = "Erro ao atualizar estoque: " . $e->getMessage();
        }
    } else {
        $error_msg = "Operação inválida.";
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_fornecedor') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM fornecedores WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                $success_msg = "Fornecedor excluído com sucesso!";
            } else {
                $error_msg = "Fornecedor não encontrado.";
            }
        } catch (PDOException $e) {
            $error_msg = "Erro ao excluir fornecedor: " . $e->getMessage();
        }
    } else {
        $error_msg = "ID inválido para exclusão.";
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_produto') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                $success_msg = "Produto excluído com sucesso!";
            } else {
                $error_msg = "Produto não encontrado.";
            }
        } catch (PDOException $e) {
            $error_msg = "Erro ao excluir produto: " . $e->getMessage();
        }
    } else {
        $error_msg = "ID inválido para exclusão.";
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_pessoa') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM pessoas WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                $success_msg = "Pessoa excluída com sucesso!";
            } else {
                $error_msg = "Pessoa não encontrada.";
            }
        } catch (PDOException $e) {
            $error_msg = "Erro ao excluir pessoa: " . $e->getMessage();
        }
    } else {
        $error_msg = "ID inválido para exclusão.";
    }
}


$fornecedores = $pdo->query("SELECT * FROM fornecedores ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$produtos = $pdo->query("SELECT p.*, f.nome as fornecedor_nome FROM produtos p LEFT JOIN fornecedores f ON p.fornecedor_id = f.id ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC);
$pessoas = $pdo->query("SELECT * FROM pessoas ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CARDUUS</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        header {
            background: #1e3c72;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        header h1 {
            margin: 0;
            font-size: 2em;
        }
        header p {
            margin: 5px 0 0;
            opacity: 0.9;
        }
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: 0.2s;
        }
        .card:hover {
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .card-header {
            background: #2c3e66;
            color: white;
            padding: 15px 20px;
            font-size: 1.3em;
            font-weight: bold;
        }
        .card-body {
            padding: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            transition: 0.2s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #1e3c72;
            box-shadow: 0 0 0 2px rgba(30,60,114,0.2);
        }
        .error-text {
            color: #d9534f;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }
        .btn {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: 0.2s;
        }
        .btn:hover {
            background: #0f2a4a;
        }
        .btn-danger {
            background: #d9534f;
        }
        .btn-danger:hover {
            background: #c9302c;
        }
        .btn-success {
            background: #5cb85c;
        }
        .btn-success:hover {
            background: #4cae4c;
        }
        .btn-warning {
            background: #f0ad4e;
        }
        .btn-warning:hover {
            background: #ec971f;
        }
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        .alert-error {
            background: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fc;
            font-weight: 600;
        }
        .stock-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .stock-form input {
            width: 70px;
            text-align: center;
        }
        .badge {
            background: #e7f3ff;
            color: #1e3c72;
            padding: 4px 8px;
            border-radius: 20px;
            font-weight: bold;
        }
        .section-title {
            margin: 30px 0 15px;
            font-size: 1.5em;
            color: #1e3c72;
            border-left: 5px solid #1e3c72;
            padding-left: 15px;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            .stock-form {
                flex-direction: column;
            }
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1>CARDUUS</h1>
        <p>Controle de Estoque</p>
    </header>

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php elseif ($error_msg): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="dashboard">
        <!-- CADASTRO DE FORNECEDOR -->
        <div class="card">
            <div class="card-header">📦 Cadastrar Fornecedor</div>
            <div class="card-body">
                <form method="POST" id="formFornecedor" novalidate>
                    <input type="hidden" name="action" value="add_fornecedor">
                    <div class="form-group">
                        <label>Nome / Razão Social *</label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($old_fornecedor['nome'] ?? '') ?>">
                        <?php if (isset($fornecedor_errors['nome'])): ?>
                            <span class="error-text"><?= $fornecedor_errors['nome'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>CNPJ *</label>
                        <input type="text" name="cnpj" placeholder="00.000.000/0000-00" value="<?= htmlspecialchars($old_fornecedor['cnpj'] ?? '') ?>">
                        <?php if (isset($fornecedor_errors['cnpj'])): ?>
                            <span class="error-text"><?= $fornecedor_errors['cnpj'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" value="<?= htmlspecialchars($old_fornecedor['telefone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($old_fornecedor['email'] ?? '') ?>">
                        <?php if (isset($fornecedor_errors['email'])): ?>
                            <span class="error-text"><?= $fornecedor_errors['email'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Endereço</label>
                        <textarea name="endereco" rows="2"><?= htmlspecialchars($old_fornecedor['endereco'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn">✅ Cadastrar Fornecedor</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">🏷️ Cadastrar Produto</div>
            <div class="card-body">
                <form method="POST" id="formProduto" novalidate>
                    <input type="hidden" name="action" value="add_produto">
                    <div class="form-group">
                        <label>Nome do Produto *</label>
                        <input type="text" name="produto_nome" value="<?= htmlspecialchars($old_produto['nome'] ?? '') ?>">
                        <?php if (isset($produto_errors['nome'])): ?>
                            <span class="error-text"><?= $produto_errors['nome'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Código de Barras *</label>
                        <input type="text" name="codigo_barras" value="<?= htmlspecialchars($old_produto['codigo_barras'] ?? '') ?>">
                        <?php if (isset($produto_errors['codigo_barras'])): ?>
                            <span class="error-text"><?= $produto_errors['codigo_barras'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Preço (R$) *</label>
                        <input type="text" name="preco" step="0.01" value="<?= htmlspecialchars($old_produto['preco'] ?? '') ?>">
                        <?php if (isset($produto_errors['preco'])): ?>
                            <span class="error-text"><?= $produto_errors['preco'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Quantidade em Estoque</label>
                        <input type="number" name="quantidade" value="<?= htmlspecialchars($old_produto['quantidade'] ?? 0) ?>">
                        <?php if (isset($produto_errors['quantidade'])): ?>
                            <span class="error-text"><?= $produto_errors['quantidade'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Fornecedor *</label>
                        <select name="fornecedor_id">
                            <option value="">-- Selecione --</option>
                            <?php foreach ($fornecedores as $forn): ?>
                                <option value="<?= $forn['id'] ?>" <?= (isset($old_produto['fornecedor_id']) && $old_produto['fornecedor_id'] == $forn['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($forn['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($produto_errors['fornecedor_id'])): ?>
                            <span class="error-text"><?= $produto_errors['fornecedor_id'] ?></span>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn">➕ Cadastrar Produto</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">👤 Cadastrar Pessoa</div>
            <div class="card-body">
                <form method="POST" id="formPessoa" novalidate>
                    <input type="hidden" name="action" value="add_pessoa">
                    <div class="form-group">
                        <label>Nome Completo *</label>
                        <input type="text" name="pessoa_nome" value="<?= htmlspecialchars($old_pessoa['nome'] ?? '') ?>">
                        <?php if (isset($pessoa_errors['nome'])): ?>
                            <span class="error-text"><?= $pessoa_errors['nome'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>CPF *</label>
                        <input type="text" name="cpf" placeholder="000.000.000-00" value="<?= htmlspecialchars($old_pessoa['cpf'] ?? '') ?>">
                        <?php if (isset($pessoa_errors['cpf'])): ?>
                            <span class="error-text"><?= $pessoa_errors['cpf'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="pessoa_telefone" value="<?= htmlspecialchars($old_pessoa['telefone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="pessoa_email" value="<?= htmlspecialchars($old_pessoa['email'] ?? '') ?>">
                        <?php if (isset($pessoa_errors['email'])): ?>
                            <span class="error-text"><?= $pessoa_errors['email'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Endereço</label>
                        <textarea name="pessoa_endereco" rows="2"><?= htmlspecialchars($old_pessoa['endereco'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn">👥 Cadastrar Pessoa</button>
                </form>
            </div>
        </div>
    </div>

    <div class="section-title">📋 Fornecedores Cadastrados</div>
    <div class="card">
        <div class="card-body">
            <?php if (count($fornecedores) > 0): ?>
                 <table>
                    <thead>
                        <tr><th>ID</th><th>Nome</th><th>CNPJ</th><th>Telefone</th><th>Email</th><th>Ações</th>
                    </thead>
                    <tbody>
                        <?php foreach ($fornecedores as $f): ?>
                         <tr>
                             <td><?= $f['id'] ?></td>
                             <td><?= htmlspecialchars($f['nome']) ?></td>
                             <td><?= htmlspecialchars($f['cnpj']) ?></td>
                             <td><?= htmlspecialchars($f['telefone']) ?></td>
                             <td><?= htmlspecialchars($f['email']) ?></td>
                             <td>
                                 <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este fornecedor? Os produtos relacionados terão o fornecedor removido.');">
                                     <input type="hidden" name="action" value="delete_fornecedor">
                                     <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                     <button type="submit" class="btn btn-danger btn-sm">🗑️ Excluir</button>
                                 </form>
                             </td>
                         </tr>
                        <?php endforeach; ?>
                    </tbody>
                 </table>
            <?php else: ?>
                <p>Nenhum fornecedor cadastrado ainda.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="section-title">📊 Controle de Estoque</div>
    <div class="card">
        <div class="card-body">
            <?php if (count($produtos) > 0): ?>
                 <table>
                    <thead>
                        <tr><th>Produto</th><th>Cód. Barras</th><th>Preço</th><th>Estoque</th><th>Fornecedor</th><th>Ajuste Estoque</th><th>Ações</th>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $prod): ?>
                         <tr>
                             <td><?= htmlspecialchars($prod['nome']) ?></td>
                             <td><?= htmlspecialchars($prod['codigo_barras']) ?></td>
                             <td>R$ <?= number_format($prod['preco'], 2, ',', '.') ?></td>
                             <td><span class="badge"><?= $prod['quantidade'] ?> un.</span></td>
                             <td><?= htmlspecialchars($prod['fornecedor_nome'] ?? '—') ?></td>
                             <td>
                                <form method="POST" class="stock-form" style="display: flex; gap: 5px;">
                                    <input type="hidden" name="action" value="update_stock">
                                    <input type="hidden" name="produto_id" value="<?= $prod['id'] ?>">
                                    <input type="number" name="delta" placeholder="+/-" step="1" value="0" style="width: 70px;">
                                    <button type="submit" class="btn btn-success btn-sm">+/-</button>
                                </form>
                             </td>
                             <td>
                                <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este produto?');">
                                    <input type="hidden" name="action" value="delete_produto">
                                    <input type="hidden" name="id" value="<?= $prod['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">🗑️ Excluir</button>
                                </form>
                             </td>
                         </tr>
                        <?php endforeach; ?>
                    </tbody>
                 </table>
            <?php else: ?>
                <p>Nenhum produto cadastrado. Adicione produtos para controle de estoque.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="section-title">👥 Pessoas Cadastradas</div>
    <div class="card">
        <div class="card-body">
            <?php if (count($pessoas) > 0): ?>
                 <table>
                    <thead>
                        <tr><th>ID</th><th>Nome</th><th>CPF</th><th>Telefone</th><th>Email</th><th>Ações</th>
                    </thead>
                    <tbody>
                        <?php foreach ($pessoas as $pess): ?>
                         <tr>
                             <td><?= $pess['id'] ?></td>
                             <td><?= htmlspecialchars($pess['nome']) ?></td>
                             <td><?= htmlspecialchars($pess['cpf']) ?></td>
                             <td><?= htmlspecialchars($pess['telefone']) ?></td>
                             <td><?= htmlspecialchars($pess['email']) ?></td>
                             <td>
                                <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir esta pessoa?');">
                                    <input type="hidden" name="action" value="delete_pessoa">
                                    <input type="hidden" name="id" value="<?= $pess['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">🗑️ Excluir</button>
                                </form>
                             </td>
                         </tr>
                        <?php endforeach; ?>
                    </tbody>
                 </table>
            <?php else: ?>
                <p>Nenhuma pessoa cadastrada.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
   
    (function() {
        
        const formForn = document.getElementById('formFornecedor');
        if (formForn) {
            formForn.addEventListener('submit', function(e) {
                let isValid = true;
                clearErrors('fornecedor');
                const nome = formForn.querySelector('input[name="nome"]');
                const cnpj = formForn.querySelector('input[name="cnpj"]');
                const email = formForn.querySelector('input[name="email"]');
                if (!nome.value.trim()) { showError(nome, 'Nome é obrigatório', 'fornecedor'); isValid = false; }
                if (!cnpj.value.trim()) { showError(cnpj, 'CNPJ é obrigatório', 'fornecedor'); isValid = false; }
                else {
                    let cnpjClean = cnpj.value.replace(/\D/g, '');
                    if (cnpjClean.length !== 14) showError(cnpj, 'CNPJ deve ter 14 dígitos', 'fornecedor');
                }
                if (email.value && !/^\S+@\S+\.\S+$/.test(email.value)) showError(email, 'E-mail inválido', 'fornecedor');
                if (!isValid) e.preventDefault();
            });
        }

        
        const formProd = document.getElementById('formProduto');
        if (formProd) {
            formProd.addEventListener('submit', function(e) {
                let isValid = true;
                clearErrors('produto');
                const nome = formProd.querySelector('input[name="produto_nome"]');
                const codigo = formProd.querySelector('input[name="codigo_barras"]');
                const preco = formProd.querySelector('input[name="preco"]');
                const fornecedor = formProd.querySelector('select[name="fornecedor_id"]');
                if (!nome.value.trim()) { showError(nome, 'Nome do produto obrigatório', 'produto'); isValid = false; }
                if (!codigo.value.trim()) { showError(codigo, 'Código de barras obrigatório', 'produto'); isValid = false; }
                if (!preco.value.trim() || isNaN(parseFloat(preco.value)) || parseFloat(preco.value) <= 0) {
                    showError(preco, 'Preço deve ser um número positivo', 'produto');
                    isValid = false;
                }
                if (!fornecedor.value || fornecedor.value == '') {
                    showError(fornecedor, 'Selecione um fornecedor', 'produto');
                    isValid = false;
                }
                if (!isValid) e.preventDefault();
            });
        }

        
        const formPessoa = document.getElementById('formPessoa');
        if (formPessoa) {
            formPessoa.addEventListener('submit', function(e) {
                let isValid = true;
                clearErrors('pessoa');
                const nome = formPessoa.querySelector('input[name="pessoa_nome"]');
                const cpf = formPessoa.querySelector('input[name="cpf"]');
                const email = formPessoa.querySelector('input[name="pessoa_email"]');
                if (!nome.value.trim()) { showError(nome, 'Nome é obrigatório', 'pessoa'); isValid = false; }
                if (!cpf.value.trim()) { showError(cpf, 'CPF é obrigatório', 'pessoa'); isValid = false; }
                else {
                    let cpfClean = cpf.value.replace(/\D/g, '');
                    if (cpfClean.length !== 11) showError(cpf, 'CPF deve ter 11 dígitos', 'pessoa');
                }
                if (email.value && !/^\S+@\S+\.\S+$/.test(email.value)) showError(email, 'E-mail inválido', 'pessoa');
                if (!isValid) e.preventDefault();
            });
        }

        function showError(input, message, formType) {
            input.classList.add('error-input');
            let errorSpan = document.createElement('span');
            errorSpan.className = 'error-text';
            errorSpan.style.color = '#d9534f';
            errorSpan.innerText = message;
            input.parentNode.appendChild(errorSpan);
        }
        function clearErrors(formType) {
            document.querySelectorAll('.error-text').forEach(el => el.remove());
            document.querySelectorAll('.error-input').forEach(el => el.classList.remove('error-input'));
        }
    })();
</script>
<style>
    .error-input { border-color: #d9534f !important; background-color: #fff8f8; }
    .btn-sm { padding: 5px 10px; font-size: 12px; margin: 2px; }
    .action-buttons { display: flex; gap: 5px; }
    .stock-form .btn-success { background-color: #28a745; }
    .stock-form .btn-success:hover { background-color: #218838; }
    .btn-danger { background-color: #d9534f; }
    .btn-danger:hover { background-color: #c9302c; }
</style>
</body>
</html>
