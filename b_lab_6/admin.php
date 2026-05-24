<?php
header("Content-Type: text/html; charset=UTF-8");
session_start();
error_reporting(0);
ini_set('display_errors', 0);

function getDbConnection() {
    $host = 'localhost';
    $dbname = 'u82190';
    $user = 'u82190';
    $pass = '8528410';
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function initCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function authenticateAdmin() {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
        header('WWW-Authenticate: Basic realm="Admin Panel"');
        header('HTTP/1.0 401 Unauthorized');
        echo '<html><body><h1>401 Unauthorized</h1></body></html>';
        exit;
    }
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE login = :login");
    $stmt->execute([':login' => $_SERVER['PHP_AUTH_USER']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin || !password_verify($_SERVER['PHP_AUTH_PW'], $admin['password_hash'])) {
        header('WWW-Authenticate: Basic realm="Admin Panel"');
        header('HTTP/1.0 401 Unauthorized');
        echo '<html><body><h1>401 Unauthorized</h1></body></html>';
        exit;
    }
    
    return true;
}

authenticateAdmin();

$pdo = getDbConnection();

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM application_language WHERE application_id = ?");
    $stmt->execute([$id]);
    $stmt = $pdo->prepare("DELETE FROM application WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: admin.php');
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_id'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $message = '<div class="error">Ошибка безопасности. Попробуйте ещё раз.</div>';
    } else {
        $id = (int)$_POST['edit_id'];
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $birth_date = $_POST['birth_date'];
        $gender = $_POST['gender'];
        $biography = trim($_POST['biography']);
        $agreement = isset($_POST['agreement']) ? '1' : '0';
        $languages = $_POST['languages'] ?? [];
        
        $stmt = $pdo->prepare("UPDATE application SET full_name=?, phone=?, email=?, birth_date=?, gender=?, biography=?, agreement=? WHERE id=?");
        $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, $agreement, $id]);
        
        $stmt = $pdo->prepare("DELETE FROM application_language WHERE application_id = ?");
        $stmt->execute([$id]);
        
        $lang_stmt = $pdo->prepare("SELECT id, name FROM programming_language WHERE name = ?");
        foreach ($languages as $lang_name) {
            $lang_stmt->execute([$lang_name]);
            $lang = $lang_stmt->fetch(PDO::FETCH_ASSOC);
            if ($lang) {
                $pdo->prepare("INSERT INTO application_language (application_id, language_id) VALUES (?, ?)")->execute([$id, $lang['id']]);
            }
        }
        
        $message = '<div class="success">Запись успешно обновлена</div>';
    }
}

$stmt = $pdo->query("SELECT * FROM application ORDER BY id DESC");
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$lang_stats = [];
$stats_stmt = $pdo->query("
    SELECT pl.name, COUNT(al.id) as cnt 
    FROM programming_language pl 
    LEFT JOIN application_language al ON pl.id = al.language_id 
    GROUP BY pl.id, pl.name 
    ORDER BY cnt DESC"
);
while ($row = $stats_stmt->fetch(PDO::FETCH_ASSOC)) {
    $lang_stats[$row['name']] = $row['cnt'];
}

$edit_app = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM application WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_app = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_app) {
        $stmt = $pdo->prepare("SELECT pl.name FROM programming_language pl JOIN application_language al ON pl.id = al.language_id WHERE al.application_id = ?");
        $stmt->execute([$edit_id]);
        $edit_app['languages'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

initCsrfToken();
$csrf_token = $_SESSION['csrf_token'];
$languages_list = $pdo->query("SELECT name FROM programming_language")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #4CAF50; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        tr:hover { background: #f1f1f1; }
        .btn { padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 2px; }
        .btn-edit { background: #2196F3; color: white; }
        .btn-delete { background: #f44336; color: white; }
        .btn-back { background: #607D8B; color: white; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .form-group textarea { min-height: 80px; }
        .form-group select[multiple] { min-height: 100px; }
        .success { color: green; background: #e8f5e9; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .error { color: red; background: #ffebee; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .stats { background: #e3f2fd; padding: 15px; border-radius: 8px; margin-top: 30px; }
        .stats-item { display: inline-block; background: white; padding: 10px 15px; margin: 5px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .logout { float: right; }
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 5% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 600px; }
        .close { float: right; cursor: pointer; font-size: 24px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin.php" class="btn btn-back logout">Выйти</a>
        <h1>Админ-панель</h1>
        <?php if ($message) echo $message; ?>
        
        <h2>Все заявки (<?php echo count($applications); ?>)</h2>
        <?php if (empty($applications)): ?>
            <p>Заявок пока нет.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>ФИО</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Дата рождения</th>
                    <th>Пол</th>
                    <th>Языки</th>
                    <th>Действия</th>
                </tr>
                <?php foreach ($applications as $app): ?>
                    <?php
                    $stmt = $pdo->prepare("SELECT pl.name FROM programming_language pl JOIN application_language al ON pl.id = al.language_id WHERE al.application_id = ?");
                    $stmt->execute([$app['id']]);
                    $langs = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    ?>
                    <tr>
                        <td><?php echo $app['id']; ?></td>
                        <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($app['phone']); ?></td>
                        <td><?php echo htmlspecialchars($app['email']); ?></td>
                        <td><?php echo htmlspecialchars($app['birth_date']); ?></td>
                        <td><?php echo $app['gender'] == 'male' ? 'Мужской' : 'Женский'; ?></td>
                        <td><?php echo htmlspecialchars(implode(', ', $langs)); ?></td>
                        <td>
                            <a href="admin.php?edit=<?php echo $app['id']; ?>" class="btn btn-edit">Редактировать</a>
                            <a href="admin.php?delete=<?php echo $app['id']; ?>" class="btn btn-delete" onclick="return confirm('Удалить запись?')">Удалить</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
        
        <div class="stats">
            <h2>Статистика по языкам программирования</h2>
            <?php foreach ($lang_stats as $lang => $cnt): ?>
                <div class="stats-item">
                    <strong><?php echo htmlspecialchars($lang); ?></strong>: <?php echo $cnt; ?> чел.
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php if ($edit_app): ?>
    <div id="editModal" class="modal" style="display:block;">
        <div class="modal-content">
            <span class="close" onclick="window.location='admin.php'">&times;</span>
            <h2>Редактирование записи #<?php echo $edit_app['id']; ?></h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="edit_id" value="<?php echo $edit_app['id']; ?>">
                
                <div class="form-group">
                    <label for="full_name">ФИО:</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($edit_app['full_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Телефон:</label>
                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($edit_app['phone']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($edit_app['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="birth_date">Дата рождения:</label>
                    <input type="date" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($edit_app['birth_date']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Пол:</label>
                    <select name="gender" required>
                        <option value="male" <?php echo $edit_app['gender'] == 'male' ? 'selected' : ''; ?>>Мужской</option>
                        <option value="female" <?php echo $edit_app['gender'] == 'female' ? 'selected' : ''; ?>>Женский</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="biography">Биография:</label>
                    <textarea id="biography" name="biography"><?php echo htmlspecialchars($edit_app['biography']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="languages">Языки программирования:</label>
                    <select name="languages[]" id="languages" multiple size="5">
                        <?php foreach ($languages_list as $lang): ?>
                            <option value="<?php echo htmlspecialchars($lang); ?>" <?php echo in_array($lang, $edit_app['languages']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($lang); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="agreement" value="1" <?php echo $edit_app['agreement'] ? 'checked' : ''; ?>>
                        С контрактом ознакомлен
                    </label>
                </div>
                
                <button type="submit" class="btn btn-edit">Сохранить</button>
                <a href="admin.php" class="btn btn-back">Отмена</a>
            </form>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>