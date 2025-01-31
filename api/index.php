<?php
  require_once '../class/config.php';
  require_once '../class/database.php';
  require_once '../steamauth/steamauth.php';
  
  define("USER_AGENT", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36");

  $db = new DataBase();

  $_POST = json_decode(file_get_contents("php://input"), true);

  if (!isset($_GET["action"])) exit;

  switch($_GET["action"]) {
    case "check":
      $steamid = "";
      $avatar = "";
      $personname = "";
      $selectedSkins = [
        't' => [],
        'ct' => []
      ];
      $selectedKnife = [
        't' => 'weapon_knife_t',
        'ct' => 'weapon_knife'
      ];
      $selectedGlove = [
        't' => -1,
        'ct' => -1
      ];
      $selectedMusic = [
        't' => -1,
        'ct' => -1
      ];
      $selectedPin = [
        't' => -1,
        'ct' => -1
      ];
      
      if (isset($_SESSION["steamid"])) {
        require_once "../steamauth/userInfo.php";
        $steamid = $steamprofile['steamid'];
        $avatar = $steamprofile['avatarmedium'];
        $personname = $steamprofile['personaname'];
        
        // 获取皮肤数据时加入team区分
        $querySelected = $db->select("SELECT * FROM `wp_player_skins` WHERE `wp_player_skins`.`steamid` = :steamid", ["steamid" => $steamid]);
        foreach ($querySelected as $weapon) {
          $team = $weapon['weapon_team'] == 2 ? 't' : 'ct';  // 2是T阵营，3是CT阵营
          $selectedSkins[$team][$weapon['weapon_defindex']] = [
            'weapon_paint_id' => $weapon['weapon_paint_id'],
            'weapon_seed' => $weapon['weapon_seed'],
            'weapon_wear' => $weapon['weapon_wear'],
            'weapon_nametag' => $weapon['weapon_nametag'],
            'weapon_stattrak' => $weapon['weapon_stattrak'],
            'weapon_sticker_0' => $weapon['weapon_sticker_0'],
            'weapon_sticker_1' => $weapon['weapon_sticker_1'],
            'weapon_sticker_2' => $weapon['weapon_sticker_2'],
            'weapon_sticker_3' => $weapon['weapon_sticker_3'],
            'weapon_keychain' => $weapon['weapon_keychain'],
          ];
        }
    
        // 获取刀具数据时加入team区分
        $selectedKnifeQuery = $db->select("SELECT * FROM `wp_player_knife` WHERE `wp_player_knife`.`steamid` = :steamid", ["steamid" => $steamid]);
        foreach ($selectedKnifeQuery as $knife) {
          $team = $knife['weapon_team'] == 2 ? 't' : 'ct';
          $selectedKnife[$team] = $knife['knife'];
        }
    
        // 获取手套数据时加入team区分
        $selectedGloveQuery = $db->select("SELECT * FROM `wp_player_gloves` WHERE `wp_player_gloves`.`steamid` = :steamid", ["steamid" => $steamid]);
        foreach ($selectedGloveQuery as $glove) {
          $team = $glove['weapon_team'] == 2 ? 't' : 'ct';
          $selectedGlovePaint = $db->select("SELECT weapon_paint_id FROM `wp_player_skins`
                                        WHERE
                                          `wp_player_skins`.`steamid` = :steamid AND
                                          weapon_defindex = :defIndex AND
                                          weapon_team = :team",
                                          [
                                            "steamid" => $steamid, 
                                            "defIndex" => $glove['weapon_defindex'],
                                            "team" => $glove['weapon_team']
                                          ]
                                      );
          if (isset($selectedGlovePaint) && count($selectedGlovePaint) > 0) {
            $selectedGlove[$team] = $selectedGlovePaint[0]["weapon_paint_id"];
          }
        }
    
        // 获取音乐盒数据时加入team区分
        $selectedMusicQuery = $db->select("SELECT * FROM `wp_player_music` WHERE `wp_player_music`.`steamid` = :steamid", ["steamid" => $steamid]);
        foreach ($selectedMusicQuery as $music) {
          $team = $music['weapon_team'] == 2 ? 't' : 'ct';
          $selectedMusic[$team] = $music['music_id'];
        }
    
        // 获取徽章数据时加入team区分
        $selectedPinQuery = $db->select("SELECT * FROM `wp_player_pins` WHERE `wp_player_pins`.`steamid` = :steamid", ["steamid" => $steamid]);
        foreach ($selectedPinQuery as $pin) {
          $team = $pin['weapon_team'] == 2 ? 't' : 'ct';
          $selectedPin[$team] = $pin['id'];
        }
    
        // 获取角色数据
        $selectedAgent = $db->select("SELECT * FROM `wp_player_agents` WHERE `wp_player_agents`.`steamid` = :steamid", ["steamid" => $steamid]);
      }
      
      echo json_encode(array(
        "steamid" => $steamid,
        "steam_avatar" => $avatar,
        "steam_personaname" => $personname,
        "selected_skins" => $selectedSkins,
        "selected_knife" => $selectedKnife,
        "selected_glove" => $selectedGlove,
        "selected_music" => $selectedMusic,
        "selected_pin" => $selectedPin,
        "selected_agents" => array("t" => $selectedAgent[0]["agent_t"] ?? "", "ct" => $selectedAgent[0]["agent_ct"] ?? ""),
      ));
      break;
    
    case "set-music":
      if (!isset($_SESSION["steamid"]))   exit;
      if (!isset($_POST["music_id"]))     exit;
      if (!isset($_POST["team"]))         exit;
    
      $weapon_team = $_POST["team"] == "terrorists" ? 2 : 3;  // 转换为数字队伍ID

      if ($_POST["music_id"] == "-1") {
        $db->query("DELETE FROM `wp_player_music` WHERE steamid = :steamid AND weapon_team = :weapon_team", ["steamid" => $_SESSION["steamid"], "weapon_team" => $weapon_team]);
      } else {
        $db->query("INSERT INTO `wp_player_music` VALUES(:steamid, :weapon_team, :music_id) ON DUPLICATE KEY UPDATE `music_id` = :music_id", ["steamid" => $_SESSION["steamid"], "weapon_team" => $weapon_team, "music_id" => $_POST["music_id"]]);
      }
      break;

    case "set-pin":
      if (!isset($_SESSION["steamid"]))   exit;
      if (!isset($_POST["pin_id"]))       exit;
      if (!isset($_POST["team"]))         exit;
    
      $weapon_team = $_POST["team"] == "terrorists" ? 2 : 3;  // 转换为数字队伍ID

      if ($_POST["pin_id"] == "-1") {
        $db->query("DELETE FROM `wp_player_pins` WHERE steamid = :steamid AND weapon_team = :weapon_team", ["steamid" => $_SESSION["steamid"], "weapon_team" => $weapon_team]);
      } else {
        $db->query("INSERT INTO `wp_player_pins` VALUES(:steamid, :weapon_team, :pin_id) ON DUPLICATE KEY UPDATE `id` = :pin_id", ["steamid" => $_SESSION["steamid"], "weapon_team" => $weapon_team, "pin_id" => $_POST["pin_id"]]);
      }
      break;

    case "set-knife":
      if (!isset($_SESSION["steamid"]))   exit;
      if (!isset($_POST["knife"]))        exit;
      if (!isset($_POST["team"]))         exit;

      $weapon_team = $_POST["team"] == "terrorists" ? 2 : 3;  // 转换为数字队伍ID

      $db->query("INSERT INTO `wp_player_knife` VALUES(:steamid, :weapon_team, :knife) ON DUPLICATE KEY UPDATE `knife` = :knife", ["steamid" => $_SESSION["steamid"], "weapon_team" => $weapon_team, "knife" => $_POST["knife"]]);
      break;

    case "set-agent":
      if (!isset($_SESSION["steamid"]))   exit;
      if (!isset($_POST["team"]))         exit;
      if (!isset($_POST["model"]))        exit;

      if ($_POST["model"] == "null")      $_POST["model"] = null;

      if ($_POST["team"] == "terrorists") {
        $db->query("INSERT INTO `wp_player_agents` (`steamid`, `agent_ct`, `agent_t`) VALUES(:steamid, NULL, :model) ON DUPLICATE KEY UPDATE `agent_t` = :model", ["steamid" => $_SESSION["steamid"], "model" => $_POST["model"]]);
      } else if ($_POST["team"] == "counter-terrorists") {
        $db->query("INSERT INTO `wp_player_agents` (`steamid`, `agent_ct`, `agent_t`) VALUES(:steamid, :model, NULL) ON DUPLICATE KEY UPDATE `agent_ct` = :model", ["steamid" => $_SESSION["steamid"], "model" => $_POST["model"]]);
      }
      break;

    case "set-glove":
      if (!isset($_SESSION["steamid"]))   exit;
      if (!isset($_POST["paint"]))        exit;
      if (!isset($_POST["team"]))         exit;
      if (!isset($_POST["defIndex"]))     exit;

      $weapon_team = $_POST["team"] == "terrorists" ? 2 : 3;  // 转换为数字队伍ID

      if ($_POST["paint"] == "-1") {
        $db->query("DELETE FROM `wp_player_skins` WHERE 
                    steamid = :steamid AND 
                    weapon_team = :weapon_team AND 
                    weapon_defindex = :defIndex",
                    [
                      "steamid" => $_SESSION["steamid"], 
                      "weapon_team" => $weapon_team,
                      "defIndex" => $_POST["defIndex"]
                    ]);
        $db->query("DELETE FROM `wp_player_gloves` WHERE 
                    steamid = :steamid AND 
                    weapon_team = :weapon_team", 
                    [
                      "steamid" => $_SESSION["steamid"],
                      "weapon_team" => $weapon_team
                    ]);
      } else {
        $db->query("INSERT INTO `wp_player_gloves` 
                    (steamid, weapon_team, weapon_defindex) 
                    VALUES (:steamid, :weapon_team, :defIndex) 
                    ON DUPLICATE KEY UPDATE 
                    weapon_defindex = :defIndex", 
                    [
                      "steamid" => $_SESSION["steamid"], 
                      "weapon_team" => $weapon_team,
                      "defIndex" => $_POST["defIndex"]
                    ]);
        
        $rows = $db->query("UPDATE `wp_player_skins` 
                            SET weapon_paint_id = :paint 
                            WHERE steamid = :steamid AND 
                            weapon_defindex = :defIndex AND 
                            weapon_team = :weapon_team",
                            [
                              "steamid" => $_SESSION["steamid"], 
                              "defIndex" => $_POST["defIndex"], 
                              "paint" => $_POST["paint"],
                              "weapon_team" => $weapon_team
                            ]);
        
        if ($rows == 0) {
          $db->query("INSERT INTO `wp_player_skins` 
                      (steamid, weapon_team, weapon_defindex, weapon_paint_id, weapon_wear, weapon_seed) 
                      VALUES (:steamid, :weapon_team, :defIndex, :paint, 0.001, 0)",
                      [
                        "steamid" => $_SESSION["steamid"], 
                        "weapon_team" => $weapon_team,
                        "defIndex" => $_POST["defIndex"], 
                        "paint" => $_POST["paint"]
                      ]);
        }
      }
      break;

    case "set-skin":
      if (!isset($_SESSION["steamid"]))   exit;
      if (!isset($_POST["defIndex"]))     exit;
      if (!isset($_POST["paint"]))        exit;
      if (!isset($_POST["wear"]))         exit;
      if (!isset($_POST["seed"]))         exit;
      if (!isset($_POST["nametag"]))      exit;
      if (!isset($_POST["stattrack"]))    exit;
      if (!isset($_POST["sticker0"]))     exit;
      if (!isset($_POST["sticker1"]))     exit;
      if (!isset($_POST["sticker2"]))     exit;
      if (!isset($_POST["sticker3"]))     exit;
      if (!isset($_POST["keychain"]))     exit;
      if (!isset($_POST["team"]))         exit;

      if ($_POST["nametag"] == "")  $_POST["nametag"] = null;

      $weapon_team = $_POST["team"] == "terrorists" ? 2 : 3;  // 转换为数字队伍ID

      // 先删除可能存在的记录
      $db->query("DELETE FROM `wp_player_skins` 
      WHERE steamid = :steamid 
      AND weapon_defindex = :defIndex 
      AND weapon_team = :weapon_team",
      [
        "steamid" => $_SESSION["steamid"],
        "defIndex" => $_POST["defIndex"],
        "weapon_team" => $weapon_team
      ]);

      // 然后插入新记录
      $db->query("INSERT INTO `wp_player_skins` 
      (steamid, weapon_team, weapon_defindex, weapon_paint_id, weapon_wear, 
      weapon_seed, weapon_nametag, weapon_stattrak, weapon_stattrak_count,
      weapon_sticker_0, weapon_sticker_1, weapon_sticker_2, weapon_sticker_3, 
      weapon_sticker_4, weapon_keychain)
      VALUES (
      :steamid, :weapon_team, :defIndex, :paint, :wear, :seed, :nametag, 
      :stattrack, 0, :sticker0, :sticker1, :sticker2, :sticker3, 
      '0;0;0;0;0;0;0', :keychain
      )",
      [
        "steamid" => $_SESSION["steamid"],
        "weapon_team" => $weapon_team,
        "defIndex" => $_POST["defIndex"],
        "paint" => $_POST["paint"],
        "wear" => $_POST["wear"],
        "seed" => $_POST["seed"],
        "nametag" => $_POST["nametag"],
        "stattrack" => $_POST["stattrack"],
        "sticker0" => $_POST["sticker0"],
        "sticker1" => $_POST["sticker1"],
        "sticker2" => $_POST["sticker2"],
        "sticker3" => $_POST["sticker3"],
        "keychain" => $_POST["keychain"]
      ]);

      break;
    
    case "get-skins":
      $lang = "en";
      if (isset($_GET["lang"])) $lang = $_GET["lang"];
      $url = curl_init();
      curl_setopt($url , CURLOPT_URL , "https://bymykel.github.io/CSGO-API/api/".$lang."/skins.json");
      curl_setopt($url, CURLOPT_USERAGENT, USER_AGENT);
      $result = curl_exec($url);
      curl_close($url);
      header('Content-Type: application/json');
      break;

    case "get-musics":
      $lang = "en";
      if (isset($_GET["lang"])) $lang = $_GET["lang"];
      $url = curl_init();
      curl_setopt($url , CURLOPT_URL , "https://bymykel.github.io/CSGO-API/api/".$lang."/music_kits.json");
      curl_setopt($url, CURLOPT_USERAGENT, USER_AGENT);
      $result = curl_exec($url);
      curl_close($url);
      header('Content-Type: application/json');
      break;

    case "get-agents":
      $lang = "en";
      if (isset($_GET["lang"])) $lang = $_GET["lang"];
      $url = curl_init();
      curl_setopt($url , CURLOPT_URL , "https://bymykel.github.io/CSGO-API/api/".$lang."/agents.json");
      curl_setopt($url, CURLOPT_USERAGENT, USER_AGENT);
      $result = curl_exec($url);
      curl_close($url);
      header('Content-Type: application/json');
      break;

    case "get-stickers":
      $lang = "en";
      if (isset($_GET["lang"])) $lang = $_GET["lang"];
      $url = curl_init();
      curl_setopt($url , CURLOPT_URL , "https://bymykel.github.io/CSGO-API/api/".$lang."/stickers.json");
      curl_setopt($url, CURLOPT_USERAGENT, USER_AGENT);
      $result = curl_exec($url);
      curl_close($url);
      header('Content-Type: application/json');
      break;

    case "get-keychains":
      $lang = "en";
      if (isset($_GET["lang"])) $lang = $_GET["lang"];
      $url = curl_init();
      curl_setopt($url , CURLOPT_URL , "https://bymykel.github.io/CSGO-API/api/".$lang."/keychains.json");
      curl_setopt($url, CURLOPT_USERAGENT, USER_AGENT);
      $result = curl_exec($url);
      curl_close($url);
      header('Content-Type: application/json');
      break;

    case "get-pins":
      $lang = "en";
      if (isset($_GET["lang"])) $lang = $_GET["lang"];
      $url = curl_init();
      curl_setopt($url , CURLOPT_URL , "https://bymykel.github.io/CSGO-API/api/".$lang."/collectibles.json");
      curl_setopt($url, CURLOPT_USERAGENT, USER_AGENT);
      $result = curl_exec($url);
      curl_close($url);
      header('Content-Type: application/json');
      break;
  }
?>