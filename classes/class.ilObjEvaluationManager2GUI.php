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
require_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/EvaluationManager2/classes/class.ilObjEvaluationManager2Export.php");
 
/**
 * @ilCtrl_isCalledBy ilObjEvaluationManager2GUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI, ilCommonActionDispatcherGUI
 * @ilCtrl_Calls ilObjEvaluationManager2GUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI
 */
class ListGUI extends BaseGUI {

    public function __construct(){
        parent::__construct();
        $this->lng->loadLanguageModule('xevm');
    }

    public function addTermSelectionToForm(ilPropertyFormGUI $form) {

    }
}

class ilObjEvaluationManager2GUI extends ilObjectPluginGUI
{
	/** @var  ilCtrl */
	protected $ctrl;

	/** @var  ilTabsGUI */
	protected $tabs;

    /** @var ilTemplate */
    public $tpl;

    protected \ILIAS\DI\UIServices $ui;

	/**
	 * Initialisation
	 */
	protected function afterConstructor()
	{
        global $DIC;
		$this->ctrl = $DIC->ctrl();
		$this->tabs = $DIC->tabs();
        $this->tpl = $DIC['tpl'];
	}
/*
	public function executeCommand() {
		return parent::executeCommand();
	}
*/
 
	/**
	 * Get type.
	 */
	final function getType(): string
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
            case "addCourse":
            case "deleteCourse":
            case "exportToChosen":
            case "showContent":
            case "showExports":
				$this->checkPermission("write");
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
        //TODO: fred told me to use ilcust
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

		$title = new ilTextInputGUI($this->plugin->txt("title"), "title");
		$title->setRequired(true);
		$form->addItem($title);
 
		$description = new ilTextInputGUI($this->plugin->txt("description"), "description");
		$form->addItem($description);

        $fau_org = new ilTextInputGUI("choose fau org number", "fau_org_number");
        $fau_org->setRequired(true);
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
			if( !$this->fillObject($this->object, $form) ) {
                ilUtil::sendFailure($this->plugin->txt("Org Number not existent"), true);
                $this->ctrl->redirect($this, "editProperties");
                return;
            }
			$this->object->update();
			ilUtil::sendSuccess($this->plugin->txt("update_successful"), true);
			$this->ctrl->redirect($this, "editProperties");
		}
		$this->tpl->setContent($form->getHTML());
	}
 
    protected function showContent() {
        global $DIC;

        $this->tabs->activateTab("content");

        $list_tpl = new ilTemplate("tpl.xevm_content.html",
            true,
            true,
            "Customizing/global/plugins/Services/Repository/RepositoryObject/EvaluationManager2");

        $factory = $DIC->ui()->factory();
        $renderer = $DIC->ui()->renderer();
        $request = $DIC->http()->request();

        /* -- only for 'understanding', can be removed at the end */
        $form = $factory->input()->container()->form()->standard('#', []);
        if ($request->getMethod() == "POST") {
            $form = $form->withRequest($request);
            $result = $form->getData();
            var_dump($result);
            exit();
        } else {
            $result = "No result yet.";
        }
        /* -- */
        $icon_crs = $factory->symbol()->icon()->standard('crs', $this->lng->txt('fau_search_ilias_course'), 'medium');
        $items = array();
        $list = $this->object->getChosenCourseList();
        foreach($list as $element) {
            $item = $factory->item()->standard($element['title'])
                                                    ->withDescription("Kurs-ID: " . $element['course_id'])
                                                    ->withLeadIcon($icon_crs)
                                                    ->withProperties(['Evaluate' => $element['evaluate'] ? 'Ja' : 'Nein'])
                                                    ->withCheckbox('checkbox_name', true);
            array_push($items, $item);
        }

        $group = $DIC->ui()->factory()->item()->group("Chosen Courses", $items);
        $list_tpl->setVariable('EVA2_CONTENT', $DIC->ui()->renderer()->render($group));
        $renderer->render($form);

        $this->tabs->activateTab("contents");
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->object->getTitle());
        $listgui = new ListGUI();
        $listgui->addTermSelectionToForm($form);

        $fau_org = new ilNonEditableValueGUI('FAU Org Nummer'); //read!
        $fau_org->setValue($this->object->getFAUOrgNumber());
        $form->addItem($fau_org);

        $course_obj_ref = new ilNumberInputGUI('Kurs-Nummer', 'course_ref_id');
        $form->addItem($course_obj_ref);

        $form->addCommandButton('', 'Add Course', 'add_course');
        $form->setFormAction($this->ctrl->getFormAction($this, "addCourse"));
        $form->setFormAction($this->ctrl->getFormAction($this, "deleteCourse"));
        $this->tpl->setContent($form->getHTML() . $list_tpl->get());
        return $form;
    }

    protected function showExports() {
        /** @var ilObjEvaluationManager2 $object */
        $this->tabs->activateTab("exports");

        $form = new ilPropertyFormGUI();
        $form->setTitle($this->object->getTitle());

        $i = new ilNonEditableValueGUI($this->plugin->txt("title"));
        $i->setInfo($this->object->getTitle());
        $form->addItem($i);

        $export_type = new ilSelectInputGUI($this->lng->txt('ExportOptions'), 'exportoption');
        $export_type->setOptions(['CSV', 'EVASYS']);
        $form->addItem($export_type);

        $form->addCommandButton('exportToChosen', 'Exportieren', 'exportToChosen');
        $form->setFormAction($this->ctrl->getFormAction($this, "exportToChosen"));

        $this->tpl->setContent($form->getHTML());
        return $form;
    }

    private function exportToChosen() : ilPropertyFormGUI {
        $form = $this->showExports();
        $form->setValuesByPost();
        if($form->checkInput()) {
            $export = new ilObjEvaluationManager2Export($this->object, $form->getInput('exportoption'));
            $result = $export->doExport();
            if ($result) {
                ilUtil::sendSuccess($this->plugin->txt("Export successful"), true);
            } else {
                ilUtil::sendFailure($this->plugin->txt("something went wrong"), true);
            }
            $this->ctrl->redirect($this, "showExport");
        }

        $this->tpl->setContent($form->getHTML());
        //do export with chosen type of export
        return $form;
    }

    /**
	 * @param $object ilObjEvaluationManager2
	 * @param $form ilPropertyFormGUI
	 */
	private function fillObject($object, $form) : bool{
		$object->setTitle($form->getInput('title'));
		$object->setDescription($form->getInput('description'));
        if(!$this->object->isFAUOrgNumberValid($form->getInput('fau_org_number'))) return false;
        $object->setFAUOrgNumber($form->getInput('fau_org_number'));
        return true;
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
	}

    protected function addCourse(){
        $form = $this->showContent();
        $form->setValuesByPost();
        if($form->checkInput()) {
            $result = $this->object->addCourseToObject($form->getInput('course_ref_id'));
            if ($result) {
                ilUtil::sendSuccess($this->plugin->txt("update_successful"), true);
            } else {
                ilUtil::sendFailure($this->plugin->txt("Number not accepted"), true);
            }
            $this->ctrl->redirect($this, "showContent");
        }
        $this->tpl->setContent($form->getHTML());
    }

    protected function deleteCourse() {
        $form = $this->showContent();
        $form->setValuesByPost();
        if($form->checkInput()) {
            var_dump($form->getInput('course_ref_id'));
            exit();
        }
    }
}
?>