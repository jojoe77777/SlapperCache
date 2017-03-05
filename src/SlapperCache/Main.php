<?php

namespace SlapperCache;

use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use slapper\entities\SlapperHuman;
use slapper\events\SlapperCreationEvent;

class Main extends PluginBase implements Listener {

    private $SlapperCacheDir = "cache";
    private $SlapperStateFile = "slappers_restored_file";
    public $prefix = (TextFormat::GREEN . "[" . TextFormat::YELLOW . "SlapperCache" . TextFormat::GREEN . "] ");


    public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->initDataDirs();
        $this->checkForSlapperRestore();
    }

    public function onSlapperCreation(SlapperCreationEvent $ev){
        if($ev->getCause() === SlapperCreationEvent::CAUSE_COMMAND){
            $this->cacheSlapper($ev->getCreator(), $ev->getType(), $ev->getEntity());
        }
    }

    public function initDataDirs() {
        if (!is_dir($this->getDataFolder()))
            mkdir($this->getDataFolder());
        if (!is_dir($this->getDataFolder() . $this->SlapperCacheDir))
            mkdir($this->getDataFolder() . $this->SlapperCacheDir);
    }

    public function checkForSlapperRestore($sender = null) {

        $trigger_file = $this->getDataFolder() . $this->SlapperCacheDir . DIRECTORY_SEPARATOR . $this->SlapperStateFile;

        if (!is_file($trigger_file)) {
            if ($sender !== null) {
                $sender->sendMessage($this->prefix . "Restoring Slappers from Cache.");
            }
            $this->getLogger()->info("Restoring Slappers from Cache");
            $this->uncacheSlappers($sender);
            touch($trigger_file);
        } else {
            if ($sender != null) {
                $sender->sendMessage($this->prefix . "] No action taken - delete $this->SlapperStateFile to refresh slappers.");
            }
            $this->getLogger()->info("Slappers OK - No need to restore");
        }

    }

    public function cacheSlapper(Player $sender, $type, Entity $entity) {

        //serialize and save slapper NBT and inventory data

        $invData = Array();
        $objHash = substr(md5(spl_object_hash($entity->namedtag)), 0, 8);

        $serializedNBT = serialize($entity->namedtag);
        $fileName = $this->getDataFolder() . $this->SlapperCacheDir . DIRECTORY_SEPARATOR . $type . "." . $sender->getLevel()->getName() . "." .  $entity->getNameTag() . "." . $objHash . ".slp";

        if (file_put_contents($fileName, $serializedNBT)) {
            $this->getLogger()->debug("Wrote NBT Serial File: $fileName");
        }

        //save inventory data if human
        if ($entity instanceof SlapperHuman) {

            $humanInv = $entity->getInventory();
            $invData[] = $humanInv->getHelmet();
            $invData[] = $humanInv->getChestplate();
            $invData[] = $humanInv->getLeggings();
            $invData[] = $humanInv->getBoots();
            $invData[] = $sender->getInventory()->getHeldItemSlot();
            $invData[] = $sender->getInventory()->getItemInHand();
            $serializedInvData = serialize($invData);

            $fileName = $this->getDataFolder() . $this->SlapperCacheDir . DIRECTORY_SEPARATOR . $type . "." . $sender->getLevel()->getName() . "." . $entity->getNameTag() . "." . $objHash . ".slp.inv";
            if (file_put_contents($fileName, $serializedInvData)) {
                $this->getLogger()->debug("Wrote Inventory Serial File: $fileName");
            }
        }
    }

    public function uncacheSlappers($sender = null) {

        $this->getLogger()->debug(__FUNCTION__);

        $files = glob($this->getDataFolder() . $this->SlapperCacheDir . DIRECTORY_SEPARATOR . "*.slp");
        foreach ($files as $file) {
            $fileName = basename($file, ".slp");
            $this->getLogger()->debug(__FUNCTION__ . " Found Slapper: $fileName");
            $entity = $this->uncacheSlapper($sender, $file);
            if ($entity != null) {
                $entity->spawnToAll();
            } else {
                $this->getLogger()->debug(__FUNCTION__ . " Slapper $fileName Null, possibly because world is not present, not spawning");
            }

        }
    }

    public function uncacheSlapper($sender, $file) {

        $fileName = basename($file, ".slp");
        // like SlapperCreeper.world.Von.d603217a
        // or   SlapperHuman.world.Von.383d2bb4
        $fileParts = explode(".", $fileName);
        $typeToUse = $fileParts[0];
        $world = $fileParts[1];

        $level = $this->getServer()->getLevelByName($world);
        if ($level == null) {
            return null;
        }

        $this->getLogger()->debug(__FUNCTION__ . " Processing $fileName, type $typeToUse, world $world");

        if (!$data = file_get_contents($file)) {
            $this->getLogger()->debug(__FUNCTION__ . " Could not open slapper cache file: " . $file);
            return null;
        }

        $nbt = unserialize($data);

        $playerX = $nbt->Pos[0];
        $playerZ = $nbt->Pos[2];

        $entity = Entity::createEntity($typeToUse, $level, $nbt);
        $entity->setNameTag(str_replace("Â", "", $fileParts[2]));
        $entity->setNameTagAlwaysVisible();
        $entity->setNameTagVisible();
        if ($entity instanceof SlapperHuman) {

            $file .= ".inv";
            $this->getLogger()->debug(__FUNCTION__ . " Open Slapper Inventory File " . $file);

            $data = file_get_contents($file);
            $inventoryArray = unserialize($data);

            $humanInv = $entity->getInventory();
            $humanInv->setHelmet($inventoryArray[0]);
            $humanInv->setChestplate($inventoryArray[1]);
            $humanInv->setLeggings($inventoryArray[2]);
            $humanInv->setBoots($inventoryArray[3]);
            $entity->getInventory()->setHeldItemIndex($inventoryArray[4], false);
            $entity->getInventory()->setItemInHand($inventoryArray[5]);
        }

        if ($sender !== null) {
            $sender->sendMessage($this->prefix . $typeToUse . " entity spawned with name " . TextFormat::WHITE . "\"" . TextFormat::BLUE . $nbt->NameTag . TextFormat::WHITE . "\"");
        }

        return $entity;
    }

}