<?php
declare(strict_types=1);

namespace Tier;

use BandAPI\BandAPI;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\player\IPlayer;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use Tier\event\TierDownEvent;
use Tier\event\TierUpEvent;
use function array_keys;
use function array_map;
use function array_values;
use function arsort;
use function date;
use function implode;
use function intval;
use function is_numeric;
use function json_encode;
use function mt_rand;
use function strtolower;
use function trim;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

class TierPlugin extends PluginBase implements Listener{
	use SingletonTrait;

	/** @var Config */
	protected Config $config;

	protected array $db;

	public const TIERS = [
		"언랭",
		"브론즈",
		"실버",
		"골드",
		"다이아",
		"플레티넘",
		"마스터",
		"그랜드마스터",
		"랭커"
	];

	public const TIER_UNRANK = "언랭";
	public const TIER_BRONZE = "브론즈";
	public const TIER_SILVER = "실버";
	public const TIER_GOLD = "골드";
	public const TIER_PLATINUM = "플레티넘";
	public const TIER_DIAMOND = "다이아";
	public const TIER_MASTER = "마스터";
	public const TIER_GRAND_MASTER = "그랜드마스터";
	public const TIER_RANKER = "랭커";

	public const TIER_POINTS = [
		self::TIER_UNRANK => 100,
		self::TIER_BRONZE => 300,
		self::TIER_SILVER => 500,
		self::TIER_GOLD => 700,
		self::TIER_PLATINUM => 1000,
		self::TIER_DIAMOND => 1300,
		self::TIER_MASTER => 1500,
		self::TIER_GRAND_MASTER => 2000,
		self::TIER_RANKER => 0
	];

	protected function onLoad() : void{
		self::setInstance($this);
	}

	protected function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->config = new Config($this->getDataFolder() . "Config.yml", Config::YAML, [
			"date" => (int) date("m"),
			"player" => []
		]);
		$this->db = $this->config->getAll();
		if((int) date("m") !== $this->db["date"]){
			$this->writePost();
			$this->db["date"] = (int) date("m");
			$this->db["player"] = [];
		}
	}

	public function writePost() : void{
		$res = [];
		for($i = 1; $i <= 10; $i++){
			$res[$i] = $this->getRank($i);
		}

		$text = "[ 오닉스서버 {$this->db["date"]}월 티어 순위 ]\n";
		$text .= "아래 순위는 점수 기준으로 작성되었습니다.";
		$text .= implode("\n", array_map(function(int $rank, string $player) : string{
			return "{$rank}위: " . $player . "님";
		}, array_keys($res), array_values($res)));
		BandAPI::sendPost($text);
	}

	protected function onDisable() : void{
		$this->config->setAll($this->db);
		$this->config->save();
	}

	public function handlePlayerJoin(PlayerJoinEvent $event) : void{
		$player = $event->getPlayer();
		if(!$this->hasData($player))
			$this->setUp($player);
	}

	public function hasData(IPlayer $player) : bool{
		return isset($this->db["player"][strtolower($player->getName())]);
	}

	public function setUp(Player $player) : void{
		$this->db["player"][strtolower($player->getName())] = [
			"points" => 0,
			"highPoints" => 0
		];
	}

	public function getTierToString(IPlayer $player) : string{
		$tier = $this->getTier($player);
		if($tier < self::TIER_POINTS[self::TIER_UNRANK]){
			return "§f언랭";
		}
		if($tier > self::TIER_POINTS[self::TIER_UNRANK] && $tier <= self::TIER_POINTS[self::TIER_BRONZE]){
			return "§7브론즈";
		}
		if($tier > self::TIER_POINTS[self::TIER_BRONZE] && $tier <= self::TIER_POINTS[self::TIER_SILVER]){
			return "§6실버";
		}
		if($tier > self::TIER_POINTS[self::TIER_SILVER] && $tier <= self::TIER_POINTS[self::TIER_GOLD]){
			return "§6골드";
		}
		if($tier > self::TIER_POINTS[self::TIER_GOLD] && $tier <= self::TIER_POINTS[self::TIER_PLATINUM]){
			return "§3플래티넘";
		}
		if($tier > self::TIER_POINTS[self::TIER_PLATINUM] && $tier <= self::TIER_POINTS[self::TIER_DIAMOND]){
			return "§b다이아";
		}
		if($tier > self::TIER_POINTS[self::TIER_DIAMOND] && $tier <= self::TIER_POINTS[self::TIER_MASTER]){
			return "§9마스터";
		}
		if($tier > self::TIER_POINTS[self::TIER_MASTER] && $tier <= self::TIER_GRAND_MASTER){
			return "§e그랜드마스터";
		}else{
			if($this->getRankByPlayer($player) <= 10){
				return "§5상위 10위";
			}else{
				return "§e그랜드마스터";
			}
		}
	}

	public function getTier(IPlayer $player) : int{
		return $this->db["player"][strtolower($player->getName())]["points"] ?? -1;
	}

	public function getHighTier(IPlayer $player) : int{
		return $this->db["player"][strtolower($player->getName())]["highPoints"] ?? -1;
	}

	public function setHighTier(IPlayer $player, int $tier) : void{
		$this->db["player"][strtolower($player->getName())]["highTier"] = $tier;
	}

	public function getNext(IPlayer $player) : int{
		$tier = $this->getTier($player);
		if($tier < self::TIER_POINTS[self::TIER_UNRANK]){
			return self::TIER_POINTS[self::TIER_UNRANK];
		}
		if($tier > self::TIER_POINTS[self::TIER_UNRANK] && $tier <= self::TIER_POINTS[self::TIER_BRONZE]){
			return self::TIER_POINTS[self::TIER_BRONZE];
		}
		if($tier > self::TIER_POINTS[self::TIER_BRONZE] && $tier <= self::TIER_POINTS[self::TIER_SILVER]){
			return self::TIER_POINTS[self::TIER_SILVER];
		}
		if($tier > self::TIER_POINTS[self::TIER_SILVER] && $tier <= self::TIER_POINTS[self::TIER_GOLD]){
			return self::TIER_POINTS[self::TIER_GOLD];
		}
		if($tier > self::TIER_POINTS[self::TIER_GOLD] && $tier <= self::TIER_POINTS[self::TIER_PLATINUM]){
			return self::TIER_POINTS[self::TIER_PLATINUM];
		}
		if($tier > self::TIER_POINTS[self::TIER_PLATINUM] && $tier <= self::TIER_POINTS[self::TIER_DIAMOND]){
			return self::TIER_POINTS[self::TIER_DIAMOND];
		}
		if($tier > self::TIER_POINTS[self::TIER_DIAMOND] && $tier <= self::TIER_POINTS[self::TIER_MASTER]){
			return self::TIER_POINTS[self::TIER_MASTER];
		}
		if($tier > self::TIER_POINTS[self::TIER_MASTER] && $tier <= self::TIER_GRAND_MASTER){
			return self::TIER_POINTS[self::TIER_GRAND_MASTER];
		}
		return 0;
	}

	public function getRankByPlayer(IPlayer $player) : int{
		$arr = [];
		foreach($this->db["player"] as $name => $data){
			$arr[$name] = $data["points"];
		}
		arsort($arr);
		$c = 0;
		foreach($arr as $playerName => $currentPoints){
			++$c;
			if($playerName === strtolower($player->getName())){
				return $c;
			}
		}
		return -1;
	}

	public function addPoint(Player $player, int $point = 1) : void{
		$before = $this->getTierToString($player);
		$this->db["player"][strtolower($player->getName())]["points"] += $point;
		$after = $this->getTierToString($player);
		$bool = $this->getTier($player) > $this->getHighTier($player);
		if($this->getHighTier($player) < $this->getTier($player)){
			$this->setHighTier($player, $this->getTier($player));
		}
		if($before === $after){
			//$player->addTitle('§c- ' . $rand, '§b<§f' . $name . '§b> <§f티어 점수§b>' . "\n" . $after_tear . ' (' . $this->db ['플레이어'][$name] . '/' . $value . ')');
			$player->sendTitle("§d<§a+" . $point . "§d>", "§d<§f{$this->getTierToString($player)}§d>\n§f{$this->getTier($player)} ({$this->getTier($player)}/{$this->getNext($player)}))");
		}else{
			$ev = new TierUpEvent($player, $before, $after);
			$ev->call();
			$player->sendTitle("§d<§a+" . $point . "§d>", "§d<§f티어 업!§d>\n§f{$this->getTierToString($player)} ({$this->getTier($player)}/{$this->getNext($player)})");
		}
	}

	public function reducePoint(Player $player, int $point = 1) : void{
		if($this->getTier($player) > 0){
			$before = $this->getTierToString($player);
			$this->db["player"][strtolower($player->getName())]["points"] -= $point;
			$after = $this->getTierToString($player);
			if($before === $after){
				$player->sendTitle("§d<§c-" . $point . "§d>", "§d<§f{$this->getTierToString($player)}§d>\n§f{$this->getTier($player)} ({$this->getTier($player)}/{$this->getNext($player)})");
			}else{
				$ev = new TierDownEvent($player, $before, $after);
				$ev->call();
				$player->sendTitle("§d<§c-" . $point . "§d>", "§d<§f티어 다운!§d>\n§f{$this->getTierToString($player)} ({$this->getTier($player)}/{$this->getNext($player)})");
			}
		}
	}

	public function handlePlayerDeath(PlayerDeathEvent $event) : void{
		$player = $event->getPlayer();
		$c = $player->getLastDamageCause();
		if($c instanceof EntityDamageByEntityEvent){
			$d = $c->getDamager();
			if($d instanceof Player){
				$this->addPoint($d, mt_rand(1, 3));
				$this->reducePoint($player, mt_rand(2, 3));
			}
		}
	}

	public function getRank(int $rank) : string{
		$arr = [];
		foreach($this->db["player"] as $name => $data){
			$arr[$name] = $data["points"];
		}
		arsort($arr);
		$c = 0;
		foreach($arr as $playerName => $points){
			if(++$c === $rank){
				return $playerName;
			}
		}
		return "없음";
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if($sender instanceof Player){
			if($command->getName() === "티어" || $command->getName() === "tier"){
				$this->sendRankUI($sender);
			}
			if($command->getName() === "티어관리" || $command->getName() === "tiermanage"){
				switch($args[0] ?? "x"){
					case "포인트올리기":
						if(trim($args[1] ?? "") !== "" && $this->hasData($target = $this->getServer()->getOfflinePlayer($args[1]))){
							if(trim($args[2] ?? "") !== "" && is_numeric($args[2]) && intval($args[2]) > 0){
								$this->addPoint($target, intval($args[2]));
								$sender->sendMessage("성공적으로 올렸습니다.");
							}else{
								$sender->sendMessage("/티어관리 포인트올리기 [닉네임] [양] - 티어 포인트를 올립니다.");
							}
						}else{
							$sender->sendMessage("/티어관리 포인트올리기 [닉네임] [양] - 티어 포인트를 올립니다.");
						}
						break;
					case "포인트제거":
						if(trim($args[1] ?? "") !== "" && $this->hasData($target = $this->getServer()->getOfflinePlayer($args[1]))){
							if(trim($args[2] ?? "") !== "" && is_numeric($args[2]) && intval($args[2]) > 0){
								$this->reducePoint($target, intval($args[2]));
								$sender->sendMessage("성공적으로 제거했습니다.");
							}else{
								$sender->sendMessage("/티어관리 포인트제거 [닉네임] [양] - 티어 포인트를 제거합니다.");
							}
						}else{
							$sender->sendMessage("/티어관리 포인트제거 [닉네임] [양] - 티어 포인트를 제거합니다.");
						}
						break;
					default:
						$sender->sendMessage("/티어관리 포인트올리기 [닉네임] [양] - 티어 포인트를 올립니다.");
						$sender->sendMessage("/티어관리 포인트제거 [닉네임] [양] - 티어 포인트를 제거합니다.");
				}
			}
		}
		return true;
	}

	public function sendRankUI(Player $player) : void{
		$res = [];
		for($i = 1; $i <= 10; $i++){
			$res[$i] = $this->getRank($i);
		}
		$pk = new ModalFormRequestPacket();
		$pk->formId = mt_rand(PHP_INT_MIN, PHP_INT_MAX);
		$pk->formData = json_encode([
			"type" => "form",
			"title" => "§lTier Rank",
			"content" => "§l티어 순위입니다.\n티어 순위는 점수 기준으로 작성 되었습니다.\n\n" . implode("\n", array_map(function(int $rank, string $playerName) : string{
					return "{$rank}위: " . $playerName . "님";
				}, array_keys($res), array_values($res))) . "\n\n내 순위: " . $this->getRankByPlayer($player),
			"buttons" => []
		]);
		$player->getNetworkSession()->sendDataPacket($pk);
	}
}