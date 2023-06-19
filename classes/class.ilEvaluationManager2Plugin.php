<?php
 
include_once("./Services/Repository/classes/class.ilRepositoryObjectPlugin.php");
 
/**
 */
class ilEvaluationManager2Plugin extends ilRepositoryObjectPlugin
{
	const ID = "xevm";
 
	// must correspond to the plugin subdirectory
	function getPluginName()
	{
		return "EvaluationManager2";
	}
 
	protected function uninstallCustom() {
		
		global $ilDB;
		//when uninstalling, remove content in table rep_obj_xevm_data
		
	}
}
?>