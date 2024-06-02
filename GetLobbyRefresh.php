<?php


include "CardDictionary.php";
include 'Libraries/HTTPLibraries.php';
include_once "Libraries/PlayerSettings.php";
include_once "Assets/patreon-php-master/src/PatreonDictionary.php";

//We should always have a player ID as a URL parameter
$gameName = $_GET["gameName"];
if (!IsGameNameValid($gameName)) {
  echo ("Invalid game name.");
  exit;
}
$playerID = TryGet("playerID", 3);
$lastUpdate = TryGet("lastUpdate", 0);
$authKey = TryGet("authKey", 0);

if(!file_exists("./Games/" . $gameName . "/")) { header('HTTP/1.0 403 Forbidden'); exit; }

if($lastUpdate == "NaN") $lastUpdate = 0;
if ($lastUpdate > 10000000) $lastUpdate = 0;


include "WriteLog.php";
include "HostFiles/Redirector.php";
include "Libraries/UILibraries2.php";
include "Libraries/SHMOPLibraries.php";

$currentTime = round(microtime(true) * 1000);
SetCachePiece($gameName, $playerID + 1, $currentTime);

$count = 0;
$cacheVal = GetCachePiece($gameName, 1);
if ($cacheVal > 10000000) {
  SetCachePiece($gameName, 1, 1);
  $lastUpdate = 0;
}
$kickPlayerTwo = false;
while ($lastUpdate != 0 && $cacheVal <= $lastUpdate) {
  usleep(100000); //100 milliseconds
  $currentTime = round(microtime(true) * 1000);
  $cacheVal = GetCachePiece($gameName, 1);
  SetCachePiece($gameName, $playerID + 1, $currentTime);
  ++$count;
  if ($count == 100) break;
  $otherP = ($playerID == 1 ? 2 : 1);
  $oppLastTime = GetCachePiece($gameName, $otherP + 1);
  $oppStatus = strval(GetCachePiece($gameName, $otherP + 3));

  if ($oppStatus != "-1" && $oppLastTime != "") {
    if (($currentTime - $oppLastTime) > 8000 && $oppStatus == "0") {
      WriteLog("Player $otherP has disconnected.");
      GamestateUpdated($gameName);
      SetCachePiece($gameName, $otherP + 3, "-1");
      if($otherP == 2) SetCachePiece($gameName, $otherP + 6, "");
      $kickPlayerTwo = true;
    }
  }
}

include "MenuFiles/ParseGamefile.php";
include "MenuFiles/WriteGamefile.php";

$targetAuth = ($playerID == 1 ? $p1Key : $p2Key);
if ($authKey != $targetAuth) {
  echo ("Invalid Auth Key");
  exit;
}

if ($kickPlayerTwo) {

  $numP2Disconnects = IncrementCachePiece($gameName, 11);
  if($numP2Disconnects >= 3)
  {
    WriteLog("This lobby is now hidden due to inactivity. Type in chat to unhide the lobby.");
  }
  if (file_exists("./Games/" . $gameName . "/p2Deck.txt")) unlink("./Games/" . $gameName . "/p2Deck.txt");
  if (file_exists("./Games/" . $gameName . "/p2DeckOrig.txt")) unlink("./Games/" . $gameName . "/p2DeckOrig.txt");
  $gameStatus = $MGS_Initial;
  SetCachePiece($gameName, 14, $gameStatus);
  $p2Data = [];
  WriteGameFile();
}

if ($lastUpdate != 0 && $cacheVal < $lastUpdate) {
  echo (GetCachePiece($gameName, 1) . "ENDTIMESTAMP");
  exit;
} else if ($gameStatus == $MGS_GameStarted) {
  echo ("1");
  exit;
} else {

  echo (GetCachePiece($gameName, 1) . "ENDTIMESTAMP");
  if ($gameStatus == $MGS_ChooseFirstPlayer) {
    if ($playerID == $firstPlayerChooser) {
      echo ("<div class='game-set-up'><h2> Set Up</h2><p>You won the initiative choice</p><input class='GameLobby_Button' type='button' name='action' value='Go First' onclick='SubmitFirstPlayer(1)' style='margin-left:15px; margin-right:20px; text-align:center;'>");
      echo ("<input class='GameLobby_Button' type='button' name='action' value='Go Second' onclick='SubmitFirstPlayer(2)' style='text-align:center;'>");
    } else {
      echo ("<div class='game-set-up'><h2> Set Up</h2><p>Waiting for other player to choose who goes first</p><input type='button' value='-' style='visibility: hidden;'>");
      
    }
  }

  if ($playerID == 1 && $gameStatus < $MGS_Player2Joined) {
    if($visibility == "private")
    {
      if($p1id == "")
      {
        echo("<div>&#10071;This is a private lobby. You need to log in for matchmaking.</div><br>");
      }
      else {
        echo("<div>&#10071;This is a private lobby. You will need to invite an opponent.</div><br>");
      }
    }
    echo ("<div class='game-set-up'><h2> Set Up</h2><p>Waiting for another player to join</p>
    <input class='GameLobby_Input invite-link' onclick='copyText()' type='text' id='gameLink' value='" . $redirectPath . "/JoinGame.php?gameName=$gameName&playerID=2'><button class='GameLobby_Button' style='margin-left:3px;' onclick='copyText()'>Copy Invite Link</button>");
  }

    echo ("</div>");

  $isMobile = IsMobile();
  // Chat Log
  echo ("<div class='chat-log'>");
  if($isMobile) echo ("<h3>Chat</h3><div id='gamelog'>");
  else echo ("<h2>Chat</h2><div id='gamelog'>");
  //if(!IsMobile()) echo("<BR>");
  //echo ("<div id='gamelog' style='text-align:left; position:relative; text-shadow: 2px 0 0 #1a1a1a, 0 -2px 0 #1a1a1a, 0 2px 0 #1a1a1a, -2px 0 0 #1a1a1a; color: #EDEDED; background-color: rgba(20,20,20,0.8); margin-top:6px; height:63%; left:3%; width:94%; bottom:10%; font-weight:550; overflow-y: auto;'>");
  EchoLog($gameName, $playerID);
  echo ("</div>");

  echo ("<div id='playAudio' style='display:none;'>" . ($playerID == 1 && $gameStatus == $MGS_ChooseFirstPlayer ? 1 : 0) . "</div>");

  $otherHero = "CardBack";
  $otherBase = "CardBack";
  $otherPlayer = $playerID == 1 ? 2 : 1;
  $deckFile = "./Games/" . $gameName . "/p" . $otherPlayer . "Deck.txt";
  if (file_exists($deckFile)) {
    $handler = fopen($deckFile, "r");
    $otherCharacter = GetArray($handler);
    $otherHero = $otherCharacter[1];
    $otherBase = $otherCharacter[0];
    fclose($handler);
  }

  $theirName = ($playerID == 1 ? $p2uid : $p1uid);
  echo ("<div id='otherHero' style='display:none;'>");
  $contentCreator = ContentCreators::tryFrom(($playerID == 1 ? $p2ContentCreatorID : $p1ContentCreatorID));
  $nameColor = ($contentCreator != null ? $contentCreator->NameColor() : "");
  $theirDisplayName = "<span style='color:" . $nameColor . "'>" . ($theirName != "-" ? $theirName : "Player " . ($playerID == 1 ? 2 : 1)) . "</span>";
  if($isMobile) echo ("<h3>$theirDisplayName</h3>");
  else echo ("<h2>$theirDisplayName</h2>");
  $overlayURL = ($contentCreator != null ? $contentCreator->HeroOverlayURL($otherHero) : "");
  echo (Card($otherHero, "CardImages", ($isMobile ? 100 : 250), 0, 1, 0, 0, 0, "", "", true));
  echo (Card($otherBase, "CardImages", ($isMobile ? 100 : 250), 0, 1, 0, 0, 0, "", "", true));
  $channelLink = ($contentCreator != null ? $contentCreator->ChannelLink() : "");
  if($channelLink != "") echo("<a href='" . $channelLink . "' target='_blank'>");
  if($overlayURL != "") echo ("<img title='Portrait' style='position:absolute; z-index:1001; top: 87px; left: 18px; cursor:pointer; height:" . ($isMobile ? 100 : 250) . "; width:" . ($isMobile ? 100 : 250) . ";' src='" . $overlayURL . "' />");
  if($channelLink != "") echo("</a>");
  echo ("</div>");

  $needToSideboard = $gameStatus >= $MGS_P2Sideboard && ($playerID == 1 ? $p1SideboardSubmitted != "1" : $p2SideboardSubmitted != "1");
  echo ("<div id='submitDisplay' style='display:none;'>" . ($needToSideboard ? "block" : "none") . "</div>");

  $icon = "ready.png";
  if ($gameStatus == $MGS_ChooseFirstPlayer) $icon = $playerID == $firstPlayerChooser ? "ready.png" : "notReady.png";
  else if ($playerID == 1 && $gameStatus < $MGS_ReadyToStart) $icon = "notReady.png";
  else if ($playerID == 2 && $gameStatus >= $MGS_ReadyToStart) $icon = "notReady.png";
  echo ("<div id='iconHolder' style='display:none;'>" . $icon . "</div>");

  echo ("<div id='chatbox'>");
  //echo ("<div id='chatbox' style='position:relative; left:3%; width:97%; margin-top:4px;'>");
  echo ("<input class='GameLobby_Input' style='display:inline;' type='text' id='chatText' name='chatText' value='' autocomplete='off' onkeypress='ChatKey(event)'>");
  echo ("<button class='GameLobby_Button' style='display:inline; margin-left:3px; cursor:pointer;' onclick='SubmitChat()'>Chat</button>");
  echo ("<input type='hidden' id='gameName' value='" . $gameName . "'>");
  echo ("<input type='hidden' id='playerID' value='" . $playerID . "'>");
  echo ("</div>");

  echo ("</div>");
}
