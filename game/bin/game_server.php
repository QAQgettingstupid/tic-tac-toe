<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class TicTacToeServer implements MessageComponentInterface
{

    protected $clients; //所有在線玩家set
    protected $players; //玩家資訊
    protected $Game_Manage; //玩家資訊




    public function __construct($Game_Manage)
    {
        $this->clients = new \SplObjectStorage; //SplObjectStorage -> Object set
        $this->players = [];
        $this->Game_Manage = $Game_Manage; //遊戲本體
    }


    // 為每個連線分配一個唯一 ID
    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn); // 加入新玩家

        //從 $conn 取 resourceId 當 key (resourceId 是 ratchet 設定 $conn 的屬性)
        $this->players[$conn->resourceId] = [
            'playerID' => null,
            'playerName' => null,
            'connection' => $conn,
            'game' => null // 尚未加入遊戲
        ];

        // 生成玩家 ID
        $conn->send(json_encode([
            'action' => 'requestPlayerId'
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        switch ($data['action']) {

            // 玩家回覆生成 ID
            case 'replyPlayerId':
                $this->players[$from->resourceId]['playerID'] = $data['playerId'];
                $this->players[$from->resourceId]['playerName'] = $data['playerName'];
                $this->players[$from->resourceId]['game'] = $data['game'];
                $this->broadcastPlayerList();
                break;

            // 玩家發起挑戰
            case 'challenge':
                $this->openChallenge($from, $data['targetId']);
                break;

            // 玩家接受挑戰
            case 'challenge_confirm':

                $player1Id = $this->players[$from->resourceId]['playerID'];
                $player2Id = $data['otherplayer'];

                //開房
                $roomId = $this->Game_Manage->createRoom($player1Id, $player2Id);


                // 切到遊戲頁面
                foreach ($this->players as $player) { // 找發起玩家
                    // 發起玩家
                    if ($player['playerID'] == $data['otherplayer']) {
                        $player['game'] = $roomId; // 設定遊戲房間 ID
                        $player['connection']->send(json_encode(['action' => 'change_page', 'game' => $roomId, 'first' => false]));
                    }
                }

                // 被發起玩家
                $this->players[$from->resourceId]['game'] = $roomId; // 設定遊戲房間 ID
                $this->players[$from->resourceId]['connection']->send(json_encode(['action' => 'change_page', 'game' => $roomId, 'first' => true]));
                break;

            //未搞
            case 'challenge_not_confirm':
                // 處理玩家對挑戰的回應
                $response = [
                    'action' => $data['action'],
                    'fromId' => $from->resourceId,
                    'otherplayer' => $data['otherplayer']
                ];
                // 廣播給所有玩家
                foreach ($this->players as $player) {
                    $player['connection']->send(json_encode($response));
                }
                break;

            // 更新盤面(包含起始狀態)
            case 'updateBoard':
                $roomId = $data['game']; // 確定是哪個遊戲房間
                $room = $this->Game_Manage->getRoom($roomId);

                // 僅提取必要資料
                $response = [
                    'action' => 'updateBoard',
                    'board' => $room['board'], // 棋盤狀態
                    'round' => $room['round']  // 當前回合數
                ];
                // 回傳盤面
                $from->send(json_encode($response));
                break;

            case 'move':
                $this->handleMove($from, $data['index'], $data['symbol'], $data['roomId'], $data['playerId']);
                break;

            default:
                echo "Unknown action: " . $data['action'] . "\n";
                break;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        unset($this->players[$conn->resourceId]);
        $this->clients->detach($conn);
        $this->broadcastPlayerList();
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->close();
    }

    private function broadcastPlayerList()
    {
        // 取得所有玩家的 resourceId 作為玩家 ID
        $playerList = [];

        foreach ($this->players as $player) {
            if ($player['game'] === null) {
                $playerList[] = [
                    'id' => $player['playerID'],
                    'name' => $player['playerName']
                ];
            }
        }

        // 廣播玩家列表
        foreach ($this->players as $player) {
            if ($player['game'] === null) {
                $player['connection']->send(json_encode([
                    'action' => 'playerList',
                    'players' => $playerList
                ]));
            }
        }
    }

    // $from 是發起挑戰的玩家$conn，$targetId 是被挑戰的玩家 ID
    private function openChallenge($from, $targetId)
    {
        $fromPlayerId = $this->players[$from->resourceId]['playerID'] ?? null;
        if ($fromPlayerId === $targetId) {
            // 不允許挑戰自己，可以選擇回傳錯誤訊息或直接忽略
            $from->send(json_encode([
                'action' => 'error',
                'message' => '你不能挑戰自己！'
            ]));
            return;
        }
        foreach ($this->players as $player) {
            if ($player['playerID'] == $targetId) {
                $player['connection']->send(json_encode(['action' => 'challenge', 'from' => $this->players[$from->resourceId]['playerName'], 'fromid' => $this->players[$from->resourceId]['playerID']]));
                break;
            }
        }
    }

    // 理論上要回覆發出挑戰的玩家,但沒做QAQ
    private function replyChallenge($response)
    {
        // 快寫啊
    }

    private function handleMove($from, $index, $symbol, $roomId, $playerId)
    {
        $room = $this->Game_Manage->getRoom($roomId);

        // 更新棋盤
        $room['board'][$index] = $symbol;
        $room['round']++;

        // 勝利線組合
        $winPatterns = [
            [0, 1, 2],
            [3, 4, 5],
            [6, 7, 8], // 橫
            [0, 3, 6],
            [1, 4, 7],
            [2, 5, 8], // 直
            [0, 4, 8],
            [2, 4, 6]          // 斜
        ];

        $winner = null;
        $winner_X = null;
        $winLine = null;
        foreach ($winPatterns as $line) {
            list($a, $b, $c) = $line;
            if (
                $room['board'][$a] &&
                $room['board'][$a] === $room['board'][$b] &&
                $room['board'][$a] === $room['board'][$c]
            ) {
                $winner=true; // 有人勝利
                // X 勝利
                if ($room['board'][$a] == 'X')
                    $winner_X = true;
                // O 勝利
                else
                    $winner_X = false;
                $winLine = $line;
                break;
            }
        }

        $isDraw = false;
        if (!$winner && !in_array(null, $room['board'])) {
            $isDraw = true;
        }

        // 通知雙方更新棋盤
        foreach ($this->players as $player) {
            if ($player['playerID'] == $room['player1'] || $player['playerID'] == $room['player2']) {
                $player['connection']->send(json_encode([
                    'action' => 'updateBoard',
                    'board' => $room['board'],
                    'round' => $room['round']
                ]));
            }
        }

        if ($winner) {
            foreach ($this->players as $player) {
                if ($player['playerID'] == $room['player1'] || $player['playerID'] == $room['player2']) {
                    $player['connection']->send(json_encode([
                        'action' => 'gameOver',
                        'result' => 'win',
                        'winner' => $winner_X,
                        'line' => $winLine
                    ]));
                }
            }
        } elseif ($isDraw) {
            foreach ($this->players as $player) {
                if ($player['playerID'] == $room['player1'] || $player['playerID'] == $room['player2']) {
                    $player['connection']->send(json_encode([
                        'action' => 'gameOver',
                        'result' => 'draw'
                    ]));
                }
            }
        }

        $this->Game_Manage->setRoom($room, $roomId);
    }
}

class TicTacToeGame
{
    protected $rooms; // 遊戲房間集合

    public function __construct()
    {
        // 初始化遊戲房間
        $this->rooms = [];
    }

    public function getRoom($roomId)
    {
        return $this->rooms[$roomId] ?? null;
    }

    public function setRoom($room, $roomId)
    {
        $this->rooms[$roomId] = $room;
    }

    public function createRoom($player1Id, $player2Id)
    {
        // 為玩家創建一個新的遊戲房間
        $roomId = uniqid();
        $this->rooms[$roomId] = [
            'player1' => $player1Id,
            'player2' => $player2Id,

            'board' => array_fill(0, 9, null), // 初始化棋盤
            'round' => 1
        ];
        return $roomId;
    }
};

$Game_Manage = new TicTacToeGame();

// 啟動伺服器
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new TicTacToeServer($Game_Manage)
        )
    ),
    8080
);

echo "game server started on port 8080\n";

$server->run();
