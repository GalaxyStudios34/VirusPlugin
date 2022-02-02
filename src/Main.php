<?php

declare(strict_types=1);

namespace Cinnec\VirusEffect;


use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\item\ItemFactory;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {

    public $onlinePlayers = [];
    public function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getAllPlayers();
    }

    public function onJoin(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        $config = $this->getConfigs("infected.yml");
        if($config->get($player->getName()) == true){
            $this->setVirus($player);
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        $config = $this->getConfigs("infected.yml");
        $message = $this->getConfigs("message.yml");
        switch ($command){
            case "heal":
                if($sender->hasPermission("heal.perm")){
                    if(!isset($args[0])){
                        $sender->sendMessage($message->get("prefix") . $message->get("missingArgumentHeal"));
                    } elseif ($config->get($args[0]) == false){
                        $sender->sendMessage($message->get("prefix") . $message->get("playerIsNotInfected"));
                    } else {
                        $config->remove($args[0]);
                        $config->save();
                        $this->getServer()->broadcastMessage($message->get("prefix") . "§fPlayer " . $args[0] . " was cured of the virus.");
                        $player = $this->getServer()->getPlayerByPrefix($args[0]);
                        $player->getEffects()->clear();
                        $player->sendTitle("§bYou’ve been healed!");
                    }
                } else {
                    $sender->sendMessage($message->get("prefix") . $message->get("missingPermissionHeal"));
                }
                break;
            case "infect":
                if($sender->hasPermission("infect.perm")){
                    if(!isset($args[0])){
                        $sender->sendMessage($message->get("prefix") . $message->get("missingArgumentInfect"));
                        return true;
                    } elseif ($config->get($args[0]) == true) {
                        $sender->sendMessage($message->get("prefix") . $message->get("playerIsInfected"));
                        return true;
                    } elseif ($this->getServer()->getPlayerByPrefix($args[0]) == false){
                        $sender->sendMessage($message->get("prefix") . $message->get("missingArgumentInfect"));
                    } else {
                        $config->set($args[0], "infected");
                        $config->save();
                        $this->getServer()->broadcastMessage($message->get("prefix") . "§fPlayer " . $args[0] . " was infected by the virus.");
                        $pla = $this->getServer()->getPlayerByPrefix($args[0]);
                        $pla->sendTitle("§aYou’ve been infected!");
                        $this->setVirus($pla);
                    }
                } else {
                    $sender->sendMessage($message->get("prefix") . $message->get("missingPermissionInfect"));
                }
                break;

        }
        return true;
    }

    public function onDamage(EntityDamageEvent $event){
        $entity = $event->getEntity();
        if($entity instanceof Player){
            if($event instanceof EntityDamageByEntityEvent){
                $damager = $event->getDamager();
                if($this->getConfigs("infected.yml")->get($entity->getName()) == false){
                    $config = $this->getConfigs("infected.yml");
                    $config->set($entity->getName(), "infected");
                    $config->save();
                    $entity->sendMessage($this->getConfigs("message.yml")->get("prefix") . "§fYou got the virus from " . $damager->getNameTag());
                    $this->setVirus($entity);
                    $entity->sendTitle("§aINFECTED");
                }
            }
        }
    }

    public function onConsume(PlayerItemConsumeEvent $event){
        $item = $event->getItem()->getId();
        $itemHeal = ItemFactory::getInstance()->get(322, 1, 0);
        $player = $event->getPlayer();
        $config = $this->getConfigs("infected.yml");
        $message = $this->getConfigs("message.yml");
        if($item == $itemHeal->getId()){
            if($config->get($player->getName()) == true){
                $config->remove($player->getName());
                $config->save();
                $this->getServer()->broadcastMessage($message->get("prefix") . "§fPlayer " . $player->getName() . " was cured of the virus.");
                $player->getEffects()->clear();
                $player->sendTitle("§bYou’ve been healed!");
            }
        }
    }

    function onMove(PlayerMoveEvent $event){
        $player = $event->getPlayer();
        if($this->getConfigs("infected.yml")->get($player->getName()) == true){
            if($player->getWorld()->getTime() == 13000 or $player->getWorld()->getTime() == 23000){
                $this->spread($player);
                $this->getServer()->broadcastMessage($this->getConfigs("message.yml")->get("prefix") . "§fInfected players may now have distributed their spurs!");
            }
        }
    }

    public function setVirus(Player $player){
        $slowness = new EffectInstance(VanillaEffects::SLOWNESS(), 1000000, 1);
        $player->getEffects()->add($slowness);
        $blindness = new EffectInstance(VanillaEffects::BLINDNESS(), 200, 1);
        $player->getEffects()->add($blindness);
        $nausea = new EffectInstance(VanillaEffects::NAUSEA(), 250, 1);
        $player->getEffects()->add($nausea);
        $weakness = new EffectInstance(VanillaEffects::WEAKNESS(), 1000000, 1);
        $player->getEffects()->add($weakness);
    }

    public function getAllPlayers(){
        if(empty($this->onlinePlayers)){
            foreach ($this->getServer()->getOnlinePlayers() as $players){
                $this->onlinePlayers = $players;
            }
        }
    }

    public function getConfigs($file){
        $file = new Config($this->getDataFolder() . $file, CONFIG::YAML);
        return $file;
    }

    public function spread(Player $player){
        $config = $this->getConfigs("infected.yml");
        foreach ($this->getServer()->getOnlinePlayers() as $players){
            if($player->getDirectionVector()->distance(new Vector3($players->getDirectionVector()->getX(), $players->getDirectionVector()->getY(), $players->getDirectionVector()->getZ())) < 20){
                if($this->getConfigs("infected.yml")->get($players->getName()) == false){
                    $players->sendTitle("§aINFECTED");
                    $players->sendMessage($this->getConfigs("message.yml")->get("prefix") . "§fYou got infected by " . $player->getName() . " because his virus spread spores.");
                    $this->setVirus($players);
                    $config->set($players->getName(), "infected");
                    $config->save();
                    $chance = rand(0, 10);
                    if($chance == 1){
                        $player->kill();
                        $this->getServer()->broadcastMessage($player->getName() . " died of the virus");
                    }
                }
            }
        }
    }
}
