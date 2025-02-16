<?php
 
include_once("./Services/Repository/classes/class.ilObjectPlugin.php");
require_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/EvaluationManager2/classes/class.ilObjEvaluationManager2GUI.php");

/**
 * Class for EvaluationManager2-Object
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
	final function initType(): void
	{
		$this->setType(ilEvaluationManager2Plugin::ID);
        $this->doRead();
	}
 
	/**
	 * Create object in database, with obj_id and fau_org_number
	 */
	function doCreate(): void
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
	function doRead(): void
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
	function doUpdate(): void
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
	function doDelete(): void
	{
		global $ilDB;
 
		$ilDB->manipulate("DELETE FROM rep_robj_xevm_orgs WHERE ".
			" obj_id = ".$ilDB->quote($this->getId(), "integer")
		);
	}
 
	/**
	 * Do Cloning
     * TODO: is this necessary?
	 */
	function doClone($a_target_id,$a_copy_id,$new_obj): void
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
	public function setFAUOrgNumber($a_val): void
	{
		$this->fauOrgNumber = $a_val;
	}
 
	/**
	 * get FAUOrgNumber
	 *
	 * @return        integer                fauOrgNumber
	 */
	public function getFAUOrgNumber(): int
	{
		return $this->fauOrgNumber;
	}

    /**
     * find Course-Number with Ref-ID
     * @param $courseRefID
     * @return int
     */
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

    /**
     * add Course to Database
     * @param $courseRefID
     * @return bool
     */
    public function addCourseToObject($courseRefID) : bool {
        global $ilDB;

        $courseNumber = $this->getCourseNumberOfRefID($courseRefID);
        $isCourseAlreadyUsed = $this->checkIfCourseIsAlreadyUsed($courseNumber);
        $isCourseInOrg = $this->checkIfCourseIsInOrgUnit($courseNumber);
        if($isCourseAlreadyUsed || !$isCourseInOrg || $courseNumber == 0) {
            return false;
        }

        $ilDB->manipulate("INSERT INTO rep_robj_xevm_courses ".
            "(evaluate, course_id, obj_id) VALUES (".
            $ilDB->quote("1", "integer")."," .
            $ilDB->quote($courseNumber, "integer")."," .
            $ilDB->quote($this->getId(), "integer").
            ")"
        );

        return true;
    }

    /**
     * check if course already used with the object
     * @param int $courseNumber
     * @return bool
     */
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

    /**
     * check if Course is in the fau_org_unit
     * @param int $courseNumber
     * @return bool
     */
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

    /**
     * get Course List of this object
     * @return array
     */
    public function getChosenCourseList() : array {
        global $ilDB;
        $set = $ilDB->query(
            "SELECT eo.fauorg_nr, eo.event_id, sc.course_id, sc.title, xc.obj_id, xc.evaluate, obd.description FROM fau_study_event_orgs eo JOIN fau_study_courses sc JOIN rep_robj_xevm_courses xc JOIN object_reference obr JOIN object_description obd
                  ON eo.event_id = sc.event_id AND xc.course_id = sc.course_id AND obr.obj_id = sc.ilias_obj_id AND obd.obj_id = obr.obj_id
                  where xc.obj_id = " . $this->getId() . " AND eo.fauorg_nr = " . $this->getFAUOrgNumber());
        return $ilDB->fetchAll($set);
    }

    /**
     * check if fau_org_number is valid
     * @param int $fauOrgNumber
     * @return bool
     */
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

    /**
     * delete Course-Entry from Course-List in database
     * @param string $ref_id
     * @return bool
     */
    public function deleteEntry(string $ref_id) : bool {

        global $ilDB;
        $set = $ilDB->query("DELETE FROM rep_robj_xevm_courses WHERE course_id = ". $ref_id);
        var_dump($set);
        exit();
        if(empty($result)) { //org-nummer existiert nicht
            return false;
        } else {
            return true;
        }
    }
}
?>