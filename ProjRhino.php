<?php
namespace Stanford\ProjRhino;

use IU\PHPCap\FileUtil;
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

        //TODO: more comments

        //get the config subsettings for pdf printing
        $current = $this->getSubSettingsForPDFPrint($event_id);

        if (isset($current)) {

            //change request: print after every form
            //1. If trigger form is blank (print after every survey)
            //    a. check if form is in list to be printed
            //    b. print
            //2. If trigger form is not blank (print all surveys  after that survey is printed)
            //    a. send form entered in print-all-form field (this will print a single conjoined form
            //

            //get list of fields to print
            $form_list = $current['forms-to-merge'];

            //get triggering form (blank if at every form, selected if last form)
            $trigger_form = $current['trigger-form-field'];

            //get print-all-form (dummy form on which to attach the merged form
            $print_all_form = $current['print-all-form'];

            if (empty($trigger_form)) {
                //empty trigger form (print after every survey)
                //check if form is in list to be printed
                if (in_array($instrument, $form_list)) {
                    $this->triggerPDFPrint($record, $event_id, array($instrument), $current['compact-display']);
                }
            } else {
                //set trigger form
                //check if trigger matches current instrument
                //send print-all-form to be printed
                if ($instrument == $trigger_form) {
                    $this->emDebug("Just saved last instrument, $instrument, in event $event_id and instance $repeat_instance");
                    if (!empty($print_all_form)) {
                        $form_list = array($print_all_form);
                        $this->triggerPDFPrint($record, $event_id, $form_list, $current['compact-display']);
                    } else {
                        $this->emError("Print all form field is not set in the RHINO EM config. No forms will be printed.");
                    }
                }

            }

        }

    }

    // Hijeck the pdf
	public function redcap_pdf($project_id, $metadata, $data, $instrument, $record, $event_id, $instance = 1) {

        //get the subsettings for this event
        $subsettings = $this->getSubSettingsForPDFPrint($event_id);

        //if subsetting is set, then check the print-all-forms field
        if (isset($subsettings)) {
            $printAllForm = $subsettings['print-all-form'];
        }

    	//keep only fields in formlist
    	//$this->emDebug(func_get_args());

    	if (!empty($printAllForm) && $printAllForm == $instrument) {

    	    //it won't print an empty form so if it's the dmmy form save it
            $save_data = array(
                'record_id'                           => $record,
                'redcap_event_name'                   => REDCap::getEventNames(true, false, $event_id),
                $instrument . '_complete'             => 2
            );
            $status = REDCap::saveData('json', json_encode(array($save_data)));


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
    	return array('metadata'=>$metadata, 'data'=>$data, 'instrument'=>$instrument);
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
        $test = $this->getProjectSetting('test');

        $data = array(
            'record_id' => $record,
            'event_name' => $event_name,
            'instruments' => $form_list,
            'compact_display' => $compact_display);

        if ($test) {
            $this->emDebug("TESTING PRINT WITH DATA", $data);
            $this->testCups($data);
        }

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

    function getSubSettingsForPDFPrint($event_id) {
        $trigger_sub_events = $this->getSubSettings('pdf-events');

        $check_forms = array();
        foreach ($trigger_sub_events as $key => $sub) {
            $setting = array(
                'trigger-form-field' => $sub['trigger-form-field'],
                'forms-to-merge'     => $sub['forms-to-merge'],
                'compact-display'    => $sub['compact-display'],
                'print-all-form'     => $sub['print-all-form']
            );
            $check_forms[$sub['event-field']] = $setting;
        }

        $current = $check_forms[$event_id];

        return $current;
    }


    /*******************************************************************************************************************/
    /* TEST METHODS                                                                                                    */
    /***************************************************************************************************************** */
    function testCups($data) {
        global $Proj;
        $test = $this->getProjectSetting('test');


        try {

                    $record_id = $data['record_id'];
                    $event_name = $data['event_name'];
                    $event_id = REDCap::getEventIdFromUniqueEvent($event_name);
                    $instruments = @$data['instruments'];
                    $compact_display = (bool) filter_var(@$data['compact_display'], FILTER_SANITIZE_NUMBER_INT);

                    if (empty($record_id) || empty($event_name) || empty($instruments)) {
                        throw new Exception("Missing required input(s) - see logs");
                        $this->emDebug("Invalid inputs", $_POST);
                    }

                    foreach ($instruments as $instrument) {
                        $instrument = filter_var($instrument, FILTER_SANITIZE_STRING);
                        $this->emDebug($record_id, $event_name, $instrument);
                        $file = "/tmp/" . $record_id . "_" . $event_name . "_" . $instrument . ".pdf";
                        //$q = $this->exportPdfFileOfInstruments($file, $record_id, $event_id, $instrument, $compact_display);
                        //[ string $record = NULL [, string $instrument = NULL [, int $event_id = NULL [, bool $all_records = FALSE [, int $repeat_instance = 1 [, bool $compact_display = FALSE ]]]]]
                        $q = REDCap::getPDF($record_id, $instrument, $event_id, false, 1, $compact_display );
                        // See if result is error
                        $q = json_decode($q,true);
                        if (json_last_error() == JSON_ERROR_NONE) {
                            // We got json which means an error
                            $errorMsg = empty($q['error']) ? json_encode($q) : $q['error'];
                            throw new \Exception( $errorMsg );
                        } else {
                            // Result was not json meaning likely a PDF - so let's print the file
                            $output=null;
                            $retval=null;
                            //$options = "-o number-up=2 -o sides=two-sided-long-edge ";
                            $options = "-o sides=two-sided-long-edge ";

                            $cmd = 'lp -d ' . $printerName . ' ' . $options . $file;
                            if ($test) {
                                $this->emDebug("TESTMODE: Instrument $instrument with file $file would have been printed to $printerName with this cmd: ".$cmd );
                                $this->emDebug("with this cmd: ".$cmd );
                                $this->emDebug("with this options: ".$options );
                                $this->emDebug("with this file: ",$file );
                            } else {
                                exec( $cmd, $output, $retval);
                                $this->emDebug($cmd, $output, $retval);
                                $this->emDebug($file . " printed to $printerName");
                            }
                            // $result = ['success' => "$instrument printed"];
                            unlink($file);
                        }
                    }
                    $result = ["success" => implode(", ", $instruments) . " printed on $printerName"];



        } catch (\Exception $e) {
            $this->emDebug("EXCEPTION", $e);
            $result = [ "error" => $e->getMessage() ];
        }
    }

    function exportPdfFileOfInstruments($file, $record_id, $event_id, $instrument, $compact_display) {
        $apiUrl = "http://127.0.0.1/api/";
        $apiToken = "E280ABD23924863A2154E33DFDEAF9BD";


        $data = array(
            'token' => 'E280ABD23924863A2154E33DFDEAF9BD',
            'content' => 'pdf',
            'record' => '8',
            'event' => 'followup_3_weeks_arm_1',
            'instrument' => 'medications',
            'returnFormat' => 'json'
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/api/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
        $output = curl_exec($ch);
        //print $output;
        curl_close($ch);

        return $output;
    }
}