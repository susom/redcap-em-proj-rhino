<?php

namespace Stanford\ProjRhino;

/** @var \Stanford\ProjRhino\ProjRhino $module */


//require_once 'vendor/autoload.php';
include_once 'PDFMerger.php';

define('MIN_PDF_STRLEN', 25050);

$refer = $_SERVER['HTTP_REFERER'];
parse_str(parse_url($refer,PHP_URL_QUERY), $parts);

$record = $parts['id'];
$instance = $parts['instance'];
$event_id = $parts['event_id'];

$subsettings = $module->getSubSettings('pdf-events');
$sub_to_print = null;
$form_list = array();
$compact_display = false;

//find the subsetting for the event which we are in now
foreach($subsettings as $sub => $event_config) {
    //if the event matches then store the subsetting
    if ($event_config['event-field'] == $event_id) {
        //foudn the setting , break
        $sub_to_print = $sub;
        $form_list = $event_config['form-field'];
        if ($event_config['compact-display'] === true) {
            $compact_display = true;
        }
        break;
    }
}


//check that we are in the right event

//if sub_to_print then that means event was not defined
//if ($target_event !== $event_id) die("Unable to verify event id. Please execute this from the visit event of the record for which you want the PDF");
if ($sub_to_print === null) die("Unable to verify event id. Please execute this from the visit event of the record for which you want the PDF or make sure the event has been defined in the EM config");

if (!isset($instance)) {
    //sometimes REDCap does not report instance id for instance 1
    //so as long as event id is target event (visit event id specified in config), if no instance ID, assume it is instance 1.
    $instance = 1;
    //die("Unable to get instance id. Please check that you were in the record and event that you want to print the PDF. ");
}

if (!isset($record)) die("Unable to get record id. Please check that you were in the record that you want to print the PDF. ");

$module->printPatientForms($record, $event_id, $instance, $form_list, $compact_display);
//printPatientForms($record, $event_id, $instance, $form_list, $compact_display);
