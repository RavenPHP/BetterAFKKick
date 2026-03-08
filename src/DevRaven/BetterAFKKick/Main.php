<?php

namespace DevRaven\BetterAFKKick;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\scheduler\ClosureTask;
use pocketmine\network\mcpe\protocol\TransferPacket;

class Main extends PluginBase implements Listener{

    private Config $config;
    private array $lastActivity = [];
    private array $afkPlayers = [];

    public function onEnable(): void{

        @mkdir($this->getDataFolder());
        $this->saveResource("config.yml");

        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function(): void{
                $this->checkAFK();
            }),
            20
        );
    }

    public function onMove(PlayerMoveEvent $event): void{

        $player = $event->getPlayer();

        if($player->hasPermission("betterafkkick.bypass")){
            return;
        }

        $from = $event->getFrom();
        $to = $event->getTo();

        if($to === null){
            return;
        }

        // Ignore movement inside the same block
        if($from->getFloorX() === $to->getFloorX() &&
           $from->getFloorY() === $to->getFloorY() &&
           $from->getFloorZ() === $to->getFloorZ()){
            return;
        }

        $threshold = $this->config->get("movement-threshold");

        // Optimized distance check (no sqrt)
        if($from->distanceSquared($to) > ($threshold * $threshold)){
            $this->lastActivity[$player->getName()] = time();
            unset($this->afkPlayers[$player->getName()]);
        }
    }

    private function checkAFK(): void{

        $afkTime = $this->config->get("afk-time");
        $countdown = $this->config->get("countdown");

        foreach(Server::getInstance()->getOnlinePlayers() as $player){

            if($player->hasPermission("betterafkkick.bypass")){
                continue;
            }

            $name = $player->getName();

            if(!isset($this->lastActivity[$name])){
                $this->lastActivity[$name] = time();
            }

            $idle = time() - $this->lastActivity[$name];

            if($idle >= $afkTime && !isset($this->afkPlayers[$name])){
                $this->afkPlayers[$name] = true;
                $this->startCountdown($player, $countdown);
            }
        }
    }

    private function startCountdown(Player $player, int $seconds): void{

        $actionbar = $this->config->getNested("messages.actionbar");

        $task = null;

        $task = new ClosureTask(function() use ($player, &$seconds, $actionbar, &$task): void{

            if(!$player->isOnline()){
                $task->cancel();
                return;
            }

            if(!isset($this->afkPlayers[$player->getName()])){
                $task->cancel();
                return;
            }

            if($seconds <= 0){
                $task->cancel();
                $this->executeAction($player);
                return;
            }

            $msg = str_replace("{seconds}", (string)$seconds, $actionbar);
            $player->sendActionBarMessage($msg);

            $seconds--;
        });

        $this->getScheduler()->scheduleRepeatingTask($task, 20);
    }

    private function executeAction(Player $player): void{

        $mode = $this->config->get("mode");

        if($mode === "kick"){
            $player->kick($this->config->getNested("kick.message"));
            return;
        }

        if($mode === "teleport"){

            $world = $this->config->getNested("teleport.world");
            $w = Server::getInstance()->getWorldManager()->getWorldByName($world);

            if($w !== null){
                $player->teleport($w->getSpawnLocation());
            }

            return;
        }

        if($mode === "transfer"){

            $servers = $this->config->getNested("transfer.servers");

            if(empty($servers)){
                return;
            }

            $server = $servers[array_rand($servers)];
            [$ip, $port] = explode(":", $server);

            $pk = new TransferPacket();
            $pk->address = $ip;
            $pk->port = (int)$port;

            $player->getNetworkSession()->sendDataPacket($pk);
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{

        if(!$sender instanceof Player){
            return true;
        }

        if($command->getName() === "afk"){

            if(isset($args[0]) && $args[0] === "list"){

                if(empty($this->afkPlayers)){
                    $sender->sendMessage($this->config->getNested("messages.no-afk"));
                    return true;
                }

                $sender->sendMessage($this->config->getNested("messages.afk-list-header"));

                foreach(array_keys($this->afkPlayers) as $player){
                    $sender->sendMessage("§7- " . $player);
                }

                return true;
            }

            $name = $sender->getName();

            if(isset($this->afkPlayers[$name])){
                unset($this->afkPlayers[$name]);
                $sender->sendMessage($this->config->getNested("messages.afk-off"));
            }else{
                $this->afkPlayers[$name] = true;
                $sender->sendMessage($this->config->getNested("messages.afk-on"));
                $this->executeAction($sender);
            }

            return true;
        }

        return false;
    }
}
