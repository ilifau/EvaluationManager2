<?php
 
include_once("./Services/Repository/classes/class.ilObjectPlugin.php");
require_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/EvaluationManager2/classes/class.ilObjEvaluationManager2GUI.php");
 
/**
 */
class ilObjEvaluationManager2 extends ilObjectPlugin
{
    protected int $fauOrgNumber = 0;

	/**
	 * Constructor
	 *
	 * @access        public
	 * @param int $a_ref_id
	 */
	function __construct($a_ref_id = 0)
	{
		parent::__construct($a_ref_id);
	}
 
	/**
	 * Get type.
	 */
	final function initType()
	{
		$this->setType(ilEvaluationManager2Plugin::ID);
        $this->doRead();
	}
 
	/**
	 * Create object
	 */
	function doCreate()
	{
		global $ilDB;

        $ilDB->manipulate("INSERT INTO rep_robj_xevm_orgs ".
            "(obj_id, fau_org_number) VALUES (".
            $ilDB->quote($this->getId(), "integer").",".
            $ilDB->quote(0, "integer").
            ")"
        );
	}
 
	/**
	 * Read data from db
	 */
	function doRead()
	{
		global $ilDB;
		$set = $ilDB->query("SELECT * FROM rep_robj_xevm_orgs ".
			" WHERE obj_id = ".$ilDB->quote($this->getId(), "integer")
		);

		while ($rec = $ilDB->fetchAssoc($set)) {
			$this->setFauOrgNumber($rec["fau_org_number"]);
		}
	}
 
	/**
	 * Update data
	 */
	function doUpdate()
	{
		global $ilDB;

		$ilDB->manipulate($up = "UPDATE rep_robj_xevm_orgs SET ".
            " fau_org_number = ".$ilDB->quote($this->getFAUOrgNumber(), "integer").
			" WHERE obj_id = ".$ilDB->quote($this->getId(), "integer")
		);
	}
 
	/**
	 * Delete data from db
	 */
	function doDelete()
	{
		global $ilDB;
 
		$ilDB->manipulate("DELETE FROM rep_robj_xevm_orgs WHERE ".
			" obj_id = ".$ilDB->quote($this->getId(), "integer")
		);
	}
 
	/**
	 * Do Cloning
	 */
	function doClone($a_target_id,$a_copy_id,$new_obj)
	{
		global $ilDB;
 
		$new_obj->setFAUOrgNumber($this->getFAUOrgNumber());
		$new_obj->update();
	}
 
	/**
	 * Set fauOrgNumber
	 *
	 * @param        integer                 fauOrgNumber
	 */
	public function setFAUOrgNumber($a_val)
	{
		$this->fauOrgNumber = $a_val;
	}
 
	/**
	 * get FAUOrgNumber
	 *
	 * @return        integer                fauOrgNumber
	 */
	public function getFAUOrgNumber()
	{
		return $this->fauOrgNumber;
	}

    private function getCourseNumberOfRefID($courseRefID) : int {
        global $ilDB;
        $set = $ilDB->query("SELECT fsc.course_id FROM 
                                   object_reference orf JOIN fau_study_courses fsc 
                                   ON orf.obj_id = fsc.ilias_obj_id 
                                   WHERE orf.ref_id = ".$courseRefID);
        $result = $ilDB->fetchAll($set);
        if(empty($result)) return 0;
        return $result[0]['course_id'];
    }

    public function addCourseToObject($courseRefID) : bool {
        global $ilDB;

        $courseNumber = $this->getCourseNumberOfRefID($courseRefID);
        $isCourseAlreadyUsed = $this->checkIfCourseIsAlreadyUsed($courseNumber);
        $isCourseInOrg = $this->checkIfCourseIsInOrgUnit($courseNumber);
        if($isCourseAlreadyUsed || !$isCourseInOrg || $courseNumber == 0) {
            return false;
        }

        $ilDB->manipulate("INSERT INTO rep_robj_xevm_courses ".
            "(course_id, obj_id) VALUES (".
            $ilDB->quote($courseNumber, "integer")."," .
            $ilDB->quote($this->getId(), "integer").
            ")"
        );

        return true;
    }

    protected function checkIfCourseIsAlreadyUsed(int $courseNumber) : bool {
        global $ilDB;
        $set = $ilDB->query("SELECT * FROM rep_robj_xevm_courses WHERE obj_id = ".
            $ilDB->quote($this->getId(), "integer") .
            " AND course_id = " . $courseNumber
        );
        $result = $ilDB->fetchAll($set);
        if(empty($result)) { //eintrag noch nicht vorhanden
            return false;
        } else {
            return true;
        }
    }

    protected function checkIfCourseIsInOrgUnit(int $courseNumber) : bool {
        global $ilDB;
        $set = $ilDB->query("SELECT * FROM fau_study_event_orgs eo 
                                   JOIN fau_study_courses sc ON eo.event_id = sc.event_id 
                                   WHERE sc.course_id = ". $courseNumber .
                                   " AND eo.fauorg_nr = " . $this->getFAUOrgNumber());
        $result = $ilDB->fetchAll($set);
        if(empty($result)) { //Kurs ist nicht in Org-Einheit zu finden
            return false;
        } else {
            return true;
        }
    }

    public function getChosenCourseList() : array {
        global $ilDB;
        $set = $ilDB->query(
            "SELECT eo.fauorg_nr, eo.event_id, sc.course_id, sc.title, xc.obj_id FROM fau_study_event_orgs eo JOIN fau_study_courses sc JOIN rep_robj_xevm_courses xc
                  ON eo.event_id = sc.event_id AND xc.course_id = sc.course_id
                  where xc.obj_id = " . $this->getId() . " AND eo.fauorg_nr = " . $this->getFAUOrgNumber());
        return $ilDB->fetchAll($set);
    }

    public function isFAUOrgNumberValid(int $fauOrgNumber) : bool {
        global $ilDB;
        $set = $ilDB->query("SELECT defaulttext FROM fau_org_orgunits WHERE fauorg_nr = ". $fauOrgNumber);
        $result = $ilDB->fetchAll($set);
        if(empty($result)) { //org-nummer existiert nicht
            return false;
        } else {
            return true;
        }
    }
}
?>