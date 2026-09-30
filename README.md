 # 連線版圈圈叉叉

以 PHP 與 WebSocket（Ratchet）實作的即時連線對戰遊戲（2 人合作，本人負責連線邏輯）。
玩家可在大廳看到在線玩家、發起挑戰，並進入獨立房間對戰。

## 設計重點
- 跨頁面身分綁定：前端產生獨立 ID 存於 sessionStorage，重新連線時由 server 重新綁定
- 房間管理：每場對戰建立獨立房間，訊息只推送給房內兩位玩家

### 開啟教學
1. 先灌composer
2. 把這個上層資料夾circle下的檔案全下載放到xampp的htdocs
3. 在bin資料夾開cmd打**composer require cboden/ratchet**
4. 找到game_server.php (在circle/game/bin下) 並在此處開cmd 打php game_server.php, 然後不要關這cmd
5. 開xampp, 用xampp開entername.php(entername是入口、skin是大廳、try是圈圈叉叉進行的頁面)

#### 題外話

-  skin.php的 let conn = new WebSocket('ws://localhost:8080'); 的localhost改伺服器IP同內網可互聯
-  在skin.php所在處cmd打指令**php -S 0.0.0.0:8000**(網址:http://localhost:8000/skin.php)

************************************************

### 未來可做

-  遊戲結束後處置(再玩一局或是離開)
-  提示框拒絕的選項還沒做(凡是yes no 二選一)
-  遊戲下棋倒數計時(30秒倒數之類的)
