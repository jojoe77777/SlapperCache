<?php

namespace SlapperCache;

use dktapps\SerializedNbtFixer\SerializedNbtFixer;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
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
        // Serialize and save slapper NBT and inventory data
        $invData = [];
        $objHash = substr(md5(spl_object_hash($entity->namedtag)), 0, 8);

        $serializedNBT = serialize($entity->namedtag);
        $fileName = $this->getDataFolder() . $this->SlapperCacheDir . DIRECTORY_SEPARATOR . $type . "." . $sender->getLevel()->getName() . "." .  $entity->getNameTag() . "." . $objHash . ".slp";

        if (file_put_contents($fileName, $serializedNBT)) {
            $this->getLogger()->debug("Wrote NBT serial file: $fileName");
        }

        // Save inventory data if human
        if ($entity instanceof SlapperHuman) {
            $humanArmour = $entity->getArmorInventory();
            $invData[] = $humanArmour->getHelmet();
            $invData[] = $humanArmour->getChestplate();
            $invData[] = $humanArmour->getLeggings();
            $invData[] = $humanArmour->getBoots();
            $invData[] = $sender->getInventory()->getHeldItemIndex();
            $invData[] = $sender->getInventory()->getItemInHand();
            $serializedInvData = serialize($invData);

            $fileName = $this->getDataFolder() . $this->SlapperCacheDir . DIRECTORY_SEPARATOR . $type . "." . $sender->getLevel()->getName() . "." . $entity->getNameTag() . "." . $objHash . ".slp.inv";
            if (file_put_contents($fileName, $serializedInvData)) {
                $this->getLogger()->debug("Wrote inventory serial file: $fileName");
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
                $this->getLogger()->debug(__FUNCTION__ . " Slapper $fileName null, possibly because world is not present, not spawning");
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
            $this->getLogger()->debug(__FUNCTION__ . " Could not open Slapper cache file: " . $file);
            return null;
        }

        $nbt = SerializedNbtFixer::fixSerializedCompoundTag(unserialize($data));

        $entity = Entity::createEntity($typeToUse, $level, $nbt);
        $entity->setNameTag(str_replace("Â", "", $fileParts[2]));
        $entity->setNameTagAlwaysVisible();
        $entity->setNameTagVisible();
        if ($entity instanceof SlapperHuman) {
            $file .= ".inv";
            $data = file_get_contents($file);
            $inventoryArray = unserialize($data);

            $humanInv = $entity->getInventory();
            $humanArmour = $entity->getArmorInventory();
            $humanArmour->setHelmet(self::fixSerializedItem($inventoryArray[0]));
            $humanArmour->setChestplate(self::fixSerializedItem($inventoryArray[1]));
            $humanArmour->setLeggings(self::fixSerializedItem($inventoryArray[2]));
            $humanArmour->setBoots(self::fixSerializedItem($inventoryArray[3]));
            $humanInv->setHeldItemIndex($inventoryArray[4], false);
            $humanInv->setItemInHand(self::fixSerializedItem($inventoryArray[5]));
        }

        if ($sender !== null) {
            $sender->sendMessage($this->prefix . $typeToUse . " entity spawned with name " . TextFormat::WHITE . "\"" . TextFormat::BLUE . $nbt->NameTag . TextFormat::WHITE . "\"");
        }

        return $entity;
    }

    /**
     * Takes an __PHP_Incomplete_Class and casts it to a stdClass object.
     * All properties will be made public in this step.
     *
     * @see https://stackoverflow.com/a/28353091
     *
     * @since  1.1.0
     * @param  object $object __PHP_Incomplete_Class
     * @return object
     */
    private static function fix_object( $object ) {
        // preg_replace_callback handler. Needed to calculate new key-length.
        $fix_key = function($matches){
            return ":" . strlen( $matches[1] ) . ":\"" . $matches[1] . "\"";
        };

        // 1. Serialize the object to a string.
        $dump = serialize( $object );

        // 2. Change class-type to 'stdClass'.
        $dump = preg_replace( '/^O:\d+:"[^"]++"/', 'O:8:"stdClass"', $dump );

        // 3. Make private and protected properties public.
        $dump = preg_replace_callback( '/:\d+:"\0.*?\0([^"]+)"/', $fix_key, $dump );

        // 4. Unserialize the modified object again.
        return unserialize($dump);
    }

    private static function fixSerializedItem(object $item) : Item{
        if($item instanceof \__PHP_Incomplete_Class){
            $stdclass = self::fix_object($item);

            return ItemFactory::get($stdclass->id, $stdclass->meta, $stdclass->count, $stdclass->tags);
        }elseif($item instanceof Item){
            return $item;
        }else{
            throw new \InvalidArgumentException("unexpected object of type " . get_class($item));
        }
    }

}
