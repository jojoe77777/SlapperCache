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

use pocketmine\nbt\tag\CompoundTag;

class CacheObject{

	/** @var string */
	public $name;
	/** @var string */
	public $type;
	/** @var string */
	public $level;
	/** @var CompoundTag */
	public $compoundTag;

	public function __construct(string $name, string $type, string $level, CompoundTag $tag){
		$this->name = $name;
		$this->type = $type;
		$this->level = $level;
		$this->compoundTag = $tag;
	}
}
