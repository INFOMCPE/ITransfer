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
use pocketmine\utils\Utils; 
use pocketmine\Server;
use pocketmine\item\Item;
use pocketmine\scheduler\PluginTask;

class ITransfer extends PluginBase implements Listener {
     const Prfix = '§f[§aITransfer§f]§e ';
     
   public function onEnable(){
        $this->saveDefaultConfig();
        @mkdir($this->getDataFolder().'signs');
               if(!file_exists($this->getDataFolder().'lang.json')){
                   $this->languageInitialization();
               }
            $this->getServer()->getScheduler()->scheduleRepeatingTask(new Timer($this), 20 * 120);  
            $this->session = $this->getServer()->getPluginManager()->getPlugin("SessionAPI");
            if ($this->getServer()->getPluginManager()->getPlugin("PluginDownloader")) {
            $this->getServer()->getScheduler()->scheduleAsyncTask(new CheckVersionTask($this, 332));
            
            if($this->session == NULL){
               if($this->getServer()->getPluginManager()->getPlugin("PluginDownloader")->getDescription()->getVersion() >= '1.4'){
                   $this->getServer()->getPluginManager()->getPlugin("PluginDownloader")->installByID('SessionAPI');
               }
            }
            $this->getServer()->getPluginManager()->registerEvents($this, $this);
            }
   }
   public function onDisable() {
       unlink($this->getDataFolder().'lang.json');
   }
       public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
                    case 'transfer':
                        if($args[0] == 'help' && $args[1] == NULL){
                            $sender->sendMessage(ITransfer::Prfix.$this->lang('main'));
                        }else if($args[0] == NULL){
                            $sender->sendMessage(ITransfer::Prfix.$this->lang('no_ip'));
                        }
                        if($args[0] != null && $args[0] != 'addsign' ){
                            $this->transfer($args[0], $sender );
                        }
                        if($args[0] == 'addsign'){
                            $this->session->createSession(strtolower($sender->getName()), 'stat',0);
                            $sender->sendMessage(ITransfer::Prfix.$this->lang("press_send_1"));
                        }
                    break;
                }
       }
        public function onChat(PlayerChatEvent $event) {
            $player = $event->getPlayer();
            $message = $event->getMessage();
            if($this->session->getSessionData(strtolower($player->getName()), 'stat') == 0){
                
                $this->session->createSession(strtolower($player->getName()), 'stat', 1);
                $this->session->createSession(strtolower($player->getName()), 'ip', $message);
                $player->sendMessage(ITransfer::Prfix.$this->lang("press_tap"));
                
                }
                $event->setCancelled(); 
            
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
             if($this->query($ip, "hostname") != false)
        $sign->setText($this->getConfig()->get("SignText"), $this->query($ip, 'hostname'), '§a'.$this->query($ip, 'numplayers').'§f/§e'.$this->query($ip, 'maxplayers'));
             
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
           $data = json_decode(Utils::getURL("https://status.infomcpe.ru/query.php?ip={$ip}&port={$port}"), ture);
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
       	public function dataSave($id, $tip, $data){
  $Sfile = (new Config($this->getDataFolder() . "signs/".strtolower($id).".json", Config::JSON))->getAll();
  $Sfile[$tip] = $data;
  $Ffile = new Config($this->getDataFolder() . "signs/".strtolower($id).".json", Config::JSON);
  $Ffile->setAll($Sfile);
  $Ffile->save();
}
	 public function dataGet($id, $tip){
   $Sfile = (new Config($this->getDataFolder() . "signs/".strtolower($id).".json", Config::JSON))->getAll();
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
