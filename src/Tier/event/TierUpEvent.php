<?php
declare(strict_types=1);

namespace Tier\event;

use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

class TierUpEvent extends PlayerEvent{

	protected string $before;

	protected string $after;

	public function __construct(Player $player, string $before, string $after){
		$this->player = $player;
		$this->before = $before;
		$this->after = $after;
	}

	public function getBefore() : string{
		return $this->before;
	}

	public function getAfter() : string{
		return $this->after;
	}
}