<?php
require_once "config.php";

function httpAuth(): void
{
    if (!isset($_SERVER["PHP_AUTH_USER"]) || !isset($_SERVER["PHP_AUTH_PW"])) {
        header('WWW-Authenticate: Basic realm="Admin Panel"');
        header("HTTP/1.0 401 Unauthorized");
        echo "Доступ запрещен. Требуется авторизация.";
        exit();
    }

    if (
        $_SERVER["PHP_AUTH_USER"] !== ADMIN_LOGIN ||
        $_SERVER["PHP_AUTH_PW"] !== ADMIN_PASS
    ) {
        header('WWW-Authenticate: Basic realm="Admin Panel"');
        header("HTTP/1.0 401 Unauthorized");
        echo "Неверный логин или пароль.";
        exit();
    }
}

httpAuth();

$validBouquets = ["black-moon", "blue-evening", "moonlight", "custom"];
$bouquetNames = [
    "black-moon" => "Полуночный сад",
    "blue-evening" => "Сияние ночи",
    "moonlight" => "Лунные розы",
    "custom" => "Индивидуальный ночной букет",
];

$message = "";

try {
    $pdo = getDB();

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        if (isset($_POST["delete"])) {
            $id = (int) ($_POST["id"] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Заказ #" . $id . " удален.";
            }
        }

        if (isset($_POST["update"])) {
            $id = (int) ($_POST["id"] ?? 0);
            $name = trim($_POST["name"] ?? "");
            $phone = trim($_POST["phone"] ?? "");
            $messageText = trim($_POST["message"] ?? "");
            $bouquets = $_POST["bouquets"] ?? [];

            $selectedBouquets = [];
            foreach ((array) $bouquets as $b) {
                if (in_array($b, $validBouquets)) {
                    $selectedBouquets[] = $b;
                }
            }

            if ($id > 0 && $name && $phone && !empty($selectedBouquets)) {
                $stmt = $pdo->prepare(
                    "UPDATE orders SET name = ?, phone = ?, message = ? WHERE id = ?",
                );
                $stmt->execute([$name, $phone, $messageText, $id]);

                $stmt = $pdo->prepare(
                    "DELETE FROM order_bouquets WHERE order_id = ?",
                );
                $stmt->execute([$id]);

                $stmt = $pdo->prepare(
                    "INSERT INTO order_bouquets (order_id, bouquet_id) VALUES (?, (SELECT id FROM bouquets WHERE code = ?))",
                );
                foreach ($selectedBouquets as $bouquetCode) {
                    $stmt->execute([$id, $bouquetCode]);
                }

                $message = "Заказ #" . $id . " обновлен.";
            } else {
                $message = "Ошибка: заполните все обязательные поля.";
            }
        }
    }

    $orders = $pdo
        ->query(
            "
        SELECT o.*, GROUP_CONCAT(b.code) as bouquet_codes, GROUP_CONCAT(b.name SEPARATOR ', ') as bouquet_names
        FROM orders o
        LEFT JOIN order_bouquets ob ON o.id = ob.order_id
        LEFT JOIN bouquets b ON ob.bouquet_id = b.id
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ",
        )
        ->fetchAll();

    $stats = $pdo
        ->query(
            "
        SELECT b.name, b.code, COUNT(ob.id) as count
        FROM bouquets b
        LEFT JOIN order_bouquets ob ON b.id = ob.bouquet_id
        GROUP BY b.id
        ORDER BY count DESC
    ",
        )
        ->fetchAll();

    $editOrder = null;
    if (isset($_GET["edit"])) {
        $editId = (int) $_GET["edit"];
        foreach ($orders as $o) {
            if ($o["id"] === $editId) {
                $editOrder = $o;
                break;
            }
        }
    }
} catch (PDOException $e) {
    $message = "Ошибка БД: " . $e->getMessage();
    $orders = [];
    $stats = [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - LunaFlora</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-shield-alt"></i> Admin Panel</h1>
                <p class="text-muted">Управление заказами LunaFlora</p>
            </div>
            <div>
                <span class="user"><i class="fas fa-user"></i> <?= htmlspecialchars(ADMIN_LOGIN) ?></span>
                <a href="index.php" class="btn btn-cancel" style="margin-left:10px;"><i class="fas fa-arrow-left"></i> На сайт</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <h2><i class="fas fa-chart-bar"></i> Статистика букетов</h2>
        <div class="stats-grid">
            <?php foreach ($stats as $stat): ?>
                <div class="stat-card">
                    <div class="count"><?= (int) $stat["count"] ?></div>
                    <div class="label"><?= htmlspecialchars($stat["name"]) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($editOrder): ?>
        <div class="form-edit">
            <h3><i class="fas fa-edit"></i> Редактирование заказа #<?= (int) $editOrder["id"] ?></h3>
            <form method="POST">
                <input type="hidden" name="id" value="<?= (int) $editOrder["id"] ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Имя</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($editOrder["name"]) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Телефон</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editOrder["phone"]) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Букеты</label>
                        <div class="checkbox-group">
                        <?php
                        $editBouquets = $editOrder["bouquet_codes"]
                            ? explode(",", $editOrder["bouquet_codes"])
                            : [];
                        foreach ($bouquetNames as $code => $bname):
                            $checked = in_array($code, $editBouquets) ? "checked" : "";
                        ?>
                            <label>
                                <input type="checkbox" name="bouquets[]" value="<?= $code ?>" <?= $checked ?>>
                                <?= htmlspecialchars($bname) ?>
                            </label>
                        <?php endforeach;
                        ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Пожелания</label>
                    <textarea name="message" class="form-control" rows="3"><?= htmlspecialchars($editOrder["message"] ?? "") ?></textarea>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" name="update" class="btn btn-save"><i class="fas fa-save"></i> Сохранить</button>
                    <a href="admin.php" class="btn btn-cancel"><i class="fas fa-times"></i> Отменить</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <h2><i class="fas fa-list"></i> Все заказы (<?= count($orders) ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Имя</th>
                    <th>Телефон</th>
                    <th>Букеты</th>
                    <th>Пожелания</th>
                    <th>Логин</th>
                    <th>Дата</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="8" style="text-align:center;color:#6c757d;">Заказов нет</td></tr>
                <?php endif; ?>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= (int) $order["id"] ?></td>
                    <td><?= htmlspecialchars($order["name"]) ?></td>
                    <td><?= htmlspecialchars($order["phone"]) ?></td>
                    <td>
                        <?php
                        $codes = $order["bouquet_codes"]
                            ? explode(",", $order["bouquet_codes"])
                            : [];
                        foreach ($codes as $c):
                            $bname = htmlspecialchars($bouquetNames[$c] ?? $c);
                        ?>
                            <span class="badge"><?= $bname ?></span>
                        <?php endforeach;
                        ?>
                    </td>
                    <td style="max-width:200px;word-break:break-word;"><?= htmlspecialchars($order["message"] ?? "-") ?></td>
                    <td class="text-muted"><?= htmlspecialchars($order["login"]) ?></td>
                    <td class="text-muted" style="white-space:nowrap;"><?= htmlspecialchars($order["created_at"]) ?></td>
                    <td>
                        <div class="actions">
                            <a href="?edit=<?= (int) $order["id"] ?>" class="btn btn-edit"><i class="fas fa-pen"></i></a>
                            <form method="POST" onsubmit="return confirm('Удалить заказ #<?= (int) $order["id"] ?>?');" style="display:inline;">
                                <input type="hidden" name="id" value="<?= (int) $order["id"] ?>">
                                <button type="submit" name="delete" class="btn btn-delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
