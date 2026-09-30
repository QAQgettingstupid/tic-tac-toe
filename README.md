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

### 以下還沒做QAQ

-  遊戲結束後處置(再玩一局或是離開)
-  提示框拒絕的選項還沒做(凡是yes no 二選一)
-  遊戲下棋倒數計時(30秒倒數之類的)
