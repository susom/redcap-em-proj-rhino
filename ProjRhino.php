<?php
namespace Stanford\ProjRhino;

use REDCap;

include_once("emLoggerTrait.php");
include_once 'PDFMerger.php';


/**
 */
class ProjRhino extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;


    /*******************************************************************************************************************/
    /* HOOK METHODS                                                                                                    */
    /***************************************************************************************************************** */

    public function redcap_save_record($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance) {

        $trigger_sub_events = $this->getSubSettings('pdf-events');

        $check_forms = array();
        foreach ($trigger_sub_events as $key => $sub) {
            $setting = array(
                'trigger-form-field' => $sub['trigger-form-field'],
                'form-field'         => $sub['form-field'],
                'compact-display' => $sub['compact-display'],
            );
            $check_forms[$sub['event-field']] = $setting;
        }

        $current = $check_forms[$event_id];
        //on save of admin_review form, trigger email to admin to verify the training
        //or Reset to new event instance
        if ($instrument == $current['trigger-form-field']) {

            $this->emDebug("Just saved last instrument, $instrument, in event $event_id and instance $repeat_instance");


            $this->triggerPDFPrint($record, $event_id, $current['form-field'],$current['compact-display']);

        }
    }

    /*******************************************************************************************************************/
    /* HELPER METHODS                                                                                                    */
    /***************************************************************************************************************** */

    function triggerPDFPrint($record,$event_id, $form_list, $compact_display)
    {

        //call url to trigger print passing in array of instruments ($form_list)
        $this->emDebug("Triggering the PDF print for record $record in event $event_id with compact_display set to $compact_display");
    }
}