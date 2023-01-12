<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class chauffage extends eqLogic {
	/*     * *************************Attributs****************************** */

	/*     * ***********************Methode static*************************** */

	/*
	* Fonction exécutée automatiquement toutes les minutes par Jeedom
	public static function cron() {}
	*/

	/*
	* Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
	public static function cron5() {}
	*/

	/*
	* Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
	*/
	public static function cron10() {
		foreach (self::byType(__CLASS__,true) as $chauffage) {
			$chauffage->setConsignes();
		}
	}

	/*
	* Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
	public static function cron15() {}
	*/

	/*
	* Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
	public static function cron30() {}
	*/

	/*
	* Fonction exécutée automatiquement toutes les heures par Jeedom
	public static function cronHourly() {}
	*/

	/*
	* Fonction exécutée automatiquement tous les jours par Jeedom
	public static function cronDaily() {}
	*/

	/*     * *********************Méthodes d'instance************************* */

	// Fonction exécutée automatiquement après la création de l'équipement
	public function postInsert() {
		$refresh = new chauffageCmd();
		$refresh->setLogicalId('refresh');
		$refresh->setIsVisible(1);
		$refresh->setName(__('Rafraichir',__FILE__));
		$refresh->setType('action');
		$refresh->setSubType('other');
		$refresh->setEqLogic_id($this->getId());
		$refresh->save();
	}

	// Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
	public function preSave() {
		$schedules = $this->getConfiguration('schedules');
		foreach ($schedules as $schedule) {
			if (preg_match('/^\d+(\.\d*)?$/',$schedule['consigne']) != 1) {
				throw new Exception(__("Une consigne n'est pas une valeur numérique",__FILE__));
			}
		}
		foreach ($this->getConfiguration('absences') as $absence) {
			$du = DateTime::createFromFormat("d/m/Y H:i:s", $absence['du'])->getTimeStamp();
			$au = DateTime::createFromFormat("d/m/Y H:i:s", $absence['au'])->getTimeStamp();
			if ($au <= $du) {
				throw new Exception(__("La fin d'une période d'absence doit être postérieure à son début!",__FILE__));
			}
		}
	}

	// Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
	public function postSave() {
		foreach ($this->getConfiguration('zones') as $zone) {
			$cmd = $this->getConsigneCmd($zone['id']);
			if (! is_object($cmd)){
				log::add("chauffage","info",__(sprintf("%s: Création de la commande consigne pour la zone '%s'", $this->getHumanName(), $zone['id']),__FILE__));
				$cmd = new chauffageCmd();
				$cmd->setEqLogic_id($this->getId());
				$cmd->setIsVisible(1);
				$cmd->setName('consigne_' . $zone['name']);
				$cmd->setType('info');
				$cmd->setSubType('numeric');
				$cmd->setLogicalId('consigne');
				$cmd->setConfiguration('zoneId',$zone['id']);
				$cmd->setUnite('°C');
				$cmd->save();
			}
			$cmd = $this->getDeltaCmd($zone['id']);
			if (! is_object($cmd)){
				log::add("chauffage","info",__(sprintf("%s: Création de la commande delty pour la zone '%s'", $this->getHumanName(), $zone['id']),__FILE__));
				$cmd = new chauffageCmd();
				$cmd->setEqLogic_id($this->getId());
				$cmd->setIsVisible(1);
				$cmd->setName('delta_' . $zone['name']);
				$cmd->setType('info');
				$cmd->setSubType('numeric');
				$cmd->setLogicalId('delta');
				$cmd->setConfiguration('zoneId',$zone['id']);
				$cmd->setUnite('°C');
				$cmd->save();
			}
		}
	}

	// Fonction excécutée apès sauvegarde de l'eqLogic et des commandes
	public function postAjax() {
		foreach ($this->getConfiguration('zones') as $zone) {
			$foundValues = [];
			foreach ($this->getCmd('info') as $cmd) {
				if ($cmd->getConfiguration('zoneId') != $zone['id']){
					continue;
				}
				if ($cmd->getLogicalId() == 'delta') {
					continue;
				}
				$foundValues[] = $cmd->getId();
			}
			$deltaCmd = $this->getDeltaCmd($zone['id']);
			if (! is_object($deltaCmd)){
				log::add("chauffage","warning",__(sprintf("%s : commande %s introuvable",$this->getHumanName(), 'delta'),__FILE__));
			}
			$oldValue = $deltaCmd->getValue();
			$newValue = '';
			foreach ($foundValues as $foundValue) {
				$newValue .= '#' . $foundValue . '#';
			}
			$deltaCmd->setValue($newValue);
			$deltaCmd->save();
		}
		$this->setConsignes();
	}

	private function setZoneConsigne($zoneId) {
		function timeDiff($k1, $k2) {
			$d1 = substr($k1,0,1);
			$h1 = substr($k1,1,2);
			$m1 = substr($k1,3,2);
			$t1 = $d1*1440 + $h1*60 + $m1;
			$d2 = substr($k2,0,1);
			$h2 = substr($k2,1,2);
			$m2 = substr($k2,3,2);
			$t2 = $d2*1440 + $h2*60 + $m2;
			if ($t1 < $t2){
				return $t2 - $t1;
			} else {
				return 10080 - ($t1 - $t2);
			}
		}

		$enAbsence=false;
		$strNow = strftime("%u%H%M");
		$now = new dateTime();
		foreach ($this->getConfiguration('absences') as $absence) {
			$du = dateTime::createFromFormat('d/m/Y H:i:s',$absence['du']);
			$au = dateTime::createFromFormat('d/m/Y H:i:s',$absence['au']);
			if ($du <= $now and $au >= $now){
				$enAbsence = true;
				$strNow = $au->format('NHi');
				break;
			}
		}

		$lastSchedule = [];
		$firstSchedule = [];
		$activeSchedule = [];
		$nextSchedule = [];
		foreach ($this->getConfiguration('schedules') as $schedule){
			if ($schedule['zoneid'] != $zoneId){
				continue;
			}
			if ($schedule['key'] <= $strNow) {
				if (! $activeSchedule || $schedule['key'] > $activeSchedule['key']) {
					$activeSchedule = $schedule;
				}
			} else {
				if (! $nextSchedule || $schedule['key'] < $nextSchedule['key']) {
					$nextSchedule = $schedule;
				}
			}
			if (! $firstSchedule || $schedule['key'] < $firstSchedule['key']) {
				$firstSchedule = $schedule;
			}
			if (! $lastSchedule || $schedule['key'] > $lastSchedule['key']) {
				$lastSchedule = $schedule;
			}
		}
		if (! $activeSchedule) {
			$activeSchedule = $lastSchedule;
		}
		if (! $nextSchedule) {
			$nextSchedule = $firstSchedule;
		}
		$consigne = "";
		if ($enAbsence) {
			$consigne = 4;
		} else {
			$consigne = $activeSchedule['consigne'];
		}
		if ($consigne < $nextSchedule['consigne']) {
			$timeToNext = timeDiff($strNow, $nextSchedule['key']);
			$consigneToNext = $nextSchedule['consigne'] - ($timeToNext * 2 / 60);
			$consigne = max($consigne,$consigneToNext);
		}
		$consigne = round($consigne,1);

		$cmd=$this->getConsigneCmd($zoneId);
		if (is_object($cmd)){
			$this->checkAndUpdateCmd($cmd,$consigne);
		}
	}

	public function zoneExists($zoneId) {
		foreach ($this->getConfiguration('zones') as $zone) {
			if ($zone['id'] == $zoneId){
				return True;
			}
		}
		return False;
	}

	public function setConsignes() {
		$consignes = [];
		foreach ($this->getConfiguration('zones') as $zone) {
			if ($zone['isEnable'] != 1) {
				continue;
			}
			$this->setZoneConsigne($zone['id']);
		}
	}

	public function getConsigneCmd($zoneId = 0) {
		$cmds = chauffageCmd::byEqLogicIdAndLogicalId($this->getId(),'consigne', true);
		if ($zoneId == 0) {
			return $cmds;
		}
		foreach ($cmds as $cmd) {
			if ($cmd->getConfiguration("zoneId") == $zoneId) {
				return $cmd;
			}
		}
		return null;
	}

	public function getDeltaCmd($zoneId = 0) {
		$cmds = chauffageCmd::byEqLogicIdAndLogicalId($this->getId(),'delta', true);
		if ($zoneId == 0) {
			return $cmds;
		}
		foreach ($cmds as $cmd) {
			if ($cmd->getConfiguration("zoneId") == $zoneId) {
				return $cmd;
			}
		}
		return null;
	}

	public function getTemperatureCmd($zoneId = 0) {
		$cmds = chauffageCmd::byEqLogicIdAndLogicalId($this->getId(),'temperature', true);
		if ($zoneId == 0) {
			return $cmds;
		}
		$cmdsToReturn = [];
		foreach ($cmds as $cmd) {
			if ($cmd->getConfiguration("zoneId") == $zoneId) {
				$cmdsToReturn[] = $cmd;
			}
		}
		return $cmdsToReturn;
	}

	/*     * **********************Getteur Setteur*************************** */

}

class chauffageCmd extends cmd {
	/*     * *************************Attributs****************************** */

	/*
	public static $_widgetPossibility = array();
	*/

	/*     * ***********************Methode static*************************** */


	/*     * *********************Methode d'instance************************* */

	
	/* Permet d'empêcher la suppression des commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS */
	public function dontRemoveCmd() {
		if ($this->getLogicalId() == 'consigne' || $this->getLogicalId() == 'delta') {
			$zoneId = $this->getConfiguration('zoneId');
			if ($this->getEqLogic()->zoneExists($zoneId)){
				return True;
			}
		}
		return false;
	}
	
	public function preSave() {
		if (in_array($this->getLogicalId(),['temperature', 'ouvrant'])){
			$calcul = $this->getConfiguration('calcul');
			if (strpos($calcul, '#' . $this->getId() . '#') !== false) {
				throw new Exception(__('Vous ne pouvez appeler la commande elle-même (boucle infinie) sur', __FILE__) . ' : ' . $this->getName());
			}
			$added_value = [];
			preg_match_all("/#([0-9]+)#/", $calcul, $matches);
			$value = '';
			foreach ($matches[1] as $cmd_id) {
				if (isset($added_value[$cmd_id])){
					continue;
				}
				$cmd = self::byId($cmd_id);
				if (is_object($cmd) && $cmd->getType() == 'info') {
					$value .= '#' . $cmd_id . '#';
					$added_value[$cmd_id] = $cmd_id;
				}
			}
			preg_match_all("/variable\((.+?)\)/", $calcul, $matches);
			foreach ($matches[1] as $variable) {
				if (isset($added_value['#variable(' . $variable . ')#'])){
					continue;
				}
				$value .= '#variable(' . $variable . ')#';
				$added_value['#variable(' . $variable . ')#'] = 1;
			}
			$this->setValue($value);
		}
	}

	// Exécution d'une commande
	public function execute($_options = array()) {
		$eqLogic = $this->getEqLogic();
		log::add("chauffage","info","execute : " . $this->getHumanName() . "  LogicalId: " . $this->getLogicalId() );
		if ($this->getLogicalId() == 'refresh') {
			$eqLogic->refresh();
			return;
		}
		if ($this->getLogicalId() == 'temperature' || $this->getLogicalId() == 'ouvrant') {
			try {
				return jeedom::evaluateExpression($this->getConfiguration('calcul'));
			} catch (Exception $e) {
				log::add('chauffage', 'info', $e->getMessage());
				return $this->getConfiguration('calcul');
			}
		}
		if ($this->getLogicalId() == 'delta') {
			$zoneId = $this->getConfiguration('zoneId');
			$eqLogic = $this->getEqLogic();
			$consigneCmd = $eqLogic->getConsigneCmd($zoneId);
			if (! is_object($consigneCmd)){
				log::add("chauffage","error",__(sprintf("&s : consigne introuvable pour la zone %s",$eqLogic->getHumanName(),$zoneId),__FILE__));
				return "";
			}
			$consigne = $consigneCmd->execCmd();
			$count = 0;
			$total = 0;
			foreach ($eqLogic->getTemperatureCmd($zoneId) as $cmd) {
				$total += $cmd->execCmd();
				$count++;
			}
			if ($count == 0){
				return "";
			}
			return ($total/$count)-$consigne; 
		}

	}

	/*     * **********************Getteur Setteur*************************** */

}
