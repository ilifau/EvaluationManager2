<?php
 
include_once "./Services/Repository/classes/class.ilObjectPluginListGUI.php";
 
/**
 * handles the presentation in container items (categories, courses, ...)
 * together with the corresponding ...Access class.
 *
 * PLEASE do not create instances of larger classes here. Use the
 * ...Access class to get DB data and keep it small.
 */
class ilObjEvaluationManager2ListGUI extends ilObjectPluginListGUI
{
 
	/**
	 * Init type
	 */
	function initType() {
		$this->setType(ilEvaluationManager2Plugin::ID);
	}
 
	/**
	 * Get name of gui class handling the commands
	 */
	function getGuiClass()
	{
		return "ilObjEvaluationManager2GUI";
	}
 
	/**
	 * Get commands
	 */
	function initCommands()
	{
		return array
		(
			array(
				"permission" => "read",
				"cmd" => "showContent",
				"default" => true),
			array(
				"permission" => "write",
				"cmd" => "editProperties",
				"txt" => $this->txt("edit"),
				"default" => false),
		);
	}
 
	/**
	 * Get item properties
	 *
	 * @return        array                array of property arrays:
	 *                                "alert" (boolean) => display as an alert property (usually in red)
	 *                                "property" (string) => property name
	 *                                "value" (string) => property value
	 */
	function getProperties()
	{
		global $lng, $ilUser;
 
		$props = array();
 
		$this->plugin->includeClass("class.ilObjEvaluationManager2Access.php");
		if (!ilObjEvaluationManager2Access::checkOnline($this->obj_id))
		{
			$props[] = array("alert" => true, "property" => $this->txt("status"),
				"value" => $this->txt("offline"));
		}
 
		return $props;
	}
}
?>