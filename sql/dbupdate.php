<#1>
<?php
$fields_courses = array(
    'evaluate' => array(
        'type' => 'integer',
        'length' => 1,
        'notnull' => false
    ),
    'course_id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    ),
    'obj_id' => array(
    'type' => 'integer',
    'length' => 4,
    'notnull' => true
    )
);

$fields_orgs = array(
    'obj_id' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    ),
    'fau_org_number' => array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => true
    )
);
$ilDB->createTable("rep_robj_xevm_courses", $fields_courses);
$ilDB->createTable("rep_robj_xevm_orgs", $fields_orgs);
$ilDB->addPrimaryKey("rep_robj_xevm_orgs", array("obj_id"));
$ilDB->addPrimaryKey("rep_robj_xevm_courses", array("course_id"));
?>