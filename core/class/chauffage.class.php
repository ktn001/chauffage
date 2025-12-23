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
	* Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
	*/
	public static function cron10() {
		foreach (self::byType(__CLASS__,true) as $chauffage) {
			$chauffage->setConsignes();
		}
	}

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
			$du = DateTime::createFromFormat("d/m/Y H:i", $absence['du'])->getTimeStamp();
			$au = DateTime::createFromFormat("d/m/Y H:i", $absence['au'])->getTimeStamp();
			if ($au <= $du) {
				throw new Exception(__("La fin d'une période d'absence doit être postérieure à son début!",__FILE__));
			}
		}
	}

	// Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
	public function postSave() {
		$cmd = $this->getCmd('info','chaudiere');
		if ( ! is_object($cmd)){
			log::add("chauffage","info",__(sprintf("%s: Création de la commande 'chaudiere'", $this->getHumanName()),__FILE__));
			$cmd = new chauffageCmd();
			$cmd->setEqLogic_id($this->getId());
			$cmd->setIsVisible(1);
			$cmd->setName(__('Etat chaudière',__FILE__));
			$cmd->setType('info');
			$cmd->setSubType('binary');
			$cmd->setLogicalId('chaudiere');
			$cmd->save();
		}
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
				log::add("chauffage","info",__(sprintf("%s: Création de la commande delta pour la zone '%s'", $this->getHumanName(), $zone['id']),__FILE__));
				$cmd = new chauffageCmd();
				$cmd->setEqLogic_id($this->getId());
				$cmd->setIsVisible(1);
				$cmd->setName('delta_' . $zone['name']);
				$cmd->setType('info');
				$cmd->setSubType('numeric');
				$cmd->setLogicalId('delta');
				$cmd->setConfiguration('zoneId',$zone['id']);
				$cmd->setConfiguration('minValue',"-20");
				$cmd->setConfiguration('maxValue',"20");
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
		$values = '';
		foreach ($this->getCmd('info','delta',null,true) as $deltaCmd){
			$values .= '#' . $deltaCmd->getId() . '#';
		}
		foreach ($this->getCmd('info','ouvrant',null,true) as $ouvrantCmd){
			$values .= '#' . $ouvrantCmd->getId() . '#';
		}
		$this->getCmd('info','chaudiere')->setValue($values)->save();
		$this->setConsignes();
	}

	private function timeDiff($k1, $k2) {
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

	private function setZoneConsigne($zoneId) {
		$zone = $this->getZone($zoneId);
		log::add("chauffage","debug",sprintf(__("Début du calcul de la consigne pour la zone '%s'",__FILE__), $zone['name']));
		$enAbsence=false;
		$keyNow = strftime("%u%H%M");
		$keyConsigne = $keyNow;
		$now = new dateTime();
		foreach ($this->getConfiguration('absences') as $absence) {
			$du = dateTime::createFromFormat('d/m/Y H:i',$absence['du']);
			$au = dateTime::createFromFormat('d/m/Y H:i',$absence['au']);
			if ($du <= $now and $au >= $now){
				$enAbsence = true;
				$keyConsigne = $au->format('NHi');
				log::add("chauffage","debug",sprintf(__("En absence : => %s",__FILE__), $keyConsigne));
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
			if ($schedule['key'] <= $keyConsigne) {
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
			$nextSchedule['key'] = $keyConsigne;
			$nextSchedule['consigne'] = $activeSchedule['consigne'];
		} else {
			$consigne = $activeSchedule['consigne'];
		}
		log::add("chauffage","debug",sprintf(__("activeSchedule: %s %4.1f°c",__FILE__),$activeSchedule['key'],$activeSchedule['consigne']));
		log::add("chauffage","debug",sprintf(__("nextSchedule:   %s %4.1f°c",__FILE__),$nextSchedule['key'],$nextSchedule['consigne']));
		log::add("chauffage","debug",sprintf(__("consigne brute:       %4.1f°C",__FILE__),$consigne));
		if ($consigne < $nextSchedule['consigne']) {
			$gradiant = is_numeric($zone[gradiant]) ? $zone['gradiant'] : 0;
			log::add("chauffage","debug","gradiant: " . $gradiant);
			$timeToNext = $this->timeDiff($keyNow, $nextSchedule['key']);
			log::add("chauffage","debug",sprintf(__("Temps avant prochaine consigne: %s minutes",__FILE__),$timeToNext));
			$consigneToNext = $nextSchedule['consigne'] - ($timeToNext * 0.3 / 60);
			log::add("chauffage","debug",sprintf(__("Consigne pour atteindre prochaine consigne: %4.1f°c",__FILE__),$consigneToNext));
			$consigne = max($consigne,$consigneToNext);
		}
		log::add("chauffage","debug",sprintf(__("consigne finale: %4.1f°C",__FILE__),$consigne));
		$consigne = round($consigne,1);

		$cmd=$this->getConsigneCmd($zoneId);
		if (is_object($cmd)){
			if ($cmd->execCmd() != $consigne) {
				log::add("chauffage","info",sprintf(__("consigne zone '%s': %4.1f°C",__FILE__),$zone['name'],$consigne));
				$this->checkAndUpdateCmd($cmd,$consigne);
			}
		}
	}

	public function getChaudiereStatus () {
		log::add("chauffage","debug",__("Calcul de statut de la chaudière",__FILE__));
		$deltaSum = 0;
		foreach ($this->getConfiguration('zones') as $zone){
			if ($zone['isEnable'] != 1) {
				continue;
			}
			foreach ($this->getOuvrantCmd($zone['id']) as $ouvrant){
				if ($ouvrant->execCmd() == 1){
					continue;
				}
			}
			$delta = $this->getDeltaCmd($zone['id']);
			if (!$delta->isOk()) {
				log::add("chauffage","warning",sprintf(__("  La zone %s est en défaut",__FILE__),$zone['id']));
				continue;
			}
			$deltaSum += $delta->execCmd() * $zone['poids'];
		}
		$statusCmd = $this->getCmd('info','chaudiere');
		if ($deltaSum < 0) {
			if ($statusCmd->execCmd() != 1) {
				log::add("chauffage","debug",__("Démarrage de la chaudière",__FILE__));
				$cmdOnId = $this->getConfiguration('cmd_on');
				log::add("chauffage","debug",$cmdOnId);
				$cmdOnId = str_replace('#','',$cmdOnId);
				log::add("chauffage","debug",$cmdOnId);
				$cmdOn = cmd::byId($cmdOnId);
				if (!is_object($cmdOn)) {
					log::add("chauffage","error",sprintf(__("Commande d'enclenchement de la chaudière %s introuvable",__FILE__),$cmdOnId));
				} else {
					$cmdOn->execCmd();
				}
			}
			return 1;
		} else {
			if ($statusCmd->execCmd() != 0) {
				log::add("chauffage","debug",__("Arret de la chaudière",__FILE__));
				$cmdOffId = $this->getConfiguration('cmd_off');
				log::add("chauffage","debug",$cmdOffId);
				$cmdOffId = str_replace('#','',$cmdOffId);
				log::add("chauffage","debug",$cmdOffId);
				$cmdOff = cmd::byId($cmdOffId);
				if (!is_object($cmdOff)) {
					log::add("chauffage","error",sprintf(__("Commande de déclenchement de la chaudière %s introuvable",__FILE__),$cmdOffId));
				} else {
					$cmdOff->execCmd();
				}
			}
			return 0;
		}
	}

	public function getZone($zoneId) {
		foreach ($this->getConfiguration('zones') as $zone) {
			if ($zone['id'] == $zoneId){
				return $zone;
			}
		}
		return False;
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

	public function getOuvrantCmd($zoneId = 0) {
		$cmds = chauffageCmd::byEqLogicIdAndLogicalId($this->getId(),'ouvrant', true);
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

	public function isOk() {
		if ($this->getLogicalId() == 'delta') {
			$zoneId = $this->getConfiguration('zoneId');
			$eqLogic = $this->getEqLogic();
			$consigneCmd = $eqLogic->getConsigneCmd($zoneId);
			if (! is_object($consigneCmd)) {
				log::add("chauffage","error",sprintf(__("Commamde de consigne pour la zone %1 introuvable",__FILE__),$zoneId));
				return false;
			}
			$ok = true;
			foreach ($eqLogic->getTemperatureCmd($zoneId) as $cmd) {
				$age = strtotime('now') - strtotime($cmd->getCollectDate());
				if ($age > 40) {
					log::add("chauffage","warning",sprintf(__("La commande %s n'a pas été actualisée depuis %s minutes",__FILE__),$cmd->getHumanName(),$age/60));
					$ok = false;
				}
			}
			if (! $ok) {
				return false;
			}
			return true;
		}
		return true;
	}

	// Exécution d'une commande
	public function execute($_options = array()) {
		$eqLogic = $this->getEqLogic();
		log::add("chauffage","debug","execute: " . $this->getHumanName() . "  LogicalId: " . $this->getLogicalId() );
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
				log::add("chauffage","debug",sprintf(__("  %s: temperature: %s",__FILE__),$cmd->getHumanName(), $cmd->execCmd()));
				$total += $cmd->execCmd();
				$count++;
			}
			if ($count == 0){
				return "";
			}
			$temperatureMoyenne = $total/$count;
			log::add("chauffage","debug",__("  temperature moyenne:",__FILE__) . $temperatureMoyenne);
			return $temperatureMoyenne-$consigne; 
		}
		if ($this->getLogicalId() == 'chaudiere') {
			return $this->getEqLogic()->getChaudiereStatus();
		}
	}

	/*     * **********************Getteur Setteur*************************** */

}
