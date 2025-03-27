<?php

use FAU\BaseGUI;
use FAU\Study\Data\SearchCondition;
use FAU\Study\Search;
use ILIAS\UI\Component\Item\Group;
use ILIAS\UI\Component\ViewControl\Pagination;
use FAU\Study\Data\ImportId;

include_once("./Services/Repository/PluginSlot/class.ilObjectPluginGUI.php");
require_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
require_once("./Services/Form/classes/class.ilTextInputGUI.php");
require_once("./Services/Form/classes/class.ilCheckboxInputGUI.php");
require_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/EvaluationManager2/classes/class.ilEvaluationManager2Plugin.php");
require_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/EvaluationManager2/classes/class.ilObjEvaluationManager2Export.php");
 
/**
 * @ilCtrl_isCalledBy ilObjEvaluationManager2GUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI, ilCommonActionDispatcherGUI
 * @ilCtrl_Calls ilObjEvaluationManager2GUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI
 */

class ilObjEvaluationManager2GUI extends ilObjectPluginGUI
{
	protected ilCtrl $ctrl;

	protected ilTabsGUI $tabs;
    protected ilAccessHandler $access;

    public ilGlobalTemplateInterface $tpl;

    protected \ILIAS\DI\UIServices $ui;


	/**
	 * init of class-members
	 */
	protected function afterConstructor(): void
	{
        global $DIC;
		$this->ctrl = $DIC->ctrl();
		$this->tabs = $DIC->tabs();
        $this->tpl = $DIC['tpl'];
        $this->lng->loadLanguageModule('xevm');
        $this->access = $DIC->access();
	}

	/**
	 * Get type of plugin
	 */
	final function getType(): string
    {
		return ilEvaluationManager2Plugin::ID;
	}
 
	/**
	 * Handles all commmands of this class, centralizes permission checks
	 */
	function performCommand($cmd): void
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
            default:
                $this->showContent();
		}
	}

 
	/**
	 * After object has been created -> jump to this command
	 */
	function getAfterCreationCmd(): string
	{
		return "editProperties";
	}
 
	/**
	 * Get standard command
	 */
	function getStandardCmd(): string
	{
		return "showContent";
	}
 
	/**
	 * Set tabs
	 */
	function setTabs(): void
    {

        // tab for the "show content" command
        if ($this->access->checkAccess("read", "", $this->object->getRefId())) {
            //none cause users with only read rights should now open this
        }

		// standard info screen tab

		// a "properties" tab
        //TODO: only allow Administrator Roles to access Properties
        //TODO: fred told me to use ilcust
		if ($this->access->checkAccess("write", "", $this->object->getRefId()))
		{
            $this->tabs->addTab("contents", $this->txt("contents"), $this->ctrl->getLinkTarget($this, "showContent"));
            $this->tabs->addTab("exports", $this->txt("exports"), $this->ctrl->getLinkTarget($this, "showExports"));
			$this->tabs->addTab("properties", $this->txt("properties"), $this->ctrl->getLinkTarget($this, "editProperties"));
		}

        $this->addInfoTab();
		$this->addPermissionTab();
		//$this->activateTab();
	}
 
	/**
	 * Edit Properties
	 */
	protected function editProperties(): void
	{
		$this->tabs->activateTab("properties");
		$form = $this->initPropertiesForm();
		$this->addValuesToForm($form);
		$this->tpl->setContent($form->getHTML());
	}
 
	/**
     * init Form for Properties
	 * @return ilPropertyFormGUI
	 */
	protected function initPropertiesForm(): ilPropertyFormGUI {
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
     * setup Fields for Property
	 * @param $form ilPropertyFormGUI
	 */
	protected function addValuesToForm(&$form): void {
		$form->setValuesByArray([
			"title" => $this->object->getTitle(),
			"description" => $this->object->getDescription(),
            "fau_org_number" => $this->object->getFAUOrgNumber()
        ]);
	}
 
	/**
	 * save Properties
	 */
	protected function saveProperties(): void {
		$form = $this->initPropertiesForm();
		$form->setValuesByPost();
        if( !$this->fillProperties($this->object, $form) ) {
            $this->tpl->setOnScreenMessage("failure", $this->lng->txt("Org Number not existent"), true);
            $this->ctrl->redirect($this, "editProperties");
            return;
        }
        $this->object->update();
        $this->tpl->setOnScreenMessage("success", $this->lng->txt("update_successful"), true);            
			$this->ctrl->redirect($this, "editProperties");
		$this->tpl->setContent($form->getHTML());
	}

    /**
     * show Content of Course-List with fields for managing courses
     * @return ilPropertyFormGUI|void
     */
    protected function showContent(): ilPropertyFormGUI {
        global $DIC;

        $this->tabs->activateTab("contents");

        $list_tpl = new ilTemplate("tpl.xevm_content.html",
            true,
            true,
            "Customizing/global/plugins/Services/Repository/RepositoryObject/EvaluationManager2");

        $factory = $DIC->ui()->factory();
        $renderer = $DIC->ui()->renderer();
        $request = $DIC->http()->request();
            
        /* -- */

        /* -- only for 'understanding', can be removed at the end */
        
        #$form = $factory->input()->container()->form()->standard('#', []);

        $form = new ilPropertyFormGUI();
        $form->setTitle($this->object->getTitle());
        $form->checkInput();
        if ($request->getMethod() == "POST") {
            #$form = $form->withRequest($request);
            $result = $form->getInput('course_ref_id');
            var_dump($result);
            #exit();
        } else {
            $result = "No result yet.";
        }

        $icon_crs = $factory->symbol()->icon()->standard('crs', $this->lng->txt('fau_search_ilias_course'), 'medium');
        $icon_missing = $factory->symbol()->icon()->standard('pecrs', $this->lng->txt('fau_search_ilias_course_not'), 'medium');
        $items = [];
        $list = $this->object->getChosenCourseList();
        foreach($list as $element) {
            $item = $factory->item()->standard($element['title'])
                                                    ->withDescription("Beschreibung: " . $element['description'])
                                                    ->withLeadIcon($icon_crs)
                                                    ->withProperties(['Evaluate' => $element['evaluate'] ? 'Ja' : 'Nein'])
                                                    ->withCheckbox('checkbox_name', true);
            array_push($items, $item);
        }

        $this->tabs->activateTab("contents");

        $group = $DIC->ui()->factory()->item()->group("Chosen Courses", $items);
        $list_tpl->setVariable('EVA2_CONTENT', $DIC->ui()->renderer()->render($group));
        //$renderer->render($form);

        $fau_org = new ilNonEditableValueGUI('FAU Org Nummer'); //read!
        $fau_org->setValue($this->object->getFAUOrgNumber());
        $form->addItem($fau_org);

        $course_obj_ref = new ilNumberInputGUI('Kurs-Nummer', 'course_ref_id');
        $form->addItem($course_obj_ref);

        $form->addCommandButton("addCourse", "Add Course");
        $form->addCommandButton("deleteCourse", "Delete Course");
        $form->setFormAction($this->ctrl->getFormAction($this));        
        $this->tpl->setContent($form->getHTML() . $list_tpl->get());
        return $form;
    }

    /**
     * show Export GUI
     * @return ilPropertyFormGUI
     */
    protected function showExports(): ilPropertyFormGUI {
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
        $form->setFormAction($this->ctrl->getFormAction($this));         

        $this->tpl->setContent($form->getHTML());
        return $form;
    }

    /**
     * function to call export Course-List as CSV or EVASYS and show success or failure
     * @return ilPropertyFormGUI
     */
    private function exportToChosen() : ilPropertyFormGUI {
        $form = $this->showExports();
        $form->setValuesByPost();
        if($form->checkInput()) {
            $export = new ilObjEvaluationManager2Export($this->object, $form->getInput('exportoption'));
            $result = $export->doExport();
            if ($result) {
                #ilUtil::sendSuccess($this->plugin->txt("Export successful"), true);
                $this->tpl->setOnScreenMessage("success", $this->lng->txt("Export successful"), true);                 

                
            } else {
                #ilUtil::sendFailure($this->plugin->txt("something went wrong"), true);
                $this->tpl->setOnScreenMessage("failure", $this->lng->txt("something went wrong in export"), true);                 
            }
            $this->ctrl->redirect($this, "showExport");
        }

        $this->tpl->setContent($form->getHTML());
        return $form;
    }

    /**
     * fill Property GUI with values
	 * @param $object ilObjEvaluationManager2
	 * @param $form ilPropertyFormGUI
	 */
	private function fillProperties($object, $form) : bool{
		$object->setTitle($form->getInput('title'));
		$object->setDescription($form->getInput('description'));
        if(!$this->object->isFAUOrgNumberValid($form->getInput('fau_org_number'))) return false;
        $object->setFAUOrgNumber($form->getInput('fau_org_number'));
        return true;
	}

    /**
     * add Course to Database and check if successfull
     */
    protected function addCourse(): void{  
        $form = $this->showContent();    
        $form->setValuesByPost();
        var_dump('test');

        $result = $this->object->addCourseToObject($form->getInput('course_ref_id'));
        
        if ($result) {
            $this->tpl->setOnScreenMessage("success", $this->lng->txt("update_successful"), true);                 
        } else {
            $this->tpl->setOnScreenMessage("failure", $this->lng->txt("Number not accepted"), true);                 
        }
        $this->ctrl->redirect($this, "showContent");
            
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * delete Course out of list
     */
    protected function deleteCourse(): void {
        $form = $this->showContent();
        $form->setValuesByPost();
        var_dump($form->getInput('course_ref_id'));
        exit();
    }
}
?>