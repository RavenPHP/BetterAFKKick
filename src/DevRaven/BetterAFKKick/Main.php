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
use pocketmine\scheduler\CancelTaskException;
use pocketmine\network\mcpe\protocol\TransferPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;

class Main extends PluginBase implements Listener{

    private Config $config;
    private array $lastActivity = [];
    private array $afkPlayers = [];

    public function onEnable(): void{

        @mkdir($this->getDataFolder());
        $this->saveResource("config.yml");

        $this->config = new Config(
            $this->getDataFolder() . "config.yml",
            Config::YAML
        );

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

        if(
            $from->getFloorX() === $to->getFloorX() &&
            $from->getFloorY() === $to->getFloorY() &&
            $from->getFloorZ() === $to->getFloorZ()
        ){
            return;
        }

        $threshold = (float)$this->config->get("movement-threshold");

        if($from->distanceSquared($to) > ($threshold * $threshold)){
            $name = $player->getName();
            $this->lastActivity[$name] = time();
            unset($this->afkPlayers[$name]);
        }
    }

    private function checkAFK(): void{

        $afkTime = (int)$this->config->get("afk-time");
        $countdown = (int)$this->config->get("countdown");

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

        $title = $this->config->getNested("title.title");
        $subtitle = $this->config->getNested("title.subtitle");
        $sound = $this->config->getNested("title.sound");

        $actionbar = $this->config->getNested("messages.actionbar");

        $player->sendTitle($title, $subtitle, 10, 40, 10);

        $this->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function() use ($player, &$seconds, $actionbar, $sound): void{

                if(
                    !$player->isOnline() ||
                    !isset($this->afkPlayers[$player->getName()])
                ){
                    throw new CancelTaskException();
                }

                if($seconds <= 0){
                    $this->executeAction($player);
                    throw new CancelTaskException();
                }

                $msg = str_replace("{seconds}", (string)$seconds, $actionbar);
                $player->sendActionBarMessage($msg);

                $pk = new PlaySoundPacket();
                $pk->soundName = $sound;
                $pk->x = $player->getLocation()->getX();
                $pk->y = $player->getLocation()->getY();
                $pk->z = $player->getLocation()->getZ();
                $pk->volume = 1;
                $pk->pitch = 1;

                $player->getNetworkSession()->sendDataPacket($pk);

                $seconds--;

            }),
            20
        );
    }

    private function executeAction(Player $player): void{

        $mode = $this->config->get("mode");

        if($mode === "kick"){
            $player->kick($this->config->getNested("kick.message"));
            return;
        }

        if($mode === "teleport"){

            $worldName = $this->config->getNested("teleport.world");
            $world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);

            if($world !== null){
                $player->teleport($world->getSpawnLocation());
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

            $player->getNetworkSession()->sendDataPacket(
                TransferPacket::create($ip, (int)$port)
            );
        }
    }

    public function onCommand(
        CommandSender $sender,
        Command $command,
        string $label,
        array $args
    ): bool{

        if(!$sender instanceof Player){
            return true;
        }

        if($command->getName() === "afk"){

            if(isset($args[0]) && $args[0] === "list"){

                if(!$sender->hasPermission("betterafkkick.afklist")){
                    $sender->sendMessage("§cNo permission.");
                    return true;
                }

                $this->openAFKGUI($sender);
                return true;
            }

            $name = $sender->getName();

            if(isset($this->afkPlayers[$name])){
                unset($this->afkPlayers[$name]);
                $sender->sendMessage(
                    $this->config->getNested("messages.afk-off")
                );
            }else{
                $this->afkPlayers[$name] = true;
                $sender->sendMessage(
                    $this->config->getNested("messages.afk-on")
                );
            }

            return true;
        }

        return false;
    }

    private function openAFKGUI(Player $player): void{

        $form = [
            "type" => "form",
            "title" => "AFK Players",
            "content" => "List of AFK players",
            "buttons" => []
        ];

        foreach(array_keys($this->afkPlayers) as $name){
            $form["buttons"][] = [
                "text" => $name,
                "image" => [
                    "type" => "path",
                    "data" => "textures/items/skull_skeleton"
                ]
            ];
        }

        if(empty($form["buttons"])){
            $form["content"] = "No AFK players.";
        }

        $player->sendForm($form);
    }
}




