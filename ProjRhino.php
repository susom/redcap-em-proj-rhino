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
            $sub = $check_forms[$event_id];
            $form_field  = $sub['form-field'];

            $this->emDebug("Just saved $instrument in event $event_id and instance $repeat_instance");

            $this->printPatientForms($record, $event_id, $repeat_instance,$current['form-field'],$current['compact-display']);

        }
    }

    /*******************************************************************************************************************/
    /* HELPER METHODS                                                                                                    */
    /***************************************************************************************************************** */

    function printPatientForms($record, $event_id, $instance, $form_list, $compact_display)
    {

        $pdf = new \PDFMerger\PDFMerger;

        //list of files to unlink after print
        $files = array();

        foreach ($form_list as $instrument) {
            $this->emDebug("Getting pdf for $instrument");
            $pdf_content = REDCap::getPDF($record, $instrument, $event_id, false, $instance, $compact_display);
            $temp_name = APP_PATH_TEMP . date('YmdHis') . '_' . $instrument . '.pdf';

            //add to the pdf if not empty
            if (strlen($pdf_content) > MIN_PDF_STRLEN) {
                //$module->emDebug("size of pdf is $instrument ".strlen($pdf_content));
                file_put_contents($temp_name, $pdf_content);
                $pdf->addPDF($temp_name, 'all');
                $files[] = $temp_name;
            }
        }


        ob_start(); //clear out out
        $pdf->merge('download', $record .  '_' . date('YmdHis') . '.pdf');
        ob_end_flush();

        //unlink all the files
        foreach ($files as $file) {
            //$module->emDebug("Unlinking $file");
            unlink($file);
        }

    }
}