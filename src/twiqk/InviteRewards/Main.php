<?php

declare(strict_types=1);

namespace twiqk\InviteRewards;

use EasyUI\element\Dropdown;
use EasyUI\element\Input;
use EasyUI\element\Label;
use EasyUI\element\Option;
use EasyUI\element\Toggle;
use EasyUI\utils\FormResponse;
use EasyUI\variant\CustomForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

use onebone\economyapi\EconomyAPI;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener {

    public function onEnable() {
        @mkdir($this->getDataFolder());
        $this->saveResource("settings.yml");
        $this->saveResource("data.yml");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onJoin(PlayerJoinEvent $event){
        $config = $this->myConfig = new Config($this->getDataFolder() . "settings.yml", Config::YAML);
        if ($event->getPlayer()->hasPlayedBefore() === false && $config->get("newPlayer") === true) {
            $this->claimForm($event->getPlayer());
        }
    }

    public function claimForm(Player $player) {
        $config = $this->myConfig = new Config($this->getDataFolder() . "settings.yml", Config::YAML);
        $form = new CustomForm($config->get("title"));
        $form->addElement("content", new Label($config->get("content")));
        $form->addElement("switch", new Toggle($config->get("switchLabel")));
        $dropdown = new Dropdown($config->get("inviteSelector"));
        foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
            $dropdown->addOption(new Option($onlinePlayer->getName(), $onlinePlayer->getName()));
        }
        $form->addElement("playerSelector", $dropdown);
        $form->addElement("playerName", new Input($config->get("inviteInput")));
        $form->setSubmitListener(function(Player $player, FormResponse $response) {
            $submittedText = $response->getInputSubmittedText("playerName");
            if ($response->getToggleSubmittedChoice("switch") === true) {
                if ($response->getInputSubmittedText("playerName") === $player->getName()) {
                    $player->sendMessage(TextFormat::RED . "You cannot reward yourself.");
                } else {
                    $this->rewardPlayer($player, $response->getInputSubmittedText("playerName"));
                }
            } else {
                if ($response->getDropdownSubmittedOptionId("playerSelector") === $player->getName()) {
                    $player->sendMessage(TextFormat::RED . "You cannot reward yourself.");
                } else {
                    $this->rewardPlayer($player, $response->getDropdownSubmittedOptionId("playerSelector"));
                }
            }
        });
        $player->sendForm($form);
    }

    public function rewardPlayer(Player $invited, $inviter) {
        $data = $this->myConfig = new Config($this->getDataFolder() . "data.yml", Config::YAML);
        $data->set($invited->getName(), "true");
        $data->save();
        $config = $this->myConfig = new Config($this->getDataFolder() . "settings.yml", Config::YAML);
        $inviterAsPlayer = $this->getServer()->getPlayer($inviter);
        if($inviterAsPlayer instanceof Player && $inviterAsPlayer->isOnline()) {
            $inviterAsPlayer->sendMessage(str_replace("{money}", $config->get("inviterReward"), $config->get("inviterRewardMessage")));
            EconomyAPI::getInstance()->addMoney($invited, $config->get("inviterReward"));
        }
        $invited->sendMessage(str_replace("{money}", $config->get("invitedReward"), $config->get("invitedRewardMessage")));
        EconomyAPI::getInstance()->addMoney($invited, $config->get("invitedReward"));
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        $data = $this->myConfig = new Config($this->getDataFolder() . "data.yml", Config::YAML);
        if (!$data->exists($sender->getName())) {
            if ($sender instanceof Player) {
                if ($command->getName() == "claim") {
                    $this->claimForm($sender);
                }
            }
            else {
                $sender->sendMessage("You must be in-game to run this command.");
            }
        } else {
            $sender->sendMessage(TextFormat::RED . "You have already claimed your reward. Invite others and tell them to enter your name to earn money through the invite system.");
        }
        return false;
    }

    public function onDisable(){
        $this->saveResource("data.yml");
    }
}
