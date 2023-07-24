<?php

class ilObjEvaluationManager2Export{
    /**
     * EvaluationManager2 Object to read saved course values
     * @var $ilObject
     */
    protected $object;

    /**
     * Array of courses which are to be evaluated
     * @var array()
     */
    protected $export_value_array = array("Evaluate" => 0 , "Key"  => '', "Type" => 0,
                               "Event-ID" => 0, "Course-ID" => 0,
                               "Event" => '', "Course" => '', "Salutation" => '', "Title" => '',
                               "Firstname"  => '', "Lastname"  => '',
                               "EMail" => '', "Link" => '', "Participants" => ''
                              );

    protected $export_courses = array();

    protected bool $isEvaSys;

    /**
     * set local object
     * @param ilObjEvaluationManager2 $evaman2_object
     * @param bool $isEvaSys
     */

    function __construct(ilObjEvaluationManager2 $evaman2_object, bool $isEvaSys) {
        $this->object = $evaman2_object;
        $this->isEvaSys = $isEvaSys;
    }

    /**
     * @return bool
     */
    public function doExport()
    {
        $this->setCourses();
        if ($this->isEvaSys) {
            $csvOutput = '';
            $seperator = '*';
            $lineBreak = '<br>';
            $courseList = $this->getCourses();

            $header = 'Eva-Key-EventID-CourseID-Event-Course-Salutation-Title-FirstName-LastName-EMail-Link-Participant'.$lineBreak;
            $csvOutput .= $header;
            foreach ($courseList as $course) {
                $csvOutput .= $course['Evaluate'].$seperator;
                $csvOutput .= $course['Key'].$seperator;
                $csvOutput .= $course['Event-ID'].$seperator;
                $csvOutput .= $course['Course-ID'].$seperator;
                $csvOutput .= $course['Event'].$seperator;
                $csvOutput .= $course['Course'].$seperator;
                $csvOutput .= $course['Salutation'].$seperator;
                $csvOutput .= $course['Title'].$seperator;
                $csvOutput .= $course['Firstname'].$seperator;
                $csvOutput .= $course['Lastname'].$seperator;
                $csvOutput .= $course['EMail'].$seperator;
                $csvOutput .= $course['Link'].$seperator;
                $csvOutput .= $course['Participants'].$seperator;
                $csvOutput .= $lineBreak;
            }
            var_dump($csvOutput);
            exit();

        } else {
            //build file and save csv
        }

        return true;
    }

    public function doImport(){
        var_dump("Import started");
        exit();
    }

    /**
     * set courses to course-array for export
     */
    protected function setCourses() {
        $this->export_courses = $this->getCourses();
    }

    /**
     * get courses from object and get list from database
     */
    protected function getCourses() : array {

        global $ilDB;
        $course_informations = array();
        $set = $ilDB->query("SELECT sc.term_year, sc.term_type_id, eo.event_id, sc.course_id, /* all infos above to key-generation */
               se.eventtype, se.title as event_title, sc.title as course_title, ud.gender, ud.title as doc_title, ud.firstname, ud.lastname, ud.email, sc.ilias_obj_id, xc.evaluate
        FROM fau_study_event_orgs eo JOIN fau_study_courses sc
                                     JOIN rep_robj_xevm_courses xc
                                     JOIN fau_study_events se
                                     JOIN fau_study_course_resps scr
                                     JOIN fau_user_persons up
                                     JOIN usr_data ud
        ON eo.event_id = sc.event_id
            AND xc.course_id = sc.course_id
            AND eo.event_id = se.event_id
            AND xc.course_id = scr.course_id
            AND up.person_id = scr.person_id
            AND up.user_id = ud.usr_id
        WHERE xc.obj_id = ".$this->object->getId()." 
        AND sc.ilias_obj_id IS NOT NULL 
        AND eo.fauorg_nr = ".$this->object->getFAUOrgNumber()."
        GROUP BY sc.course_id");

        
        $courses_list = $ilDB->fetchAll($set);
        foreach ($courses_list as $element) {
            $temp_value_array = $this->export_value_array;
            $temp_value_array['Evaluate'] = $element['evaluate'];
            $temp_value_array['Key'] = $this->buildKey($this->isEvaSys,"Prefix",
                                                       $element['term_year'], $element['term_type_id'],
                                                       $element['event_id'], $element['course_id']);
            $temp_value_array['Event-ID'] = $element['event_id'];
            $temp_value_array['Course-ID'] = $element['course_id'];
            $temp_value_array['Event'] = $element['event_title']; // !!!! same name of field
            $temp_value_array['Course'] = $element['course_title']; // !!!! same name of field
            if($element['gender'] == 'm') $temp_value_array['Salutation'] = 'Herr';
            if($element['gender'] == 'f') $temp_value_array['Salutation'] = 'Frau';
            if($element['gender'] == 'n') $temp_value_array['Salutation'] = '';
            $temp_value_array['Title'] = $element['doc_title']; // !!!! same name of field
            $temp_value_array['Firstname'] = $element['firstname'];
            $temp_value_array['Lastname'] = $element['lastname'];
            $temp_value_array['EMail'] = $element['email'];
            $temp_value_array['Link'] = $this->buildLink($element['course_id']);
            $temp_value_array['Participants'] = $this->getParticipantCount($element['ilias_obj_id']);
            array_push($course_informations, $temp_value_array);
        }

        return $course_informations;
    }

    /**
     * collect values for export_key
     */
    protected function buildKey(bool $is_evasys, string $prefix, string $term_year, string $term_id, $event_id, $course_id) : string {
        $bridge = "_";
        $bridgeEvaSys = "-";
        if($is_evasys) return $term_year.$term_id.$bridgeEvaSys.$course_id;
        return $prefix.$bridge.$term_year.$term_id.$bridge.$event_id.$bridge.$course_id;
    }

    /**
     * build direct link in studon for use in export
     * @param string $course_id
     * @return string
     */
    protected function buildLink(string $course_id) : string{
        return "https://www.studon.fau.de/campo/course/".$course_id;
    }

    /**
     * get Amount of Participants in Course
     * @param string $ilias_obj_id
     * @return string
     */
    protected function getParticipantCount(string $ilias_obj_id) : string {
        global $ilDB;
        $set = $ilDB->query("       
        SELECT COUNT(ud.firstname) FROM fau_user_members fum
              JOIN fau_study_courses fsc
              JOIN usr_data ud
              ON fum.obj_id = fsc.ilias_obj_id AND ud.usr_id = fum.user_id
              WHERE obj_id = ".$ilias_obj_id." AND event_responsible = 0 AND course_responsible = 0 AND instructor = 0 AND individual_instructor = 0");
        $participantCount = $ilDB->fetchAll($set);
        return $participantCount[0]['COUNT(ud.firstname)'];;
    }
}