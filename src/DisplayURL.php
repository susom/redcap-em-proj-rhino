<?php

namespace Stanford\ProjPANS;

use REDCap;

/** @var \Stanford\ProjPANS\ProjRhino $module */

$url = $module->getUrl('../PrintPDF.php', true, true);
echo "<br><br>This is the PDF Merger Link: <br>".$url;

