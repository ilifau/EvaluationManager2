<?php

use FAU\BaseGUI;
use FAU\Study\Data\SearchCondition;
use FAU\Study\Search;
use ILIAS\UI\Component\Item\Group;
use ILIAS\UI\Component\ViewControl\Pagination;
use FAU\Study\Data\ImportId;

include_once("./Services/Repository/classes/class.ilObjectPluginGUI.php");
require_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
require_once("./Services/Form/classes/class.ilTextInputGUI.php");
require_once("./Services/Form/classes/class.ilCheckboxInputGUI.php");
require_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/EvaluationManager2/classes/class.ilEvaluationManager2Plugin.php");
 
/**
 * @ilCtrl_isCalledBy ilObjEvaluationManager2GUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI, ilCommonActionDispatcherGUI
 * @ilCtrl_Calls ilObjEvaluationManager2GUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI, ilExportGUI
 */
class ListGUI extends BaseGUI {
    public function __construct(){
        parent::__construct();
        $this->lng->loadLanguageModule('xemv');
    }

    public function addTermSelectionToForm(ilPropertyFormGUI $form) {
        $term = new ilSelectInputGUI('Semester', 'term_id');
        $options = $this->dic->fau()->study()->getTermSearchOptions(null, false);
        $current = $this->dic->fau()->study()->getCurrentTerm()->toString();
        $term->setOptions($options);
        $term->setValue($current);
        $form->addItem($term);
    }

    public function addRepositorySelector(ilPropertyFormGUI $form) {
        $ref = new fauRepositorySelectorInputGUI($this->lng->txt('search_area'), 'search_ref_id');
        $ref->setTypeWhitelist(['root', 'cat']);
        $ref->setSelectableTypes(['cat']);
        $form->addItem($ref);
    }
}

class ilObjEvaluationManager2GUI extends ilObjectPluginGUI
{
	/** @var  ilCtrl */
	protected $ctrl;

	/** @var  ilTabsGUI */
	protected $tabs;

	/** @var  ilTemplate */
	public $tpl;

	/**
	 * Initialisation
	 */
	protected function afterConstructor()
	{
		global $ilCtrl, $ilTabs, $tpl;
		$this->ctrl = $ilCtrl;
		$this->tabs = $ilTabs;
		$this->tpl = $tpl;
	}
 
	public function executeCommand() {
		global $tpl;
		return parent::executeCommand();;
	}
 
	/**
	 * Get type.
	 */
	final function getType()
	{
		return ilEvaluationManager2Plugin::ID;
	}
 
	/**
	 * Handles all commmands of this class, centralizes permission checks
	 */
	function performCommand($cmd)
	{
		switch ($cmd)
		{
			case "editProperties":   // list all commands that need write permission here
			case "updateProperties":
			case "saveProperties":
			case "showExport":
            case "addCourse":
            case "export_to_chosen":
				$this->checkPermission("write");
				$this->$cmd();
				break;

            case "showContent":
            case "showExports":
				$this->checkPermission("read");
				$this->$cmd();
				break;
		}
	}

 
	/**
	 * After object has been created -> jump to this command
	 */
	function getAfterCreationCmd()
	{
		return "editProperties";
	}
 
	/**
	 * Get standard command
	 */
	function getStandardCmd()
	{
		return "showContent";
	}
 
//
// DISPLAY TABS
//
 
	/**
	 * Set tabs
	 */
	function setTabs()
    {
        global $ilCtrl, $ilAccess;

        // tab for the "show content" command
        if ($ilAccess->checkAccess("read", "", $this->object->getRefId())) {
            //none cause users with only read rights should now open this
        }

		// standard info screen tab

		// a "properties" tab
        //TODO: only allow Administrator Roles to access Properties
		if ($ilAccess->checkAccess("write", "", $this->object->getRefId()))
		{
            $this->tabs->addTab("content", $this->txt("content"), $ilCtrl->getLinkTarget($this, "showContent"));
            $this->tabs->addTab("exports", $this->txt("exports"), $ilCtrl->getLinkTarget($this, "showExports"));
			$this->tabs->addTab("properties", $this->txt("properties"), $ilCtrl->getLinkTarget($this, "editProperties"));
		}

        $this->addInfoTab();
		$this->addPermissionTab();
		$this->activateTab();
	}
 
	/**
	 * Edit Properties. This commands uses the form class to display an input form.
	 */
	protected function editProperties()
	{
		$this->tabs->activateTab("properties");
		$form = $this->initPropertiesForm();
		$this->addValuesToForm($form);
		$this->tpl->setContent($form->getHTML());
	}
 
	/**
	 * @return ilPropertyFormGUI
	 */
	protected function initPropertiesForm() {
		$form = new ilPropertyFormGUI();
		$form->setTitle($this->plugin->txt("obj_xevm"));

        $object = $this->object;

		$title = new ilTextInputGUI($this->plugin->txt("title"), "title");
		$title->setRequired(true);
		$form->addItem($title);
 
		$description = new ilTextInputGUI($this->plugin->txt("description"), "description");
		$form->addItem($description);

        $fau_org = new ilTextInputGUI("choose fau org number", "fau_org_number");
        $fau_org->setRequired(true);
        $value = strval($object->getFAUOrgNumber());
        $form->addItem($fau_org);

		$form->setFormAction($this->ctrl->getFormAction($this, "saveProperties"));
		$form->addCommandButton("saveProperties", $this->plugin->txt("update"));
 
		return $form;
	}
 
	/**
	 * @param $form ilPropertyFormGUI
	 */
	protected function addValuesToForm(&$form) {
		$form->setValuesByArray(array(
			"title" => $this->object->getTitle(),
			"description" => $this->object->getDescription(),
            "fau_org_number" => $this->object->getFAUOrgNumber()
		));
	}
 
	/**
	 * saveProperties
	 */
	protected function saveProperties() {
		$form = $this->initPropertiesForm();
		$form->setValuesByPost();
		if($form->checkInput()) {
			$this->fillObject($this->object, $form);
			$this->object->update();
			ilUtil::sendSuccess($this->plugin->txt("update_successful"), true);
			$this->ctrl->redirect($this, "editProperties");
		}
		$this->tpl->setContent($form->getHTML());
	}

    protected function saveNewCourseEntry() {

    }
 
    protected function showContent() {
        /** @var ilObjTestRepositoryObject $object */
        $object = $this->object;

        $this->tabs->activateTab("contents");
        $form = new ilPropertyFormGUI();
        $form->setTitle($object->getTitle());
        /*
        $title = new ilNonEditableValueGUI($this->plugin->txt("title"));
        $title->setInfo($object->getTitle());
        $form->addItem($title);
        */

        $listgui = new ListGUI();
        $listgui->addTermSelectionToForm($form);

        $fau_org = new ilNonEditableValueGUI('FAU Org Nummer'); //read!
        $fau_org->setValue($this->object->getFAUOrgNumber());
        $form->addItem($fau_org);

        //Course Number (obj_id, ref_id oder course_id erlaubt
        $course_obj_ref = new ilNumberInputGUI('Kurs-Nummer', 'course_number_entry');
        $form->addItem($course_obj_ref);

        //formular mit optional-filter wie in "lehrveranstaltung aus campo suchen";
        // TODO: how to get namespace fau in here?

        //liste anzeigen wie "lehrveranstaltungen aus campo suchen"

        //$start_filter = $form->addCommandButton('', 'Filter anwenden', 'start_filter');
        //$reset_filter = $form->addCommandButton('', 'Filter zurücksetzen', 'reset_filter');
        $form->addCommandButton('', 'Add Course', 'add_course');
        $form->setFormAction($this->ctrl->getFormAction($this, "addCourse"));
        $list = $this->object->getChosenCourseList();
        $ausgabe = '';
        foreach($list as $element) {
            $ausgabe .= "<p>";
            $ausgabe .= "Kurs-ID: ";
            $ausgabe .= $element['course_id'];
            $ausgabe .= ", Titel: ";
            $ausgabe .= $element['title'];
            $ausgabe .= "</p>";
        }
        $this->tpl->setContent($form->getHTML() . $ausgabe);
        return $form;
    }

    protected function showExports() {
        global $ilToolbar, $ilCtrl;
        /** @var ilObjTestRepositoryObject $object */
        $object = $this->object;

        $this->tabs->activateTab("exports");

        $form = new ilPropertyFormGUI();
        $form->setTitle($object->getTitle());

        $i = new ilNonEditableValueGUI($this->plugin->txt("title"));
        $i->setInfo($object->getTitle());
        $form->addItem($i);

        $export_type = new ilSelectInputGUI($this->lng->txt('Export Art'), 'term_id');
        $export_type->setOptions(['CSV', 'EVASYS']);
        $form->addItem($export_type);

        $form->addCommandButton('export_to_chosen', 'Exportieren', 'export_to_chosen');

        $this->tpl->setContent($form->getHTML());
    }

    private function exportToChosen() {
        //do export with chosen type of export
        var_dump("Auswahl:");
        var_dump("check how to get auswahl");
        exit();
    }

    /**
	 * @param $object ilObjEvaluationManager2
	 * @param $form ilPropertyFormGUI
	 */
	private function fillObject($object, $form) {
		$object->setTitle($form->getInput('title'));
		$object->setDescription($form->getInput('description'));
		$object->setFAUOrgNumber($form->getInput('fau_org_number'));
	}

	private function activateTab() {
		$next_class = $this->ctrl->getCmdClass();
 		switch($next_class) {
			case 'showContent':
				$this->tabs->activateTab("contents");
				break;
            case 'showExports':
                $this->tabs->activateTab("exports");
                break;
		}

		return;
	}

    protected function addCourse(){
        $form = $this->showContent();
        $form->setValuesByPost();
        if($form->checkInput()) {
            $result = $this->object->addCourseToObject($form->getInput('course_number_entry'));
            if ($result) {
                ilUtil::sendSuccess($this->plugin->txt("update_successful"), true);
            } else {
                ilUtil::sendFailure($this->plugin->txt("Number not accepted"), true);
            }
            $this->ctrl->redirect($this, "showContent");
        }
        $this->tpl->setContent($form->getHTML());
    }
}
?>