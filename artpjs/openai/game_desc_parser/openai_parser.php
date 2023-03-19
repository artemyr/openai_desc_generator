<?php

use Orhanerday\OpenAi\OpenAi;


ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php'; // remove this line if you use a PHP Framework.

function dd($var) {
    echo "<meta charset=\"utf-8\" />";
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
    die;
}

class ARTopenAIparser {
    private function savePost($id,$text,$arParams){
        $desc = mb_substr($text, 0,  150);

        $mysqli = new mysqli('localhost', $arParams['DB_LOGIN'], $arParams['DB_PASSWORD'], $arParams['DB_NAME']);
        $mysqli->set_charset('utf8mb4');
        $query = "UPDATE `dle_post` SET `short_story` = '$text', `descr` = '$desc' WHERE `id` = ".$id;
        $result = $mysqli->query($query);
        if($result) {
            $answer = (object)['ok' => true];
            echo json_encode($answer);
        }
        die;
    }
    private function getDesc($tokens,$text,$params){
        //        $arParams = $this->getSettgins();

        $open_ai = new OpenAi($params['OPENAI_TOKEN']);

        $complete = $open_ai->completion([
            'model' => $params['model'],
            'prompt' => $text,
            'max_tokens' => (int)$tokens,
            'temperature' => (int)$params['temperature'],
            'frequency_penalty' => (int)$params['frequency_penalty'],
            'presence_penalty' => (int)$params['presence_penalty'],
            'top_p' => (int)$params['top_p'],
        ]);

        //        $complete = $open_ai->chat([
        //            'model' => 'gpt-3.5-turbo',
        //            'messages' => [
        //                [
        //                    "role" => "system",
        //                    "content" => "You are a helpful assistant."
        //                ],
        //                [
        //                    "role" => "user",
        //                    "content" => "Who won the world series in 2020?"
        //                ],
        //                [
        //                    "role" => "assistant",
        //                    "content" => "The Los Angeles Dodgers won the World Series in 2020."
        //                ],
        //                [
        //                    "role" => "user",
        //                    "content" => "Where was it played?"
        //                ],
        //            ],
        //            'temperature' => 1.0,
        //            'max_tokens' => 4000,
        //            'frequency_penalty' => 0,
        //            'presence_penalty' => 0,
        //        ]);

        $complete = json_decode($complete);
        //        dd($complete);
        $answer = (object)['text' => trim($complete->choices[0]->text, " \n")];
        echo json_encode($answer);
        die;
    }
    private function template($pager, $arParams, $posts){
        ?>
        <head>
            <meta charset="utf-8" />
        </head>
        <body>

        <p>Описания постов. В начале пустые.</p>

        <?
        $this->settingsTemplate($arParams);
        ?>

        <?if($arParams['DB_CONNECT']):?>
            <br>
            <br>

            <button id="getAllDesc" onclick="getAllDesc()">Сгенерировать описания для всех постов на странице</button>
            <button id="saveAllDesc" onclick="saveAllDesc()">Сохранить все посты на странице</button>

            <br>
            <br>

            <?
            $this->pagerTemplate($pager,$arParams['COUNT']);
            ?>
            <div data-entity="posts">
            <?
            foreach ($posts as $post) {
                ?>
                <div class="post" data-entity="post" data-id="<?=$post['id']?>" id="id_<?=$post['id']?>">
                    <div>
                        Игра: <a target="_blank" href="/<?=$post['id']?>-<?=$post['alt_name']?>/"><?=$post['title']?></a>
                    </div>
                    <div>
                        <p>Запрос в нейронку будет выглядеть так</p>
                        <input size="255" name="query" value="<?=$post['OPENAI_INPUT_PHRASE']?>">
                    </div>
                    <br>
                    <div>
                        <textarea rows="20" name="gameshortstory"><?=$post['short_story']?></textarea>
                    </div>
                    <button name="gendesc" onclick="genDesc(<?=$post['id']?>)">Сгенерировать описание</button>
                    <br>
                    <button name="savedesc" onclick="saveDesc(<?=$post['id']?>)">Сохранить</button>
                </div>
                <br>
                <br>
                <?
            }
            ?>
            </div>
            <?
            $this->pagerTemplate($pager,$arParams['COUNT']);
            ?>

        <?endif;?>

        <style>
            textarea, input{
                width: 100%;
            }
            .post {
                border: 1px solid black;
                padding: 10px;
                margin: 10px;
            }
            #mainSettings table td{
                border: 1px solid black;
            }
            #mainSettings .hide {
                display: none;
            }
        </style>
        <script>
            function genDesc(id) {
                document.querySelector('#id_'+id+' [name=gendesc]').innerText = 'Жди!!';
                let tokens = document.querySelector('#tokens').value;
                let text = document.querySelector('#id_'+id+' [name=query]').value;
                fetch('<?$pager['DIR']?>?ajax=y&action=gen&tokens='+tokens+'&text='+text)
                    .then((response) => response.json())
                    .then((data) => {
                        document.querySelector('#id_'+id+' [name=gameshortstory]').value = data.text
                        document.querySelector('#id_'+id+' [name=gendesc]').innerText = 'Готово!!';
                    })
            }
            function saveDesc(id) {
                document.querySelector('#id_'+id+' [name=savedesc]').innerText = 'Сохраняется!!';
                let text = document.querySelector('#id_'+id+' [name=gameshortstory]').value;
                text = text.replace(/\n/g,"<br>");

                console.log(text);

                let data = { 
                    "id": id,
                    "text": text,
                    "action": 'save'
                };

                fetch("<?$pager['DIR']?>?ajax=y&action=save", {
                    method: "POST", // or 'PUT'
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify(data),
                })
                .then((response) => response.json())
                .then((data) => {
                    if (data.ok)
                        document.querySelector('#id_'+id+' [name=savedesc]').innerText = 'Сохранено!!';
                    else
                        alert('Ошибка!!');
                })
                .catch((error) => {
                    console.error("Error:", error);
                });




                // fetch('<?$pager['DIR']?>?ajax=y&action=save&id='+id+'&text='+text)
                //     .then((response) => response.json())
                //     .then((data) => {
                //         if (data.ok)
                //             document.querySelector('#id_'+id+' [name=savedesc]').innerText = 'Сохранено!!';
                //         else
                //             alert('Ошибка!!');
                //     })
            }
            function getAllDesc() {
                const btn = document.querySelector('#getAllDesc');
                btn.innerText = 'Жди!!'

                const postsCont = document.querySelector('[data-entity="posts"]');
                const posts = postsCont.querySelectorAll('[data-entity="post"]');
                for(let post of posts) {
                    genDesc(post.attributes['data-id'].value)
                }

                btn.innerText = 'Готово!!'
            }
            function saveAllDesc() {
                const btn = document.querySelector('#saveAllDesc');
                btn.innerText = 'Жди!!'

                const postsCont = document.querySelector('[data-entity="posts"]');
                const posts = postsCont.querySelectorAll('[data-entity="post"]');
                for(let post of posts) {
                    saveDesc(post.attributes['data-id'].value)
                }

                btn.innerText = 'Сохранено!!'
            }
            function editSettigns() {
                const settings = document.querySelector('#mainSettings');
                settings.querySelector('.spoiler').classList.add('hide');
                settings.querySelector('.edit').classList.remove('hide');
            }
            function saveSettings(){
                const settings = document.querySelector('#mainSettings');
                let arSettings = '';

                if(settings.querySelector('#tokens').value)
                    arSettings += "&TOKENS=" + settings.querySelector('#tokens').value

                if(settings.querySelector('#page_count').value)
                    arSettings += "&COUNT=" + settings.querySelector('#page_count').value

                if(settings.querySelector('#oai_token').value)
                    arSettings += "&OPENAI_TOKEN=" + settings.querySelector('#oai_token').value

                if(settings.querySelector('#phrase').value)
                    arSettings += "&OPENAI_INPUT_PHRASE=" + settings.querySelector('#phrase').value


                if(settings.querySelector('#db_name').value)
                    arSettings += "&DB_NAME=" + settings.querySelector('#db_name').value

                if(settings.querySelector('#db_login').value)
                    arSettings += "&DB_LOGIN=" + settings.querySelector('#db_login').value

                if(settings.querySelector('#db_pass').value)
                    arSettings += "&DB_PASSWORD=" + settings.querySelector('#db_pass').value

                if(settings.querySelector('#model').value)
                    arSettings += "&model=" + settings.querySelector('#model').value

                if(settings.querySelector('#temperature').value)
                    arSettings += "&temperature=" + settings.querySelector('#temperature').value

                if(settings.querySelector('#frequency_penalty').value)
                    arSettings += "&frequency_penalty=" + settings.querySelector('#frequency_penalty').value

                if(settings.querySelector('#presence_penalty').value)
                    arSettings += "&presence_penalty=" + settings.querySelector('#presence_penalty').value

                if(settings.querySelector('#top_p').value)
                    arSettings += "&top_p=" + settings.querySelector('#top_p').value


                fetch('<?$pager['DIR']?>?action=settingssave' + arSettings)
                    .then((response) => response.json())
                    .then((data) => {
                        if (data.ok)
                            location.href = location.pathname;
                        else
                            alert('Ошибка!!');
                    })
            }
        </script>
        </body>
        <?
    }
    private function settingsTemplate($arParams){
        ?>
        <div id="mainSettings">
            <p>Настройки</p>
            <div class="spoiler">
                <table>
                    <tr>
                        <td>Токены которые будут списаны за перевод одной статьи</td>
                        <td><?=$arParams['TOKENS']?></td>
                    </tr>
                    <tr>
                        <td>Количество постов на странице</td>
                        <td><?=$arParams['COUNT']?></td>
                    </tr>
                    <tr>
                        <td>Токен OpenAi</td>
                        <td><?=$arParams['OPENAI_TOKEN']?></td>
                    </tr>
                    <tr>
                        <td>Фраза для нейрасети ($GAME_TITLE$)</td>
                        <td><?=$arParams['OPENAI_INPUT_PHRASE']?></td>
                    </tr>
                    <tr>
                        <td>Имя базы данных</td>
                        <td><?=$arParams['DB_NAME']?></td>
                    </tr>
                    <tr>
                        <td>Пользователь базы данных</td>
                        <td><?=$arParams['DB_LOGIN']?></td>
                    </tr>
                    <tr>
                        <td>Пароль базы данных</td>
                        <td><?=$arParams['DB_PASSWORD']?></td>
                    </tr>
                    <tr>
                        <td>Модель ИИ</td>
                        <td><?=$arParams['model']?></td>
                    </tr>
                    <tr>
                        <td>Температура (temperature)</td>
                        <td><?=$arParams['temperature']?></td>
                    </tr>
                    <tr>
                        <td>Штраф за частоту (frequency_penalty)</td>
                        <td><?=$arParams['frequency_penalty']?></td>
                    </tr>
                    <tr>
                        <td>Штраф за присутствие (presence_penalty)</td>
                        <td><?=$arParams['presence_penalty']?></td>
                    </tr>
                    <tr>
                        <td>top_p</td>
                        <td><?=$arParams['top_p']?></td>
                    </tr>
                </table>
                <button onclick="editSettigns();">Настроить</button>
            </div>
            <div class="edit hide">
                <p>Токены которые будут списаны за перевод одной статьи <input id="tokens" value="<?=$arParams['TOKENS']?>"></p>
                <p>Количество постов на странице <input id="page_count" value="<?=$arParams['COUNT']?>"></p>
                <p>Токен OpenAi <input id="oai_token" value="<?=$arParams['OPENAI_TOKEN']?>"></p>
                <p>Фраза для нейрасети ($GAME_TITLE$) <input id="phrase" value="<?=$arParams['OPENAI_INPUT_PHRASE']?>"></p>
                <p>Имя базы данных <input id="db_name" value="<?=$arParams['DB_NAME']?>"></p>
                <p>Пользователь базы данных <input id="db_login" value="<?=$arParams['DB_LOGIN']?>"></p>
                <p>Пароль базы данных <input id="db_pass" value="<?=$arParams['DB_PASSWORD']?>"></p>

                <p>model <input id="model" value="<?=$arParams['model']?>"></p>
                <p>temperature <input id="temperature" value="<?=$arParams['temperature']?>"></p>
                <p>frequency_penalty <input id="frequency_penalty" value="<?=$arParams['frequency_penalty']?>"></p>
                <p>presence_penalty <input id="presence_penalty" value="<?=$arParams['presence_penalty']?>"></p>
                <p>top_p <input id="top_p" value="<?=$arParams['top_p']?>"></p>

                <button onclick="saveSettings();">Сохранить</button>
            </div>
        </div>
        <?php
    }
    private function pagerTemplate($pager,$pageCount){
        ?>
        <a href="<?=$pager['DIR']?>?PAGER=<?=$pager['BEFORE']?>&COUNT=<?=$pageCount?>">Предыдущая</a>
        <a href="<?=$pager['DIR']?>?PAGER=<?=$pager['AFTER']?>&COUNT=<?=$pageCount?>">Следующая</a>
        <p>Посты с <?=$pager['START']?> по <?=$pager['END']?> | текучая страница <?=$pager['CUR']?></p>
        <?php
    }
    private function showPosts($arParams){
        $page_el_count = $arParams['COUNT'];
        if (!empty($arParams['PAGER']) && is_numeric($arParams['PAGER']) && ($arParams['PAGER'] > 1)) {
            $start = $page_el_count * ($arParams['PAGER'] - 1);
            $end = $page_el_count * $arParams['PAGER'];
        } else {
            $start = 0;
            $end = $page_el_count;
        }

        $pager['START'] = $start;
        $pager['END'] = $end;
        $pager['CUR'] = $arParams['PAGER'];
        $pager['BEFORE'] = $arParams['PAGER'] > 1 ? $arParams['PAGER'] - 1 : $arParams['PAGER'];
        $pager['AFTER'] = $arParams['PAGER'] + 1;
        $pager['DIR'] = $_SERVER['SCRIPT_NAME'];

        $mysqli = new mysqli('localhost', $arParams['DB_LOGIN'], $arParams['DB_PASSWORD'], $arParams['DB_NAME']);

        $arPosts = [];
        if(!$mysqli->connect_errno) {
            $arParams['DB_CONNECT'] = true;
            $mysqli->set_charset('utf8mb4');
            $posts = $mysqli->query("SELECT `title`, `short_story`, `id`, `alt_name`, `xfields` FROM `dle_post` ORDER BY short_story ASC LIMIT $start, $page_el_count");

            $arphrase = explode("\$GAME_TITLE\$",$arParams['OPENAI_INPUT_PHRASE']);
            $arPosts = [];
            while ($post = $posts->fetch_assoc()) {

                $xfields = explode("||", $post['xfields']);
                foreach ($xfields as $field) {
                    $arField = explode("|",$field);
                    if($arField[0] == 'i_razrab') {
                        $provider = $arField[1];
                        if(!empty($provider)){
                            $post['title'] .= " от ".$provider;
                        }
                    }
                }

                $post['OPENAI_INPUT_PHRASE'] = $arphrase[0].$post['title'].$arphrase[1];
                $arPosts[] = $post;
            }
        } else {
            echo "база не подцепилась!!";
            $arParams['DB_CONNECT'] = false;
        }

        $this->template($pager, $arParams, $arPosts);
        die;
    }
    private function getSettgins(){
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $arParams = $dotenv->load();
        return $arParams;
    }
    private function getAppSettings() {
        $arParams = $this->getSettgins();

        if (!isset($_GET['PAGER'])) $arParams['PAGER'] = 1;
            else $arParams['PAGER'] = $_GET['PAGER'];

        if (isset($_GET['COUNT'])) $arParams['COUNT'] = $_GET['COUNT'];
        if (isset($_GET['TOKENS'])) $arParams['TOKENS'] = $_GET['TOKENS'];

        return $arParams;
    }
    private function settingsSave($newSettigs,$arParams){
        //        $arParams = $this->getSettgins();

        foreach ($arParams as $key => &$param){
            if(isset($newSettigs[$key]))
                $param = $newSettigs[$key];
        }

        $res = $this->writeSettings($arParams);
        if ($res){
            $answer = (object)['ok' => true];
            echo json_encode($answer);
        } else {
            $answer = (object)['ok' => false];
            echo json_encode($answer);
        }
        die;
    }
    private function writeSettings($params) {
        $myfile = fopen(".env", "w") or die("Unable to open file!");
        foreach ($params as $key => $param) {
            $txt = "$key=\"$param\"\n";
            fwrite($myfile, $txt);
        }
        return fclose($myfile);
    }
    public function exec() {
        $arParams = $this->getAppSettings();
        // dd($_GET);
        if(isset($_GET['ajax']) && $_GET['ajax'] == 'y') {
            if (!isset($_GET['action']))return;

            if($_GET['action'] == 'save'){
                $a = json_decode(file_get_contents('php://input'));
                $this->savePost($a->id, $a->text, $arParams);
            }

            if (!isset($_GET['tokens']))return;
            if($_GET['action'] == 'gen')
                $this->getDesc($_GET['tokens'], $_GET['text'], $arParams);
        }

        if(isset($_GET['action']) && $_GET['action'] == 'settingssave')
            $this->settingsSave($_GET,$arParams);

        $this->showPosts($arParams);
    }
}




$app = new ARTopenAIparser;
$app->exec();