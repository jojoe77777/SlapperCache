<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace SlapperCache;

use dktapps\SerializedNbtFixer\SerializedNbtFixer;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\plugin\PluginLogger;
use pocketmine\Server;

class CacheHandlerV1 implements CacheReader{

	/** @var Main */
	private $plugin;
	/** @var string */
	private $SlapperCacheDir = "cache";
	/** @var string */
	private $SlapperStateFile = "slappers_restored_file";

	public function __construct(Main $plugin){
		$this->plugin = $plugin;
	}

	private function getLogger() : PluginLogger{
		return $this->plugin->getLogger();
	}

	private function getDataFolder() : string{
		return $this->plugin->getDataFolder();
	}

	private function getServer() : Server{
		return $this->plugin->getServer();
	}

	private function convertNbt($file){
		$fileName = basename($file, ".slp");
		// like SlapperCreeper.world.Von.d603217a
		// or   SlapperHuman.world.Von.383d2bb4
		$fileParts = explode(".", $fileName);
		$typeToUse = $fileParts[0];
		$world = $fileParts[1];

		$this->getLogger()->debug(__FUNCTION__ . " Processing $fileName, type $typeToUse, world $world");

		if (!$data = file_get_contents($file)) {
			$this->getLogger()->debug(__FUNCTION__ . " Could not open Slapper cache file: " . $file);
			return null;
		}

		$nbt = SerializedNbtFixer::fixSerializedCompoundTag(unserialize($data));

		if(file_exists($file . ".inv")){
			$data = file_get_contents($file . ".inv");

			$inventoryArray = unserialize($data);

			$slapperTag = new CompoundTag("SlapperData");
			$slapperTag->setTag(new ListTag("Armor", [
				self::fixSerializedItem($inventoryArray[0])->nbtSerialize(0),
				self::fixSerializedItem($inventoryArray[1])->nbtSerialize(1),
				self::fixSerializedItem($inventoryArray[2])->nbtSerialize(2),
				self::fixSerializedItem($inventoryArray[3])->nbtSerialize(3)
			]));

			$slapperTag->setByte("HeldItemIndex", $inventoryArray[4]);
			$slapperTag->setTag(self::fixSerializedItem($inventoryArray[5])->nbtSerialize(-1, "HeldItem"));
			$nbt->setTag($slapperTag);
		}

		return $nbt;
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

	public function getDirectory() : string{
		return $this->getDataFolder() . "cache" . DIRECTORY_SEPARATOR;
	}

	/**
	 * @return bool
	 */
	public function isValid() : bool{
		return is_dir($this->getDirectory());
	}

	/**
	 * @return bool
	 */
	public function needsRestore() : bool{
		$trigger_file = $this->getDirectory() . $this->SlapperStateFile;
		return !is_file($trigger_file);
	}

	public function setNeedsRestore(bool $flag) : void{
		$trigger_file = $this->getDirectory() . $this->SlapperStateFile;

		if(!$flag){
			@touch($trigger_file);
		}else{
			@unlink($trigger_file);
		}
	}

	public function nuke() : void{
		rename($this->getDirectory(), dirname($this->getDirectory()) . DIRECTORY_SEPARATOR . "cache_v1_nuked");
	}

	/**
	 * @return \Generator|CacheObject[]
	 */
	public function uncacheSlappers() : \Generator{
		$files = glob($this->getDirectory() . "*.slp");
		foreach ($files as $file) {
			$fileName = basename($file, ".slp");
			[$typeToUse, $world, $name, ] = explode(".", $fileName);

			$this->getLogger()->debug(__FUNCTION__ . " Found cached Slapper in v1 format: $fileName");
			$nbt = $this->convertNbt($file);

			yield new CacheObject($name, $typeToUse, $world, $nbt);
		}
	}
}
