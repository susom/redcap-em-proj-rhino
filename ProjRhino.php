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

    //only print if the survey is complete (since the last survey uses "one section per page'
    //public function redcap_save_record($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance) {
    public function redcap_survey_complete($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance) {

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


        //change request: print after every form
        $form_list = $current['form-field'];
        $trigger_form = $current['trigger-form-field'];

        if (in_array($instrument, $form_list)) {


            if (!isset($trigger_form)) {  //trigger form is not set. print all files in list
                //print the current form
                $this->triggerPDFPrint($record, $event_id, array($instrument),$current['compact-display']);
            } else {
                if ($instrument == $trigger_form) {
                    $this->emDebug("Just saved last instrument, $instrument, in event $event_id and instance $repeat_instance");
                    $this->triggerPDFPrint($record, $event_id, $form_list,$current['compact-display']);
                }
            }
        }

    }

    // Hijeck the pdf
	public function redcap_pdf($project_id, $metadata, $data, $instrument, $record, $event_id, $instance = 1) {

    	$printAllForm = $this->getProjectSetting("print-all-form");
    	if (!empty($printAllForm) && $printAllForm == $instrument) {

		    // We need to pull ALL data for this event
		    $params = [
			    'project_id' => $project_id,
			    'records' => $record,
			    'events' => $event_id
		    ];
		    $data = \REDCap::getData($params);

		    $hideFormStatus = $this->getProjectSetting("print-all-hide-form-status");

		    // We need to pull all metadata for fields to include
		    global $Proj;
			$metadata=[];
		    foreach ($Proj->metadata as $field_name => $field) {
			    // Skip field status (could be an option)
			    if ($hideFormStatus && ($field['field_name'] == $field['form_name'] . "_complete")) continue;
			    $field['form_name'] = $printAllForm;
			    $metadata[] = $field;
		    }
	    }
    	return array('metadata'=>$metadata, 'data'=>$data, 'instrument'=>null);
	}


    /*******************************************************************************************************************/
    /* HELPER METHODS                                                                                                    */
    /***************************************************************************************************************** */

    function triggerPDFPrint($record,$event_id, $form_list, $compact_display)
    {

        $event_name = REDCap::getEventNames(true, false,$event_id);

        //call url to trigger print passing in array of instruments ($form_list)
        $this->emDebug("Triggering the PDF print for record $record in event $event_id with compact_display set to $compact_display", $form_list);
        //$url = 'http://7d9adf5c5ede.ngrok.io';
        $url = $this->getProjectSetting('url');

        $data = array(
            'record_id' => $record,
            'event_name' => $event_name,
            'instruments' => $form_list,
            'compact_display' => $compact_display);

        //url-ify the data for the POST
        $data_string = http_build_query($data);

        //open connection
        $ch = curl_init();

        //set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $data_string);

        //So that curl_exec returns the contents of the cURL; rather than echoing it
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);

        //execute post
        $result = curl_exec($ch);
        $status = json_decode($result, true);

        $this->emDebug($status);

        //return { "error": "asdf"}
        //{"error":"Missing required input(s) - see logs"}
        //{"success":"2 printed for 2 on Ricoh3500_ENT"}
        if ($status["error"]) {
            REDCap::logEvent(
                "Error printing PDF. ",  //action
                "Error printing pdf with this error".$status['error'],
                NULL, //sql optional
                $record,//record optional
                $event_id
            );
        } else {
            REDCap::logEvent(
                    "PDF printed.",  //action
                    $status['success'],
                    NULL, //sql optional
                    $record,//record optional
                    $event_id
            );
        }
    }

    function triggerPDFPrint2($record,$event_id, $form_list, $compact_display)
    {

        $record = "2";

        $event_name = REDCap::getEventNames(true, false,$event_id);

        //call url to trigger print passing in array of instruments ($form_list)
        $this->emDebug("Triggering the PDF print for record $record in event $event_id with compact_display set to $compact_display");
        $url = 'http://7d9adf5c5ede.ngrok.io';
        $data = array(
            'record_id' => $record,
            'event_name' => $event_name,
            'instruments' => $form_list,
            'compact_display' => $compact_display);

// use key 'http' even if you send the request to https://...
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            )
        );
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $result2 = file_get_contents($url, false, $context);
        //return { "error": "asdf"}
        //{"error":"Missing required input(s) - see logs"}
        //sucess: {"success":"2 printed for 2 on Ricoh3500_ENT"}
        if ($result === FALSE) {
            $this->emError("There was an error printing the PDF for record $record in event_name $event_name");
            $this->emDebug($result);
        }
        $this->emDebug($result, $context, json_decode($result));

        echo($result);

        var_dump($context);
    }
}