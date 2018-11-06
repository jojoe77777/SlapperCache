<?php

namespace SlapperCache;

use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use slapper\entities\SlapperHuman;
use slapper\events\SlapperCreationEvent;

class Main extends PluginBase implements Listener {

    public $prefix = (TextFormat::GREEN . "[" . TextFormat::YELLOW . "SlapperCache" . TextFormat::GREEN . "] ");

    /** @var CacheHandlerV2 */
	private $cacheHandler;

    public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->cacheHandler = new CacheHandlerV2($this);

        $legacyCacheHandler = new CacheHandlerV1($this);
        if($legacyCacheHandler->isValid()){
        	foreach($legacyCacheHandler->uncacheSlappers() as $cacheObject){
        		$this->cacheHandler->storeSlapperNbt($cacheObject->name, $cacheObject->type, $cacheObject->level, $cacheObject->compoundTag);
			}

			$this->cacheHandler->setNeedsRestore($legacyCacheHandler->needsRestore());
        	$legacyCacheHandler->nuke();
        	$this->getLogger()->debug("successfully upgraded Slapper storage to v2");
		}

        $this->checkForSlapperRestore();
    }

    public function onSlapperCreation(SlapperCreationEvent $ev){
        if($ev->getCause() === SlapperCreationEvent::CAUSE_COMMAND){
        	$entity = $ev->getEntity();
			$entity->saveNBT();
			$this->cacheHandler->storeSlapperNbt($entity->getNameTag(), $entity->getSaveId(), $entity->getLevel()->getName(), $entity->namedtag);
        }
    }

    public function checkForSlapperRestore() {
    	if($this->cacheHandler->needsRestore()){
			$this->getLogger()->info("Restoring Slappers from Cache");
			$this->uncacheSlappers();
			$this->cacheHandler->setNeedsRestore(false);
		}else{
			$this->getLogger()->info("Slappers OK - No need to restore");
		}
    }

    private function uncacheSlappers() : void{
    	foreach($this->cacheHandler->uncacheSlappers() as $cacheObject){
			$level = $this->getServer()->getLevelByName($cacheObject->level);
			if ($level === null) {
				$this->getLogger()->error(__FUNCTION__ . ": failed to restore $cacheObject->name, type $cacheObject->type, world $cacheObject->level because world is not loaded");
				continue;
			}

			$this->getLogger()->debug(__FUNCTION__ . " Processing $cacheObject->name, type $cacheObject->type, world $cacheObject->level");
            $nbt = $cacheObject->compoundTag;
            if(!$nbt->hasTag("Motion", ListTag::class)){
                $motion = new ListTag("Motion", [
                    new DoubleTag("", 0.0),
                    new DoubleTag("", 0.0),
                    new DoubleTag("", 0.0)
                ]);
                $nbt->setTag($motion);
            }
			$entity = Entity::createEntity($cacheObject->type, $level, $nbt);

			$entity->setNameTag(str_replace("Â", "", $cacheObject->name));
			$entity->setNameTagAlwaysVisible();
			$entity->setNameTagVisible();
			if ($entity instanceof SlapperHuman) {
				$slapperInv = $cacheObject->compoundTag->getCompoundTag("SlapperData");
				if($slapperInv !== null){
					if($slapperInv->hasTag("Armor", ListTag::class)){
						$humanArmour = $entity->getArmorInventory();
						/** @var CompoundTag $itemTag */
						foreach($slapperInv->getListTag("Armor") ?? [] as $itemTag){
							$humanArmour->setItem($itemTag->getByte("Slot"), Item::nbtDeserialize($itemTag));
						}
					}

					if($slapperInv->hasTag("HeldItemIndex", ByteTag::class)){
						$entity->getInventory()->setHeldItemIndex($slapperInv->getByte("HeldItemIndex"));
					}
					if($slapperInv->hasTag("HeldItem", CompoundTag::class)){
						$entity->getInventory()->setItemInHand(Item::nbtDeserialize($slapperInv->getCompoundTag("HeldItem")));
					}
				}
			}
		}
	}



}
