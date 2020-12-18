<?php

namespace Stanford\ProjPANS;

/** @var \Stanford\ProjPANS\ProjRhino $module */
use REDCap;
require_once 'vendor/autoload.php';
//include_once 'PDFMerger.php';
//use setasign\Fpdi\Tcpdf\Fpdi;
use \setasign\Fpdi\Fpdi;



//require_once('fpdf/fpdf.php');
//require_once('fpdi2/src/autoload.php');





define('MIN_PDF_STRLEN', 25050);

$refer = $_SERVER['HTTP_REFERER'];
parse_str(parse_url($refer,PHP_URL_QUERY), $parts);

$record = $parts['id'];
$instance = $parts['instance'];
$event_id = $parts['event_id'];

//check that we are in the right event
$target_event = $module->getProjectSetting('event-field');
if ($target_event !== $event_id) die("Unable to verify event id. Please execute this from the visit event of the record for which you want the PDF");
if (!isset($instance)) die("Unable to get instance id. Please check that you were in the record and event that you want to print the PDF. ");
if (!isset($record)) die("Unable to get record id. Please check that you were in the record that you want to print the PDF. ");


//$files = ['/temp/one.pdf', '/temp/two.pdf'];
$files = ['prepans','pans_patient_questionnaire'];

$files = $module->savePDFToFile($files,MIN_PDF_STRLEN );

//$module->emDebug($files);


$pdf = new Fpdi();
//$pdf = new ConcatPdf();
//
//$pdf->setFiles(array('temp/one.pdf', 'temp/two.pdf'));
//$pdf->concat();
//
//$pdf->Output('I', 'concat.pdf');
//exit;

ob_start();
ob_clean();
// iterate over array of files and merge
foreach ($files as $instrument) {

    //$module->emDebug($instrument . ' Exists: '. file_exists($instrument));
    $pageCount = $pdf->setSourceFile($instrument);


    //$pdf_content = REDCap::getPDF($record, $instrument, $event_id, false, $instance, true);
    //$temp_name  = APP_PATH_TEMP . date('YmdHis') .'_' . $instrument.'.pdf';

//    //check if PDF is empty by length??
//    if (strlen($pdf_content)>MIN_PDF_STRLEN) {
//        //$module->emDebug("size of pdf is $instrument ".strlen($pdf_content));
//        file_put_contents($temp_name, $pdf_content);
//        $pageCount = $pdf->setSourceFile($temp_name);
//    }

//    for ($i = 0; $i < $pageCount; $i++) {
//        $tpl = $pdf->importPage($i + 1, '/MediaBox');
//        $pdf->addPage();
//        $pdf->useTemplate($tpl);
//    }

    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $pageId = $pdf->ImportPage($pageNo);
        $s = $pdf->getTemplatesize($pageId);
        $pdf->AddPage($s['orientation'], $s);
        $pdf->useImportedPage($pageId);
    }
}

// output the pdf as a file (http://www.fpdf.org/en/doc/output.htm)
//ob_start();

$out_file = $record.'_'.$instance.'_'.date('YmdHis') .'.pdf';
$module->emDebug($out_file);

ob_clean();
ob_end_clean();
$pdf->Output($out_file,'FD');
//$pdf->Output();
exit;


class ConcatPdf extends Fpdi
{
    public $files = array();

    public function setFiles($files)
    {
        $this->files = $files;
    }

    public function concat()
    {
        foreach($this->files AS $file) {
            $pageCount = $this->setSourceFile($file);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $pageId = $this->ImportPage($pageNo);
                $s = $this->getTemplatesize($pageId);
                $this->AddPage($s['orientation'], $s);
                $this->useImportedPage($pageId);
            }
        }
    }
}

$pdf = new ConcatPdf();
exit;
printPatientForms($record, $event_id, $instance);

function printPatientForms3($record, $event_id, $instance) {
    global $module;

    //echo 'ProjPANS: printing '.$record . ' and instance ' . $instance;

    //get list of forms to print
    $form_list = $module->getProjectSetting('form-field');

    $pdf = new \Clegginabox\PDFMerger\PDFMerger3;
    $pdf = new \PDFMerger\PDFMerger;

$fpdi = new \TCPDI;

    //list of files to unlink after print
    $files = array();


    $instrument='pans_patient_questionnaire';
    //foreach ($form_list as $instrument) {
        ob_start();
        ob_clean();
        $template = REDCap::getPDF($record, $instrument, $event_id, false, $instance, true);
    $size = $fpdi->getTemplateSize($template);
    $orientation = ($size['h'] > $size['w']) ? 'P' : 'L';

    $module->emDEbug("size is $size");
        $temp_name  = APP_PATH_TEMP . date('YmdHis') .'_' . $instrument.'.pdf';

    $fpdi->AddPage($orientation, array($size['w'], $size['h']));
    $fpdi->useTemplate($template);

    $foo = $fpdi->Output($temp_name, 'I');

    if($foo == '')
    {
        return true;
    }
    else
    {
        $module->emDebug($foo);
        throw new exception("Error outputting PDF to .");
        return false;
    }

}

function printPatientForms($record, $event_id, $instance) {
    global $module;

    //echo 'ProjPANS: printing '.$record . ' and instance ' . $instance;

    //get list of forms to print
    $form_list = $module->getProjectSetting('form-field');

    $pdf = new \PDFMerger\PDFMerger;

    //list of files to unlink after print
    $files = array();

    foreach ($form_list as $instrument) {
        ob_start();
        ob_clean();
        $pdf_content = REDCap::getPDF($record, $instrument, $event_id, false, $instance, true);
        $temp_name  = APP_PATH_TEMP . date('YmdHis') .'_' . $instrument.'.pdf';

        //add to the pdf if not empty
        if (strlen($pdf_content)>MIN_PDF_STRLEN) {
            //$module->emDebug("size of pdf is $instrument ".strlen($pdf_content));
            file_put_contents($temp_name, $pdf_content);
            $pdf->addPDF($temp_name, 'all');
            $files[] = $temp_name;
        }
    }

    ob_start(); //clear out out
    ob_clean();  //adobe reader can't read it?

    $pdf->merge('browser', $record.'_'.$instance.'_'.date('YmdHis') .'.pdf');
    ob_end_flush();

    //unlink all the files
    foreach ($files as $file) {
        //$module->emDebug("Unlinking $file");
        unlink($file);
    }

    }

