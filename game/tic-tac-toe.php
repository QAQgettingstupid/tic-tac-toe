<?php
header("Content-Security-Policy: connect-src 'self' ws://192.168.66.188:8080;");
?>
<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tic-Tac-Toe</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
        }

        .board {
            display: grid;
            grid-template-columns: repeat(3, 80px);
            gap: 8px;
            margin: 30px auto;
            width: 264px;
        }

        .cell {
            width: 80px;
            height: 80px;
            font-size: 2.5em;
            border: 2px solid #333;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            background: #fff;
        }

        .cell.win {
            background: #ffe066;
        }

        #status {
            margin: 20px;
            font-size: 1.2em;
        }

        #restart {
            margin: 10px;
        }
    </style>
</head>

<body>
    <h1>Tic-Tac-Toe</h1>
    <div class="board">
        <?php for ($i = 0; $i < 9; $i++): ?>
            <div class="cell" data-index="<?= $i ?>"></div>
        <?php endfor; ?>
    </div>
    <script>
        //從上頁取回玩家 ID 和房間 ID
        let playerId = sessionStorage.getItem('playerId');
        let roomId = sessionStorage.getItem('roomId');
        let playerName = sessionStorage.getItem('playerName');
        let first = sessionStorage.getItem('first') === 'true'; // === 'true' -> 將字串轉為布林值
        let round;
        // 先手為X
        let symbol = first ? 'X' : 'O';

        // 建立 WebSocket 連線
        let conn = new WebSocket('ws://192.168.66.188:8080');
        conn.onopen = () => {
            // 以下可刪,單純除錯用
            console.log("WebSocket connection established!");
        };

        conn.onmessage = (e) => {
            let data = JSON.parse(e.data);

            switch (data.action) {

                // 當伺服器請求玩家 ID 時，回傳當前玩家 ID
                case 'requestPlayerId':
                    conn.send(JSON.stringify({
                        action: 'replyPlayerId',
                        playerId: playerId,
                        playerName: playerName,
                        game: roomId
                    }));
                    //請求初始化
                    conn.send(JSON.stringify({
                        action: 'updateBoard',
                        playerId: playerId,
                        game: roomId
                    }));
                    break;

                    // 更新棋盤
                case 'updateBoard':
                    console.log("update");
                    round = data.round; // 更新回合數
                    updateBoard(data.board);
                    break;

                case 'gameOver':
                    if (data.result === 'win') {
                        if (data.winner == first)
                            updateTitle(`你贏了！`);
                        else
                            updateTitle(`你輸了QAQ`);

                        // 標記勝利格
                        highlightWin(data.line);
                    } else if (data.result === 'draw') {
                        updateTitle('平手！');
                    }

                    // 遊戲結束後禁止再下棋
                    disableBoard();
                    break;
            }
        };

        //更新當前棋盤狀態
        function updateBoard(board) {
            console.log("round: " + round);

            //理論上要對手寫對手的玩家名但我懶還沒改
            if (first) {
                if (round % 2)
                    updateTitle("你的回合(你是X)");
                else
                    updateTitle("對手的回合(你是X)");
            } else {
                if (!(round % 2))
                    updateTitle("你的回合(你是O)");
                else
                    updateTitle("對手的回合(你是O)");
            }

            // symbol在此為區域變數, 代表格內元素
            board.forEach((symbol, index) => {
                let cell = document.querySelector(`.cell[data-index='${index}']`);
                if (cell) {
                    cell.textContent = symbol || ''; // 如果 symbol 為 null 則顯示空格

                    cell.classList.remove('taken');

                    if (
                        (first && !(round % 2)) || // 你先手但現在是對方回合
                        (!first && (round % 2)) || // 你後手但現在是對方回合
                        symbol // 或這格已經有棋子
                    ) {
                        cell.classList.add('taken');
                    }
                }
            });
        }

        // 更新標題
        function updateTitle(newTitle) {
            const titleElement = document.querySelector('h1');
            titleElement.textContent = newTitle;
        }

        // 標記勝利格
        function highlightWin(line) {
            document.querySelectorAll('.cell').forEach(cell => {
                let index = cell.getAttribute('data-index');
                if (index == line[0] || index == line[1] || index == line[2]) {
                    cell.classList.add('win');
                }
            });
        }

        // 遊戲結束後禁止動作
        function disableBoard() {
            document.querySelectorAll('.cell').forEach(cell => {
                cell.classList.add('taken');
            });
        }

        // 監聽棋盤點擊事件
        document.querySelectorAll('.cell').forEach(cell => {
            cell.addEventListener('click', () => {

                // 格子已經使用
                if (cell.classList.contains('taken')) return;

                let index = cell.getAttribute('data-index');
                cell.textContent = symbol;
                cell.classList.add('taken');
                // 發送步數訊息到伺服器
                conn.send(JSON.stringify({
                    action: "move",
                    playerId: playerId,
                    roomId: roomId,
                    index: index,
                    symbol: symbol
                }));
                console.log("send move");
            });
        });
    </script>

</body>

</html>