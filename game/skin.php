<!DOCTYPE html>
<html lang="zh-Hant">

<?php $playerName = $_POST["name"];?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tic-Tac-Toe</title>

    <style>
        /* 自定義對話框樣式 */
        #customDialog {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            border: 1px solid black;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        #overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        button {
            margin: 5px;
        }
    </style>
</head>

<body>
    <h1>你的暱稱: <span id="playerName"></span></h1>
    <h1>你的ID: <span id="playerId"></span></h1>
    <h2>線上玩家列表:</h2>
    <ul id="playerList"></ul>

    <input type="text" id="targetIdInput" placeholder="輸入要挑戰玩家的ID">
    <button onclick="challengePlayer()">挑戰</button>

    <!-- 自定義對話框 -->
    <div id="overlay"></div>
    <div id="customDialog">
        <p id="dialogMessage"></p>
        <button id="acceptButton">接受</button>
        <button id="declineButton">拒絕</button>
    </div>

    <script>
        // 取得玩家名稱
        let playerName = sessionStorage.getItem('playerName');

        // 馬的, 被坑了sessionStorage取出是string
        console.log("raw playerName=" + playerName, "type:", typeof playerName);
        if (!playerName || playerName === "null") {
            playerName = <?php echo json_encode($playerName); ?>;
        }
        document.getElementById('playerName').textContent = playerName;

        // 若不存在玩家 ID，則生成並保存到 sessionStorage
        let playerId = sessionStorage.getItem('playerId');
        if (!playerId) {
            playerId = Math.random().toString(36).substr(2, 9);
            sessionStorage.setItem('playerId', playerId);
        }
        document.getElementById('playerId').textContent = playerId;

        let first;
        let conn = new WebSocket('ws://192.168.66.188:8080');

        conn.onopen = () => console.log("WebSocket connected!");
        conn.onmessage = (e) => {
            let data = JSON.parse(e.data);

            switch (data.action) {
                case 'requestPlayerId':
                    // 當伺服器請求玩家 ID 時，回傳當前玩家 ID
                    conn.send(JSON.stringify({
                        action: 'replyPlayerId',
                        playerId: playerId,
                        playerName: playerName,
                        game: null
                    }));
                    break;

                case 'playerList':
                    updatePlayerList(data.players);
                    break;

                case 'challenge':
                    // (accept)-> 處理callback結果,accept是回傳值
                    showCustomDialog(`${data.from}想跟你玩,你要嗎AUA?`, (accept) => {
                        if (accept) {
                            conn.send(JSON.stringify({
                                action: 'challenge_confirm',
                                otherplayer: data.fromid,
                                player: playerId,
                            }));
                        } else {
                            conn.send(JSON.stringify({
                                action: 'challenge_not_confirm',
                            }));
                        }
                    });
                    break;

                    // 切換到遊戲頁面
                case 'change_page':
                    let roomId = sessionStorage.setItem('roomId', data.game);
                    let first = sessionStorage.setItem('first', data.first);
                    window.location.href = "tic-tac-toe.php?";
                    break;
            }
        };

        // 更新玩家列表
        function updatePlayerList(players) {
            const list = document.getElementById('playerList');
            list.innerHTML = players
                .map(p => `<li>${p.name}(ID => ${p.id})</li>`)
                .join('');
        }
        function showAlertDialog(message, callback) {
            const dialog = document.getElementById('customDialog');
            const overlay = document.getElementById('overlay');
            const messageEl = document.getElementById('dialogMessage');
            const acceptButton = document.getElementById('acceptButton');
            const declineButton = document.getElementById('declineButton');

            messageEl.textContent = message;
            dialog.style.display = 'block';
            overlay.style.display = 'block';

            acceptButton.textContent = '關閉';
            acceptButton.style.display = 'inline-block';
            declineButton.style.display = 'none';
            //置中
            acceptButton.style.display = 'block'; // 改成 block 讓 margin auto 生效
            acceptButton.style.marginLeft = 'auto';
            acceptButton.style.marginRight = 'auto';
            
            acceptButton.onclick = () => {
                closeCustomDialog();
                if (callback) callback();
            };
        }

        // 發起挑戰
        function challengePlayer(targetId) {
            targetId = document.getElementById('targetIdInput').value;

            // 無效玩家沒做
            if (targetId === playerId) {
                alert("你不能挑戰自己！");
                return;
            }
            if (targetId) {
                conn.send(JSON.stringify({
                    action: 'challenge',
                    targetId: targetId
                }));
            } else {
                
                alert("請輸入有效的玩家 ID!");
            }
        }

        // 顯示自定義對話框
        function showCustomDialog(message, callback) {
            const dialog = document.getElementById('customDialog');
            const overlay = document.getElementById('overlay');
            const messageEl = document.getElementById('dialogMessage');
            const acceptButton = document.getElementById('acceptButton');
            const declineButton = document.getElementById('declineButton');

            // 設定訊息
            messageEl.textContent = message;

            // 顯示對話框和遮罩
            dialog.style.display = 'block';
            overlay.style.display = 'block';

            // 按鈕事件處理
            acceptButton.onclick = () => {
                closeCustomDialog();
                callback(true);
            };

            declineButton.onclick = () => {
                closeCustomDialog();
                callback(false);
            };
        }

        // 關閉對話框
        function closeCustomDialog() {
            document.getElementById('customDialog').style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
        }
    </script>
</body>

</html>