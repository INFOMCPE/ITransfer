<?php

namespace infomcpe;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\utils\Utils; 
use pocketmine\Server;
use pocketmine\item\Item;
use pocketmine\scheduler\PluginTask;
use pocketmine\tile\Sign;

class ITransfer extends PluginBase implements Listener {
     const Prfix = '§f[§aITransfer§f]§e ';

    private $last_x;

    public function onEnable(){
        $this->saveDefaultConfig();
        @mkdir($this->getDataFolder().'signs');
        @mkdir($this->getDataFolder().'portals');
               if(!file_exists($this->getDataFolder().'lang.json')){
                   $this->languageInitialization();
               }
               
            $this->getServer()->getScheduler()->scheduleRepeatingTask(new Timer($this), 20 * $this->getConfig()->get("interval"));  
            $this->session = $this->getServer()->getPluginManager()->getPlugin("SessionAPI");
            if ($this->getServer()->getPluginManager()->getPlugin("PluginDownloader")) {
            $this->getServer()->getScheduler()->scheduleAsyncTask(new CheckVersionTask($this, 332));
            
            if($this->session == NULL){
               if($this->getServer()->getPluginManager()->getPlugin("PluginDownloader")->getDescription()->getVersion() >= '1.4'){
                   $this->getServer()->getPluginManager()->getPlugin("PluginDownloader")->installByID('SessionAPI');
               }
            }
            }
	     $this->getServer()->getPluginManager()->registerEvents($this, $this);
   }
   public function onDisable() {
       unlink($this->getDataFolder().'lang.json');
   }
       public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
                    case 'transfer':
                        switch ($args[0]) {
                            case 'help':
                $sender->sendMessage($this->lang('main'));
                                break;
                            case 'connect':
                                      $server = explode(":", $args[1]);
                                      $ip = $server[0];
                                      $port = $server[1];
                                      if($ip != null){
                                        if($port != null){
                                           $this->transfer($args[1], $sender );
                                        }else{
                                             $sender->sendMessage(ITransfer::Prfix.$this->lang('no_port'));
                                        }
                                      }else{
                                          $sender->sendMessage(ITransfer::Prfix.$this->lang('no_ip'));
                                      }
                                break;
                            case 'addsign':
                            $this->session->createSession(strtolower($sender->getName()), 'stat',3);
                            $sender->sendMessage(ITransfer::Prfix.$this->lang("press_send_1"));
                                break;
                             case 'addportal':
                            $this->session->createSession(strtolower($sender->getName()), 'stat', 5);
                            $sender->sendMessage(ITransfer::Prfix."Пожалуйста нажмите на первую точку портала");
                                break;
                            case 'update':
                                $this->signTask();
                                break;
                          
                        }
                          if($args[0] == null && $args[1] == null){
                                $sender->sendMessage($this->lang('main'));
                            }
                }
       }
        public function onChat(PlayerChatEvent $event) {
            $player = $event->getPlayer();
            $message = $event->getMessage();
            if($this->session->getSessionData(strtolower($player->getName()), 'stat') == 3){
                $this->session->createSession(strtolower($player->getName()), 'stat', 1);
                $this->session->createSession(strtolower($player->getName()), 'ip', $message);
                $player->sendMessage(ITransfer::Prfix.$this->lang("press_tap"));
                $event->setCancelled(); 
                }
                if($this->session->getSessionData(strtolower($player->getName()), 'stat') == 7){
                    $this->dataSave($message, 'min', $this->session->getSessionData(strtolower($player->getName()), 'min'), 'portals');
                    $this->dataSave($message, 'max', $this->session->getSessionData(strtolower($player->getName()), 'max'), 'portals');
                    $this->dataSave($message, 'ip', $message, 'portals');
                    $player->sendMessage(ITransfer::Prfix.'Успешно. Портал добавлен');
                            
                }
                
            
        }
        public function onMove(\pocketmine\event\server\DataPacketReceiveEvent $event){
            $player = $event->getPlayer();
            $pk = $event->getPacket();
            if($pk instanceof \pocketmine\network\protocol\MovePlayerPacket  ){
                if($pk->mode == 0){  
            if ($this->last_x != round($player->getX())){
                $this->last_x = round($player->getX());
              foreach (glob($this->getDataFolder().'portals/*.json') as $filename) {
                $data = json_decode(file_get_contents($filename), TRUE);
                if($this->cheakXYZ($player->getX(), $player->getY(), $player->getZ(), $data['min'], $data['max']) == true){
                    $this->transfer($data['ip'], $player);
                    
                }else{
                 
                }
            }
                }
                }
        }
        }
         public function onBlockBreak(BlockBreakEvent $event){
         $player = $event->getPlayer();
         $block = $event->getBlock();
             if($this->dataGet($block->getX().":".$block->getY().":".$block->getZ(), 'ip') != NULL){
                 unlink($this->getDataFolder().'signs/'.$block->getX().":".$block->getY().":".$block->getZ());
             }
         }
       public function onPlayerTouch(PlayerInteractEvent $event){
         $player = $event->getPlayer();
         $block = $event->getBlock();
         $sign = $event->getPlayer()->getLevel()->getTile($event->getBlock());
        
         if($this->session->getSessionData(strtolower($player->getName()), 'stat') == 1){
              if($this->dataGet($block->getX().":".$block->getY().":".$block->getZ(), 'ip') != NULL){
             $ip = $this->dataGet($block->getX().":".$block->getY().":".$block->getZ(), 'ip');
         }
             if($event->getBlock()->getId() == 68 || $event->getBlock()->getId() == 63){
             $this->dataSave($block->getX().":".$block->getY().":".$block->getZ(), 'ip', $this->session->getSessionData(strtolower($player->getName()), 'ip'));
             $sign->setText($this->getConfig()->get("SignText"), $this->query($ip, 'hostname'), '§a'.$this->query($ip, 'numplayers').'§f/§e'.$this->query($ip, 'maxplayers'));
             $this->session->deleteSession($player->getName());
             $player->sendMessage(ITransfer::Prfix.$this->lang("success_sign"));
             } else {
                 $player->sendMessage(ITransfer::Prfix.$this->lang("no_sign"));
             }
         }
             if($event->getBlock()->getId() == 68 || $event->getBlock()->getId() == 63){
	           if($this->session->getSessionData(strtolower($player->getName()), 'stat') != 1){
                   $signtext = $sign->getText();
                        if($signtext[0] == $this->getConfig()->get("SignText")  ){
                            if($this->dataGet($block->getX().":".$block->getY().":".$block->getZ(), 'ip') != NULL ){
                                $this->transfer($this->dataGet($block->getX().":".$block->getY().":".$block->getZ(), 'ip'), $player);
                                 $sign->setText($this->getConfig()->get("SignText"), $this->query($this->dataGet($block->getX().":".$block->getY().":".$block->getZ(), 'ip'), 'hostname'), '§a'.$this->query($this->dataGet($block->getX().":".$block->getY().":".$block->getZ(), 'ip'), 'numplayers').'§f/§e'.$this->query($this->dataGet($block->getX().":".$block->getY().":".$block->getZ(), 'ip'), 'maxplayers'));
             
                            }
                            }
                   }
             }
             if($this->session->getSessionData(strtolower($player->getName()), 'stat') == 5){
                 $this->session->createSession(strtolower($player->getName()), 'min', $block->getX().":".$block->getY().":".$block->getZ());
                 $this->session->createSession(strtolower($player->getName()), 'stat', 6);
                 $player->sendMessage(ITransfer::Prfix.'Успешно. Теперь отмете вторую точку');
             } else if($this->session->getSessionData(strtolower($player->getName()), 'stat') == 6){
                $this->session->createSession(strtolower($player->getName()), 'max', $block->getX().":".$block->getY().":".$block->getZ());
                $player->sendMessage(ITransfer::Prfix.'Успешно. Теперь напишите IP адрес. Порт после ":" Пример: 0.0.0.0:19132');
                $this->session->createSession(strtolower($player->getName()), 'stat', 7);
             }
         
       }
       public function cheakXYZ($x, $y, $z, $min, $max) {
        /* @var $pos1 array */
        $pos1 = explode(':', $min);
        /* @var $pos2 array */
        $pos2 = explode(':', $max);
          //Топ код АЙДАНА --Начало--
           $minX = min($pos1[0], $pos2[0]);//0 - x; 1 - y; 2 - z
           $maxX = max($pos1[0], $pos2[0]); 
            $minY = min($pos1[1], $pos2[1]);
            $maxY = max($pos1[1], $pos2[1]);
            $minZ = min($pos1[2], $pos2[2]);
            $maxZ = max($pos1[2], $pos2[2]);
            if ($minX <= $x and $x <= $maxX) {
        if ($minY <= $y and $y <= $maxY) {
        if ($minZ <= $z and $z <= $maxZ) {
            return true;
} else {
return false;
}
} else {
return false;
}
} else {
return false;
}
//Топ код АЙДАНА --Конец--
       }
       public function signTask() {

        foreach ($this->getServer()->getLevels() as $levels) {
            foreach ($levels->getTiles() as $tile) {
                if ($tile instanceof Sign) {
                    $block = $tile->getBlock();
                    if ($tile->getText()[0] == $this->getConfig()->get("SignText") ){
                        if($this->dataGet($block->getX().":".$block->getY().":".$block->getZ(), 'ip') != NULL){
             $ip = $this->dataGet($block->getX().":".$block->getY().":".$block->getZ(), 'ip');
         }
         if($ip != NULL){
             if($this->query($ip, "hostname") != false){
                   $tile->setText($this->getConfig()->get("SignText"), $this->query($ip, 'hostname'), '§a'.$this->query($ip, 'numplayers').'§f/§e'.$this->query($ip, 'maxplayers'));
                            
             }
       
         }
                    }
                }
            }
        }
       }
       public function query($addres, $id) {
           $server = explode(":", $addres);
           $ip = $server[0];
           $port = $server[1];
           $data = json_decode(Utils::getURL("https://status.infomcpe.ru/query.php?ip={$ip}&port={$port}"), true);
           if($data['error'] == NULL){
               return $data[$id];
           } elseif ($data['error'] != NULL) {
               return FALSE;
           }
           
       }
       
       public function transfer($addres, $player) {
           $server = explode(":", $addres);
           $ip = $server[0];
           $port = $server[1];
//           $this->getLogger()->info($ip);
//           $this->getLogger()->info($port);
           $pk = new \pocketmine\network\protocol\TransferPacket();
           $pk->port = $port;
           $pk->address = $ip;
           $player->dataPacket($pk);
       }
       	public function dataSave($id, $tip, $data, $type = 'signs'){
  $Sfile = (new Config($this->getDataFolder() . $type."/".strtolower($id).".json", Config::JSON))->getAll();
  $Sfile[$tip] = $data;
  $Ffile = new Config($this->getDataFolder() . $type."/".strtolower($id).".json", Config::JSON);
  $Ffile->setAll($Sfile);
  $Ffile->save();
}
	 public function dataGet($id, $tip ,$type = 'signs'){
   $Sfile = (new Config($this->getDataFolder() . $type."/".strtolower($id).".json", Config::JSON))->getAll();
   return $Sfile[$tip];
         }
      public function languageInitialization(){
             switch ($this->getConfig()->get("lang")) {
                 case 'rus':
                    $this->saveResource('rus.json');
                     if(file_exists($this->getDataFolder().'lang.json')){
                         unlink($this->getDataFolder().'lang.json');
                     }
                     rename($this->getDataFolder().'rus.json', $this->getDataFolder().'lang.json');

                     break;
                     case 'eng':
                    $this->saveResource('eng.json');
                     if(file_exists($this->getDataFolder().'lang.json')){
                         unlink($this->getDataFolder().'lang.json');
                     }
                     rename($this->getDataFolder().'eng.json', $this->getDataFolder().'lang.json');

                     break;

                 default:
                     $this->saveResource('rus.json');
                     if(file_exists($this->getDataFolder().'lang.json')){
                         unlink($this->getDataFolder().'lang.json');
                     }
                     rename($this->getDataFolder().'rus.json', $this->getDataFolder().'lang.json');
                     break;
             }
         }

         public function lang($phrase){
        $file = json_decode(file_get_contents($this->getDataFolder()."lang.json"), TRUE);
        return $file["{$phrase}"];
		}
    public function curl_get_contents($url){
  $curl = curl_init($url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
  curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
  $data = curl_exec($curl);
  curl_close($curl);
  return $data;
          }
          
}
class Timer extends PluginTask{
	
	public function __construct(ITransfer $main){
            $this->plugin = $main;
		parent::__construct($main);
	}
	
	public function onRun($tick){
            $this->plugin->signTask();
	}
	
}
