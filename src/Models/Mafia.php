<?php
namespace Clicars\Models;

use Clicars\Interfaces\IMafia;
use Clicars\Interfaces\IMember;

class Mafia implements IMafia
{
    protected $godfather;
    protected $members;
    protected $prison;

    public function __construct(IMember $godfather)
    {
        $this->godfather = $godfather;
        $this->members = [$godfather->getId() => $godfather];
        $this->prison = [];
    }

    public function getGodfather(): IMember
    {
        return $this->godfather;
    }

    public function addMember(IMember $member): ?IMember
    {
        if (isset($this->members[$member->getId()])) {
            return null;
        }

        $this->members[$member->getId()] = $member;
        return $member;
    }

    public function getMember(int $id): ?IMember
    {
        if (isset($this->members[$id])) {
            return $this->members[$id];
        }

        return null;
    }

    public function sendToPrison(IMember $member): bool
    {
        $boss = $member->getBoss();
        //Send member to prison, remove from members
        if (isset($this->members[$member->getId()])) {
            $this->prison[$member->getId()] = $member;
            unset($this->members[$member->getId()]);
        }  
        else {
            return false; //Is not a member
        }

        // Find the new boss for subordinates        
        return $this->findNewBoss($member, $boss);        
    }
    

    public function releaseFromPrison(IMember $member): bool
    {
        $boss = $member->getBoss();
        //Release from prison, add to members
        if (isset($this->prison[$member->getId()])) {
            $this->members[$member->getId()] = $member;
            unset($this->prison[$member->getId()]);
        }
        else  {
            return false; //Is not in prison
        }
        
        return $this->recoverSubordinates($member);
    }
	
    public function findBigBosses(int $minimumSubordinates): array
    {
        $bigBosses = [];
        $subordinatesCount = 0;
	foreach ($this->members as $member) {
            $subordinatesCount = count($member->getSubordinates()) + $this->countMembers($member->getSubordinates());
            if ($subordinatesCount > $minimumSubordinates) {
                $bigBosses[] = $member;
            }
	}

	return $bigBosses;
    }

    public function compareMembers(IMember $memberA, IMember $memberB): ?IMember
    {
        if ($memberA->getBoss() === $memberB->getBoss()) {
	    return null;
	}

	$a = $memberA;
        $levelA = 0;
	while ($a->getBoss() !== null) {
	    $a = $a->getBoss();
            $levelA++;
	}

	$b = $memberB;
        $levelB = 0;
	while ($b->getBoss() !== null) {
	    $b = $b->getBoss();
            $levelB++;
	}
        //Lower count of bosses is the higher level
	if ($levelA === $levelB) {
	    return null;
	}
        else if ($levelA > $levelB) {
            return $memberB;
        }
        else {
            return $memberA;
        }
    }

    private function isMember(IMember $member): bool
    {
        return isset($this->members[$member->getId()]);
    }

    private function returnSubordinatesInCommon(array $subordinates1, array $subordinates2): array
    {
        // Find subordinates in common using array_intersect_key()
        $common = array_intersect_key($subordinates1, $subordinates2);
        return $common;
    }

    private function countMembers(array $members): int
    {
        $count = 0;
        foreach ($members as $member) {
            $count += count($member->getSubordinates()) + $this->countMembers($member->getSubordinates());
        }
        
        return $count;
    }
    
    private function getOldestMember(array $members, IMember $member): ?IMember
    {
        $oldestMember = null;
        $oldestAge = 0;        
        foreach ($members as $subordinate) {
            if ($subordinate->getAge() > $oldestAge && $subordinate->getId() !== $member->getId() && $this->isMember($subordinate)) {
                $oldestMember = $subordinate;
                $oldestAge = $subordinate->getAge();
            }
        } 

        return $oldestMember;
    }

    private function moveSubordinates(array $subordinates, IMember $newBoss): void
    {
        foreach ($subordinates as $subordinate) {
            //Relocate the direct subordinates of the imprisoned member to work for the new boss
            //Avoid an oldest subordinate being its own subordinate
            if ($subordinate->getId() !== $newBoss->getId()) {
                $subordinate->setBoss($newBoss);
            }
        }   
    }

    private function findNewBoss(IMember $member, ?IMember $boss): bool
    {      
        $isGodFather = false;
        $oldestBoss = null;
        if ($boss) {
            $oldestBoss = $this->getOldestMember($boss->getSubordinates(), $member);
        }
        else { //If not have boss, is the godfather
            $isGodFather = true;
        }

        if ($oldestBoss) {
            // Relocate the direct subordinates of the imprisoned member to work for the oldest remaining boss
            $this->moveSubordinates($member->getSubordinates(), $oldestBoss);  
            return true;
        }
        
        // If there is no oldest boss at the same level, promote the oldest direct subordinate of the imprisoned member     
        $oldestSubordinate = $this->getOldestMember($member->getSubordinates(), $member);
        if ($oldestSubordinate) {
             //member going to jail is GodFather
            if ($isGodFather) $this->godfather = $oldestSubordinate;
            // Promote the oldest direct subordinate to be the boss of the others
            $oldestSubordinate->setBoss($boss);
            $this->moveSubordinates($member->getSubordinates(), $oldestSubordinate);     
            return true;     
        }  
        else {
            return false;
        }           
    }

    private function recoverSubordinates(IMember $memberOutOfJail): bool
    {
        $formerSubordinates = $memberOutOfJail->getSubordinates();
        foreach ($formerSubordinates as $formerSubordinate) {
            $currentBoss = $formerSubordinate->getBoss();
            $currentBoss->removeSubordinate($formerSubordinate);
            $formerSubordinate->setBoss($memberOutOfJail); 
            //if it is a promoted subordinate, lets see if has subordinates in common
            $this->getSubordinatesInCommon($formerSubordinate, $formerSubordinates);
        }
      
        return true;  
    }

    private function getSubordinatesInCommon($formerSubordinate, $formerSubordinates) {
        //Get subordinates from subordinate, if has been promoted
        $subordinatesFromSubordinate = $formerSubordinate->getSubordinates();        
        //Check if has subordinates in common
        $commonSubordinates = $this->returnSubordinatesInCommon($formerSubordinates, $subordinatesFromSubordinate);
        //Remove subordinates in common
        foreach($commonSubordinates as $commonSubordinate) {
            $formerSubordinate->removeSubordinate($commonSubordinate);
        }
        
        return true;
    }
}
