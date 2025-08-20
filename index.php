<?php
// index.php

// Получаем список новостей (только не скрытые)
$news_dir = __DIR__ . '/news';
$news_files = glob($news_dir . '/news*.html');

// --- Порядок новостей из order-файла ---
$order_file = __DIR__ . '/news/news_order.json';
if (file_exists($order_file)) {
    $order = json_decode(file_get_contents($order_file), true);
    $ordered = [];
    foreach ($order as $fname) {
        $path = $news_dir . '/' . $fname;
        if (in_array($path, $news_files) && strpos($path, '_hidden') === false) $ordered[] = $path;
    }
    // Добавляем новые файлы, которых нет в порядке
    foreach ($news_files as $f) {
        if (!in_array($f, $ordered) && strpos($f, '_hidden') === false) $ordered[] = $f;
    }
    $visible_news = $ordered;
} else {
    $visible_news = [];
    foreach ($news_files as $file) {
        if (strpos($file, '_hidden') === false) {
            $visible_news[] = $file;
        }
    }
    // Сортировка по времени (новые сверху)
    usort($visible_news, function($a, $b) { return filemtime($b) - filemtime($a); });
}

// Функция для извлечения заголовка
function get_news_title($file) {
    $html = file_get_contents($file);
    if (preg_match('/<h1.*?>(.*?)<\/h1>/u', $html, $m)) return $m[1];
    return '';
}

// Функция для получения превью-картинки
function get_news_preview_img($file) {
    if (preg_match('/news(\d+)/', basename($file), $m)) {
        $N = $m[1];
        $img_dir = __DIR__ . '/img/';
        $img_files = glob($img_dir . "news_{$N}_preview.*");
        if ($img_files && file_exists($img_files[0])) {
            return 'img/' . basename($img_files[0]);
        }
    }
    return 'img/no_img.png';
}
?>
<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <link rel="stylesheet" href="css/style.css">
        <link rel="stylesheet" href="css/logo.css">
        <title>СНТ Ивушка/Главная</title>
        <meta charset="utf-8">
        <style>
        .newsimg {
            width:400px;
            height:300px;
            object-fit:cover;
            display:block;
        }
        @media screen and (max-width: 940px) {
            .newsimg {
                width:290px;
                height:180px;
            }
        }
        </style>
    </head>
    <body>
        <div class="topnav" id="myTopnav">
            <h1 class="title">СНТ Ивушка</h1>
            <a href="index.php" class="active">Главная</a>
            <a href="fotos.html">О нас</a>
            <a href="government.html">Правление</a>
            <a href="documents.html">Документы</a>
            <a href="archive.html">Информация и объявления</a>
            <a href="contacts.html">Контакты</a>
            <a href="javascript:void(0);" class="icon" onclick="myFunction()">
                <i class="fa fa-bars"></i>
            </a>
        </div>
        <div class="content">
            <ul>
                <?php foreach ($visible_news as $file):
                    $fname = basename($file);
                    $title = get_news_title($file);
                    $img = get_news_preview_img($file);
                ?>
                <il>
                    <span class="des">
                        <p class="des_text"><?= htmlspecialchars($title) ?></p>
                        <a href="news/<?= $fname ?>" class="bt">Подробнее</a>
                    </span>
                    <img class="newsimg" src="<?= htmlspecialchars($img) ?>">
                </il>
                <?php endforeach; ?>
            </ul>
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
        <div class="footer">
            <p style="text-align:end;padding:0; margin:0">
                <time style="float:left" datetime="2021">&copy;2021</time>
                <a style="font-size: 120%; color: white;" href="mailto: sntivushkacop@gmail.com">sntivushkacop@gmail.com</a>
                <a style="font-size: 105%; color: white;" target="_blank" href="https://t.me/sntivu">Мы в Телеграм</a>
                <a style="font-size: 105%; color: white;padding-right:10px" target="_blank" href="https://vk.com/sntivu">Мы в Вконтакте</a>
            </p>
        </div>
</body>
</html>

