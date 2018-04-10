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

use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;

class CacheHandlerV2 implements CacheReader{

	public const DATA_DIR = "cache_v2";
	public const STATE_FILE = "slappers_restored_file";

	/** @var Main */
	private $plugin;

	public function __construct(Main $plugin){
		$this->plugin = $plugin;
	}

	public function storeSlapperNbt(string $nametag, string $type, string $levelName, CompoundTag $nbt) : void{
		$pos = $nbt->getListTag("Pos");
		$nbt->removeTag("SlapperData");
		assert($pos instanceof ListTag);

		$dir = $this->getDirectory();
		@mkdir($dir, 0777, true);

		$writer = new BigEndianNBTStream();
		$data = $writer->writeCompressed($nbt);

		$filename = "$type.$nametag.$levelName." . bin2hex(random_bytes(8)) . ".nbt"; //don't hash the data, or they might collide if they have the same NBT

		file_put_contents($dir . $filename, $data);
	}

	public function getDirectory() : string{
		return $this->plugin->getDataFolder() . "cache_v2" . DIRECTORY_SEPARATOR;
	}

	/**
	 * @return bool
	 */
	public function isValid() : bool{
		return is_dir($this->plugin->getDataFolder() . self::DATA_DIR);
	}

	/**
	 * @return bool
	 */
	public function needsRestore() : bool{
		$trigger_file = $this->getDirectory() . self::STATE_FILE;
		return !is_file($trigger_file);
	}

	public function setNeedsRestore(bool $flag) : void{
		$trigger_file = $this->getDirectory() . self::STATE_FILE;

		if(!$flag){
			touch($trigger_file);
		}else{
			unlink($trigger_file);
		}
	}

	public function nuke() : void{
		rename($this->getDirectory(), dirname($this->getDirectory()) . DIRECTORY_SEPARATOR . "cache_v2_nuked");
	}

	/**
	 * @return \Generator|CacheObject[]
	 */
	public function uncacheSlappers() : \Generator{
		$files = glob($this->getDirectory() . "*.nbt");

		$reader = new BigEndianNBTStream();
		foreach ($files as $file) {
			$fileName = basename($file, ".nbt");
			$this->plugin->getLogger()->debug(__FUNCTION__ . " Found Slapper in v2 format: $fileName");

			$data = file_get_contents($file);
			$nbt = $reader->readCompressed($data);
			assert($nbt instanceof CompoundTag);

			[$type, $name, $world, ] = explode(".", $fileName);

			yield new CacheObject($name, $type, $world, $nbt);
		}
	}
}
