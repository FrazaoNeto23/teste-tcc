<?php
session_start();
include "config.php";
include "helpers.php";

verificar_login('dono');

// ADICIONAR MESA
if (isset($_POST['adicionar_mesa'])) {
    $numero = sanitizar_texto($_POST['numero']);
    $qr_code = 'QR_MESA_' . strtoupper($numero);
    
    $stmt = $conn->prepare("INSERT INTO mesas (numero, qr_code) VALUES (?, ?)");
    $stmt->bind_param("ss", $numero, $qr_code);
    $stmt->execute();
    
    redirecionar('gerenciar_mesas.php', 'Mesa adicionada com sucesso!');
}

// EDITAR MESA
if (isset($_POST['editar_mesa'])) {
    $id = (int) $_POST['id'];
    $numero = sanitizar_texto($_POST['numero']);
    $ativa = isset($_POST['ativa']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE mesas SET numero=?, ativa=? WHERE id=?");
    $stmt->bind_param("sii", $numero, $ativa, $id);
    $stmt->execute();
    
    redirecionar('gerenciar_mesas.php', 'Mesa atualizada!');
}

// DELETAR MESA
if (isset($_GET['deletar'])) {
    $id = (int) $_GET['deletar'];
    $conn->query("DELETE FROM mesas WHERE id=$id");
    redirecionar('gerenciar_mesas.php', 'Mesa removida!');
}

// LIBERAR MESA (marcar como desocupada)
if (isset($_GET['liberar'])) {
    $id = (int) $_GET['liberar'];
    $conn->query("UPDATE mesas SET ocupada=0 WHERE id=$id");
    redirecionar('gerenciar_mesas.php', 'Mesa liberada!');
}

// BUSCAR MESAS
$mesas = $conn->query("SELECT * FROM mesas ORDER BY CAST(numero AS UNSIGNED), numero")->fetch_all(MYSQLI_ASSOC);

// EDITAR - Buscar mesa específica
$mesa_editar = null;
if (isset($_GET['editar'])) {
    $mesa_editar = $conn->query("SELECT * FROM mesas WHERE id=" . (int) $_GET['editar'])->fetch_assoc();
}

// URL base do sistema
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Mesas - Burger House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="header-container">
            <div class="logo"><i class="fas fa-chair"></i> GERENCIAR MESAS</div>
            <div style="display: flex; gap: 15px;">
                <a href="painel_dono.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['sucesso'] ?></div>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>

        <!-- FORMULÁRIO -->
        <div class="card">
            <h2><i class="fas fa-<?= $mesa_editar ? 'edit' : 'plus' ?>"></i> <?= $mesa_editar ? 'Editar' : 'Adicionar' ?> Mesa</h2>
            
            <form method="POST">
                <?php if ($mesa_editar): ?>
                    <input type="hidden" name="id" value="<?= $mesa_editar['id'] ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> Número da Mesa *</label>
                    <input type="text" name="numero" value="<?= $mesa_editar['numero'] ?? '' ?>" required placeholder="Ex: 1, 2, A1, VIP-01">
                </div>
                
                <?php if ($mesa_editar): ?>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="ativa" <?= $mesa_editar['ativa'] ? 'checked' : '' ?>>
                            Mesa ativa (disponível para uso)
                        </label>
                    </div>
                <?php endif; ?>
                
                <div style="display:flex;gap:10px;">
                    <button type="submit" name="<?= $mesa_editar ? 'editar_mesa' : 'adicionar_mesa' ?>" class="btn btn-success">
                        <i class="fas fa-save"></i> <?= $mesa_editar ? 'Atualizar' : 'Adicionar' ?>
                    </button>
                    <?php if ($mesa_editar): ?>
                        <a href="gerenciar_mesas.php" class="btn btn-danger">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- LISTA DE MESAS -->
        <div class="card">
            <h2><i class="fas fa-list"></i> Mesas Cadastradas (<?= count($mesas) ?>)</h2>
            
            <?php if (empty($mesas)): ?>
                <p style="text-align:center;color:#999;padding:40px;">Nenhuma mesa cadastrada</p>
            <?php else: ?>
                <div class="mesas-grid">
                    <?php foreach ($mesas as $m): ?>
                        <div class="mesa-card <?= $m['ocupada'] ? 'mesa-ocupada' : '' ?> <?= !$m['ativa'] ? 'mesa-inativa' : '' ?>">
                            <div class="mesa-numero">
                                <i class="fas fa-chair"></i>
                                Mesa <?= htmlspecialchars($m['numero']) ?>
                            </div>
                            
                            <div class="mesa-status">
                                <?php if (!$m['ativa']): ?>
                                    <span class="badge badge-danger">INATIVA</span>
                                <?php elseif ($m['ocupada']): ?>
                                    <span class="badge badge-warning">OCUPADA</span>
                                <?php else: ?>
                                    <span class="badge badge-success">LIVRE</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mesa-qr">
                                <div class="qr-placeholder" id="qr-<?= $m['id'] ?>">
                                    <i class="fas fa-qrcode"></i>
                                    <p>QR Code</p>
                                </div>
                            </div>
                            
                            <div class="mesa-url">
                                <small>URL de acesso:</small>
                                <input type="text" readonly value="<?= $base_url ?>/pedido_mesa.php?mesa=<?= $m['id'] ?>" onclick="this.select()" style="font-size:11px;padding:8px;margin-top:5px;">
                            </div>
                            
                            <div class="mesa-acoes">
                                <a href="pedido_mesa.php?mesa=<?= $m['id'] ?>" target="_blank" class="btn btn-primary btn-sm">
                                    <i class="fas fa-external-link-alt"></i> Abrir
                                </a>
                                <a href="?editar=<?= $m['id'] ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <?php if ($m['ocupada']): ?>
                                    <a href="?liberar=<?= $m['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Liberar esta mesa?')">
                                        <i class="fas fa-unlock"></i> Liberar
                                    </a>
                                <?php endif; ?>
                                <a href="?deletar=<?= $m['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Deletar esta mesa?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-info-circle"></i> Como Funciona?</h2>
            <div style="line-height: 1.8;">
                <p><strong>1.</strong> Cada mesa tem um QR Code único que leva à página de pedidos</p>
                <p><strong>2.</strong> Clientes escaneiam o QR Code com o celular</p>
                <p><strong>3.</strong> Fazem o pedido diretamente pelo celular</p>
                <p><strong>4.</strong> Quando terminarem, podem solicitar a conta pelo app</p>
                <p><strong>5.</strong> Você recebe notificação quando a conta é solicitada</p>
            </div>
        </div>
    </div>

    <style>
        .mesas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .mesa-card {
            background: white;
            border: 3px solid #10b981;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .mesa-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .mesa-ocupada {
            border-color: #f59e0b;
            background: linear-gradient(135deg, #fff 0%, #fef3c7 100%);
        }

        .mesa-inativa {
            border-color: #dc2626;
            background: linear-gradient(135deg, #fff 0%, #fee2e2 100%);
            opacity: 0.7;
        }

        .mesa-numero {
            font-size: 32px;
            font-weight: 800;
            color: #1f2937;
            margin-bottom: 16px;
        }

        .mesa-numero i {
            color: #10b981;
            margin-right: 8px;
        }

        .mesa-status {
            margin-bottom: 20px;
        }

        .mesa-qr {
            margin: 20px 0;
        }

        .qr-placeholder {
            width: 150px;
            height: 150px;
            margin: 0 auto;
            background: #f3f4f6;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px dashed #9ca3af;
        }

        .qr-placeholder i {
            font-size: 48px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .qr-placeholder p {
            color: #6b7280;
            font-size: 14px;
            margin: 0;
        }

        .mesa-url {
            margin: 16px 0;
        }

        .mesa-url small {
            color: #6b7280;
            font-size: 12px;
        }

        .mesa-acoes {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 16px;
        }

        .btn-sm {
            padding: 8px 14px;
            font-size: 13px;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script>
        // Gerar QR Codes para cada mesa
        <?php foreach ($mesas as $m): ?>
            QRCode.toCanvas(
                document.createElement('canvas'),
                '<?= $base_url ?>/pedido_mesa.php?mesa=<?= $m['id'] ?>',
                { width: 150, margin: 1 },
                function (error, canvas) {
                    if (!error) {
                        document.getElementById('qr-<?= $m['id'] ?>').innerHTML = '';
                        document.getElementById('qr-<?= $m['id'] ?>').appendChild(canvas);
                    }
                }
            );
        <?php endforeach; ?>
    </script>
</body>
</html>
