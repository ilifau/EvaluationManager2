<?php
 
include_once("./Services/Repository/classes/class.ilRepositoryObjectPlugin.php");
 
/**
 */
class ilEvaluationManager2Plugin extends ilRepositoryObjectPlugin
{
	const ID = "xevm";

    /**
     * get plugin name
     * @return string
     */
	function getPluginName()
	{
		return "EvaluationManager2";
	}

    /**
     * uninstall related database tables
     */
	protected function uninstallCustom() {
		global $ilDB;
        try {
            if($ilDB->checkTableName("rep_robj_xevm_courses")) {
                $ilDB->dropTable('rep_robj_xevm_courses');
            }
            if($ilDB->checkTableName("rep_robj_xevm_orgs")) {
                $ilDB->dropTable('rep_robj_xevm_orgs');
            }
        }
        catch(Exception $e) {
            //catch missing stuff, TODO: better handling needed
        }
    }
}
?>