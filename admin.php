<?php
session_start();

// --- Настройки логина ---
$admin_user = 'admin';
$admin_pass = '12345'; // Замените на свой пароль

// --- Авторизация ---
if (!isset($_SESSION['logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        if ($_POST['username'] === $admin_user && $_POST['password'] === $admin_pass) {
            $_SESSION['logged_in'] = true;
            header('Location: admin.php');
            exit;
        } else {
            $error = "Неверный логин или пароль";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Вход в админ-панель</title>
        <link rel="stylesheet" href="css/style.css">
        <link rel="stylesheet" href="css/logo.css">
        <style>
        .form-input, .form-textarea {
            width: 100%;
            max-width: 500px;
            padding: 10px 14px;
            margin: 8px 0 20px 0;
            border: 1.5px solid #bbb;
            border-radius: 7px;
            background: #232323;
            color: #fff;
            font-size: 18px;
            transition: border 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }
        .form-input:focus, .form-textarea:focus {
            border: 1.5px solid #4fc3f7;
            outline: none;
            box-shadow: 0 0 8px #4fc3f744;
        }
        .form-label {
            font-size: 19px;
            color: #e0e0e0;
            margin-bottom: 4px;
            display: block;
        }
        .form-btn {
            background: #4fc3f7;
            color: #222;
            border: none;
            border-radius: 7px;
            padding: 10px 22px;
            font-size: 18px;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s;
        }
        .form-btn:hover {
            background: #039be5;
            color: #fff;
        }
        </style>
    </head>
    <body>
        <div class="content">
            <h1>Вход в админ-панель</h1>
            <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
            <form method="POST">
                <input type="hidden" name="login" value="1">
                <label class="form-label">Логин:<br>
                    <input type="text" name="username" required class="form-input">
                </label><br>
                <label class="form-label">Пароль:<br>
                    <input type="password" name="password" required class="form-input">
                </label><br>
                <button type="submit" class="form-btn">Войти</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// --- Папка для новостей ---
$news_dir = __DIR__ . '/news';
if (!file_exists($news_dir)) mkdir($news_dir, 0755, true);

// --- Файл порядка новостей ---
$order_file = __DIR__ . '/news/news_order.json';

// --- Перемещение новости вверх/вниз ---
if (isset($_GET['move']) && isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $order = [];
    if (file_exists($order_file)) {
        $order = json_decode(file_get_contents($order_file), true);
    } else {
        $order = array_map('basename', glob($news_dir . '/news*.html'));
    }
    $idx = array_search($file, $order);
    if ($idx !== false) {
        if ($_GET['move'] === 'up' && $idx > 0) {
            $tmp = $order[$idx-1];
            $order[$idx-1] = $order[$idx];
            $order[$idx] = $tmp;
        }
        if ($_GET['move'] === 'down' && $idx < count($order)-1) {
            $tmp = $order[$idx+1];
            $order[$idx+1] = $order[$idx];
            $order[$idx] = $tmp;
        }
        file_put_contents($order_file, json_encode($order));
    }
    header('Location: admin.php');
    exit;
}

// --- Удаление новости и её картинок ---
if (isset($_GET['delete']) && isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $path = "$news_dir/$file";
    if (file_exists($path)) {
        // Удаляем все картинки, связанные с этой новостью
        if (preg_match('/news(\d+)/', $file, $m)) {
            $N = $m[1];
            $img_dir = __DIR__ . '/img/';
            $imgs = glob($img_dir . "news_{$N}_*");
            foreach ($imgs as $img) unlink($img);
        }
        unlink($path);
    }
    // Удаляем из порядка
    if (file_exists($order_file)) {
        $order = json_decode(file_get_contents($order_file), true);
        $order = array_values(array_filter($order, function($v) use ($file) { return $v !== $file; }));
        file_put_contents($order_file, json_encode($order));
    }
    header('Location: admin.php');
    exit;
}

// --- Скрытие/отображение новости ---
if (isset($_GET['toggle']) && isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $path = "$news_dir/$file";
    if (file_exists($path)) {
        if (strpos($file, '_hidden') === false) {
            rename($path, "$news_dir/" . str_replace('.html', '_hidden.html', $file));
        } else {
            rename($path, "$news_dir/" . str_replace('_hidden.html', '.html', $file));
        }
    }
    header('Location: admin.php');
    exit;
}

// --- Добавление новости ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    $title = htmlspecialchars($_POST['title']);
    $text = htmlspecialchars($_POST['content']);
    $date = date('d.m.Y');

    // --- Генерация имени файла ---
    $files = glob($news_dir . '/news*.html');
    $numbers = [];
    foreach ($files as $file) {
        if (preg_match('/news(\d+)\.html$/', $file, $matches)) $numbers[] = (int)$matches[1];
        if (preg_match('/news(\d+)_hidden\.html$/', $file, $matches)) $numbers[] = (int)$matches[1];
    }
    $next = $numbers ? max($numbers) + 1 : 1;
    $filename = sprintf('news%d.html', $next);

    // --- Загрузка картинок для новости ---
    $img_dir = __DIR__ . '/img/';
    if (!file_exists($img_dir)) mkdir($img_dir, 0755, true);

    // Картинка-превью
    $preview_img = '';
    if (!empty($_FILES['preview']['name'])) {
        $ext = pathinfo($_FILES['preview']['name'], PATHINFO_EXTENSION);
        $img_name = "news_{$next}_preview." . $ext;
        move_uploaded_file($_FILES['preview']['tmp_name'], $img_dir . $img_name);
        $preview_img = "img/$img_name";
    }

    // Картинки внутри новости
    $in_imgs = [];
    if (!empty($_FILES['in_images']['name'][0])) {
        foreach ($_FILES['in_images']['tmp_name'] as $k => $tmp_name) {
            if (!empty($_FILES['in_images']['name'][$k])) {
                $ext = pathinfo($_FILES['in_images']['name'][$k], PATHINFO_EXTENSION);
                $img_name = "news_{$next}_in_" . ($k+1) . "." . $ext;
                move_uploaded_file($tmp_name, $img_dir . $img_name);
                $in_imgs[] = "img/$img_name";
            }
        }
    }

    // --- Формируем HTML для ссылок ---
    $links_html = '';
    if (!empty($_POST['links_text']) && !empty($_POST['links_url'])) {
        foreach ($_POST['links_text'] as $i => $ltxt) {
            $ltxt = htmlspecialchars($ltxt);
            $lurl = htmlspecialchars($_POST['links_url'][$i]);
            if ($ltxt && $lurl) {
                if (!preg_match('~^https?://~', $lurl)) {
                    $lurl = 'https://' . $lurl;
                }
                $links_html .= '<a target="_blank" href="' . $lurl . '" class="newsa">' . $ltxt . '</a><br>';
            }
        }
    }

    // --- Формируем HTML для картинок внутри новости ---
    $in_imgs_html = '';
    foreach ($in_imgs as $img) {
        $in_imgs_html .= '<img src="../' . $img . '" class="half_img"><br>';
    }

    // --- Генерация HTML ---
    $html = <<<HTML
<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <link rel="stylesheet" href="../css/logo.css">
        <link rel="stylesheet" href="../css/style.css">
        <title>СНТ Ивушка/Новость</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </head>
    <body>
        <div class="topnav" id="myTopnav">
            <h1 class="title">СНТ Ивушка</h1>
            <a href="../index.php">Главная</a>
            <a href="../fotos.html">О нас</a>
            <a href="../government.html">Правление</a>
            <a href="../documents.html">Документы</a>
            <a href="../archive.html">Информация и объявления</a>
            <a href="../contacts.html">Контакты</a>
            <a href="javascript:void(0);" class="icon" onclick="myFunction()">
                <i class="fa fa-bars"></i>
            </a>
        </div>
        <div class="contentlist">
            <h1 class="newsh">{$title}</h1>
            <div class="line"></div>
            <p class="newstext"><em>{$date}</em></p>
            <div class="newstext">{$text}</div>
            {$in_imgs_html}
            {$links_html}
        </div>
        <div class="footer">
            <p style="text-align:end;padding:0; margin:0">
                <time style="float:left" datetime="2021">&copy;2021</time>
                <a style="font-size: 120%; color: white;" href="mailto: sntivushkacop@gmail.com">sntivushkacop@gmail.com</a>
                <a style="font-size: 105%; color: white;" target="_blank" href="https://t.me/sntivu">Мы в Телеграм</a>
                <a style="font-size: 105%; color: white;padding-right:10px" target="_blank" href="https://vk.com/sntivu">Мы в Вконтакте</a>
            </p>
            <!--<img class="soc" src="img/tel.png">
            <img class="soc" src="img/vk.png">-->
        </div>
        <script>
function myFunction() {
    var x = document.getElementById("myTopnav");
    if (x.className === "topnav") {
        x.className += " responsive";
    } else {
        x.className = "topnav";
    }
}
</script>
    </body>
</html>
HTML;

    file_put_contents("{$news_dir}/{$filename}", $html);

    // Добавляем новость в порядок (в начало)
    $order = [];
    if (file_exists($order_file)) {
        $order = json_decode(file_get_contents($order_file), true);
    }
    array_unshift($order, $filename);
    $order = array_unique($order);
    file_put_contents($order_file, json_encode($order));

    echo "<p>Новость успешно добавлена: <a href='news/{$filename}' target='_blank'>Посмотреть</a></p>";
    echo "<p><a href='admin.php'>Добавить ещё</a></p>";
    exit;
}

// --- Список новостей ---
$news_files = glob($news_dir . '/news*.html');
usort($news_files, function($a, $b) { return filemtime($b) - filemtime($a); });

function get_news_order($news_files, $order_file) {
    if (file_exists($order_file)) {
        $order = json_decode(file_get_contents($order_file), true);
        if (is_array($order)) {
            // Сортируем по сохранённому порядку, остальные в конец
            $ordered = [];
            foreach ($order as $fname) {
                $path = __DIR__ . '/news/' . $fname;
                if (in_array($path, $news_files)) $ordered[] = $path;
            }
            // Добавляем новые файлы, которых нет в порядке
            foreach ($news_files as $f) {
                if (!in_array($f, $ordered)) $ordered[] = $f;
            }
            return $ordered;
        }
    }
    return $news_files;
}

$news_files = get_news_order($news_files, $order_file);

// --- Разделяем на видимые и скрытые ---
$visible_news = [];
$hidden_news = [];
foreach ($news_files as $file) {
    if (strpos($file, '_hidden') !== false) {
        $hidden_news[] = $file;
    } else {
        $visible_news[] = $file;
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/logo.css">
    <style>
    .newsimg {
        max-width: 90%;
        display: block;
        margin: 20px auto;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.15);
    }
    .form-input, .form-textarea {
        width: 100%;
        max-width: 500px;
        padding: 10px 14px;
        margin: 8px 0 20px 0;
        border: none;
        border-radius: 7px;
        background: rgb(102, 100, 100, 0.8);
        color: #fff;
        font-size: 20px;
        transition: border 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
    }
    .form-input:focus, .form-textarea:focus {
        border: 1.5px solid #30de23;
        outline: none;
        box-shadow: 0 0 8px #4fc3f744;
    }
    .form-label {
        font-size: 25px;
        color: #e0e0e0;
        margin-bottom: 4px;
        display: block;
    }
    .form-btn {
        background: rgb(38, 166, 28, 0.8);
        color: #fff;
        border: none;
        border-radius: 7px;
        padding: 10px 22px;
        font-size: 18px;
        cursor: pointer;
        margin-top: 10px;
        transition: background 0.2s;
    }
    .form-btn:hover {
        background: #1e9e15;
        color: #fff;
    }
    .newslist {
        color:white; 
        font-size:20px;
    }
    .newslist li {
        padding:10px;
    }
    .is_hidden {
        color:red;
        font-weight: bold;
    }
    .open {
        text-decoration: none;
        color:white;
        background: rgb(102, 100, 100, 0.8);
        color: #fff;
        border: none;
        border-radius: 7px;
        padding: 5px 12px;
        font-size: 18px;
        cursor: pointer;
        margin-top: 10px;
        transition: background 0.2s;
    }
    .hidd {
        text-decoration: none;
        color:white;
        background: rgb(22, 135, 184, 0.8);
        color: #fff;
        border: none;
        border-radius: 7px;
        padding: 5px 12px;
        font-size: 18px;
        cursor: pointer;
        margin-top: 10px;
        transition: background 0.2s;
    }
    .delete {
        text-decoration: none;
        color:white;
        background: rgb(189, 30, 38, 0.8);
        color: #fff;
        border: none;
        border-radius: 7px;
        padding: 5px 12px;
        font-size: 18px;
        cursor: pointer;
        margin-top: 10px;
        margin-left:10px;
        transition: background 0.2s;
    }
    .move-btn {
        background: #444;
        color: #fff;
        border: none;
        border-radius: 7px;
        padding: 5px 10px;
        font-size: 18px;
        cursor: pointer;
        margin-left: 5px;
        margin-right: 5px;
        transition: background 0.2s;
    }
    .move-btn:hover {
        background: #222;
    }
    .news-section-title {
        color: #fff;
        font-size: 24px;
        margin-top: 30px;
        margin-bottom: 10px;
        border-bottom: 1px solid #555;
        padding-bottom: 5px;
    }
    </style>
</head>
<body>
    <div class="topnav" id="myTopnav">
        <h1 class="title">СНТ Ивушка</h1>
        <a href="index.php">Главная</a>
        <a href="fotos.html">О нас</a>
        <a href="government.html">Правление</a>
        <a href="documents.html">Документы</a>
        <a href="archive.html">Информация и объявления</a>
        <a href="contacts.html">Контакты</a>
        <a href="javascript:void(0);" class="icon" onclick="myFunction()">
            <i class="fa fa-bars"></i>
        </a>
    </div>
    <div class="contentlist">
        <h1 class="h1">Добавить новость</h1>
        <form method="POST" enctype="multipart/form-data" style="color:white; font-size:20px;padding-left:30px;">
            <label class="form-label">Заголовок:<br>
                <input type="text" name="title" required class="form-input">
            </label>
            <label class="form-label">Текст новости:<br>
                <textarea name="content" rows="6" class="form-textarea"></textarea>
            </label>
            <label class="form-label">Картинка для главной (preview):<br>
                <input type="file" name="preview" accept="image/*" class="form-input">
            </label>
            <label class="form-label">Картинки внутри новости (можно несколько):<br>
                <input type="file" name="in_images[]" accept="image/*" multiple class="form-input">
            </label>
            <div id="links">
                <label class="form-label">Ссылки:<br>
                    <input type="text" name="links_text[]" placeholder="Текст ссылки" class="form-input" style="max-width:300px;display:inline-block;">
                    <input type="text" name="links_url[]" placeholder="URL" class="form-input" style="max-width:300px;display:inline-block;">
                </label>
            </div>
            <button type="button" onclick="addLink()" class="form-btn" style="background:rgb(102, 100, 100, 0.7);color:#fff;">Добавить ещё ссылку</button>
            <br>
            <button type="submit" class="form-btn">Добавить новость</button>
        </form>
        <br>
        <h1 class="h1">Список новостей</h1>
        <div class="news-section-title">Показываемые на главной</div>
        <ul class="newslist">
        <?php foreach ($visible_news as $i => $file):
            $fname = basename($file);
            $title = '';
            $html = file_get_contents($file);
            if (preg_match('/<h1\s+class="newsh".*?>(.*?)<\/h1>/u', $html, $m)) $title = $m[1];
        ?>
            <li>
                <?php echo $title ?: $fname; ?>
                <a class="open" href="news/<?php echo $fname; ?>" target="_blank">Открыть</a>
                <a class="hidd" href="?toggle=1&file=<?php echo urlencode($fname); ?>">Скрыть</a>
                <a class="delete" href="?delete=1&file=<?php echo urlencode($fname); ?>" onclick="return confirm('Удалить новость и все её картинки?')">Удалить</a>
            </li>
        <?php endforeach; ?>
        </ul>
        <div class="news-section-title">Скрытые (не показываются на главной)</div>
        <ul class="newslist">
        <?php foreach ($hidden_news as $i => $file):
            $fname = basename($file);
            $title = '';
            $html = file_get_contents($file);
            if (preg_match('/<h1\s+class="newsh".*?>(.*?)<\/h1>/u', $html, $m)) $title = $m[1];
        ?>
            <li>
                <?php echo $title ?: $fname; ?>
                <a class="open" href="news/<?php echo $fname; ?>" target="_blank">Открыть</a>
                <a class="hidd" href="?toggle=1&file=<?php echo urlencode($fname); ?>">Добавить</a>
                <a class="delete" href="?delete=1&file=<?php echo urlencode($fname); ?>" onclick="return confirm('Удалить новость и все её картинки?')">Удалить</a>
                <?php if ($i > 0): ?>
                    <a href="?move=up&file=<?= urlencode($fname) ?>" class="move-btn" title="Вверх">&#8593;</a>
                <?php endif; ?>
                <?php if ($i < count($hidden_news)-1): ?>
                    <a href="?move=down&file=<?= urlencode($fname) ?>" class="move-btn" title="Вниз">&#8595;</a>
                <?php endif; ?>
                <span class="is_hidden">(скрыта)</span>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
    <script>
    function addLink() {
        var div = document.createElement('div');
        div.innerHTML = '<input type="text" name="links_text[]" placeholder="Текст ссылки" class="form-input" style="max-width:300px;display:inline-block;"> <input type="text" name="links_url[]" placeholder="URL" class="form-input" style="max-width:300px;display:inline-block;">';
        document.getElementById('links').appendChild(div);
    }
    function myFunction() {
        var x = document.getElementById("myTopnav");
        if (x.className === "topnav") {
            x.className += " responsive";
        } else {
            x.className = "topnav";
        }
    }
    </script>
        <div class="footer">
            <p style="text-align:end;padding:0; margin:0">
                <time style="float:left" datetime="2021">&copy;2021-2025</time>
                <a style="font-size: 120%; color: white;" href="mailto: sntivushkacop@gmail.com">sntivushkacop@gmail.com</a>
                <!--<a style="font-size: 105%; color: white;" target="_blank" href="https://t.me/sntivu">Мы в Телеграм</a>
                <a style="font-size: 105%; color: white;padding-right:10px" target="_blank" href="https://vk.com/sntivu">Мы в Вконтакте</a>-->
            </p>
        </div>
</body>
</html>
