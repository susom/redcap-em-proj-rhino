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

    //change: since using the PDF save field, convert back to redcap_save_record
    //public function redcap_save_record($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance=1) {
    public function redcap_save_record( $project_id, $record, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance = 1) {
    //public function redcap_survey_complete($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance) {
        //$this->emDebug("================== ".__FUNCTION__. "  : " . $instrument);
        //TODO: more comments

        //check if it is the PDF form that has been saved?
        $trigger_form = $this->getProjectSetting('trigger-form', $project_id);
        if ($trigger_form != $instrument) return;

        $this->emDebug("Just saved triggering instrument, $instrument, in event $event_id and instance $repeat_instance");

        $pdf_field = $this->getProjectSetting('pdf-field', $project_id);

        if (!empty($pdf_field)) {
            $this->triggerPDFPrint($record, $event_id, $pdf_field);
        }

    }

    /*******************************************************************************************************************/
    /* HELPER METHODS                                                                                                    */
    /***************************************************************************************************************** */

    /**
     * trigger a PDF print in an external printer using the cups print EM
     * Here are the expected POST parameters:
     *     "action": "print_file_field",
     *     "record_id": "123",
     *     "event_name": "event_1_arm_1",
     *     "field_name": "file_upload_field"
     *
     * @param $record
     * @param $event_id
     * @param $form_list
     * @param $compact_display
     */
    function triggerPDFPrint($record,$event_id, $pdf_field)
    {

        $event_name = REDCap::getEventNames(true, false,$event_id);

        //call url to trigger print passing in array of instruments ($form_list)
        $this->emDebug("Triggering the PDF print for record $record in event $event_id", $pdf_field);

        $url = $this->getProjectSetting('url'); //cups relay URL
        $test = $this->getProjectSetting('test');

        $data = array(
            'action'    => "print_file_field",
            'record_id' => $record,
            'event_name' => $event_name,
            'field_name' => $pdf_field);

        //url-ify the data for the POST
        $data_string = http_build_query($data);

/**
        //open connection
        $curl = curl_init();

        //set the url, number of POST vars, POST data
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data
        ));

        //execute post
        $result = curl_exec($curl);
        $status = json_decode($result, true);

        curl_close($curl);

        $this->emDebug($status);
*/

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
        $this->emDebug($status . " from " . $url);
        curl_close($ch);


        //{"success":"2 printed for 2 on Ricoh3500_ENT"}
        if ($status["error"] || ($status == null)) {
            $this->emError($status);

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

    function getSubSettingsForPDFPrint($event_id) {
        $trigger_sub_events = $this->getSubSettings('pdf-events');

        $check_forms = array();
        foreach ($trigger_sub_events as $key => $sub) {
            $setting = array(
                'trigger-form-field' => $sub['trigger-form-field'],
                'pdf-field'          => $sub['pdf-field']
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
        $printerName = "TestPRINTER";

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