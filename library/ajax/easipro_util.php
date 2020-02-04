<?php
/**
 * easipro_util.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Shiqiang Tao <shiqiang.tao@uky.edu>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2018 Shiqiang Tao <shiqiang.tao@uky.edu>
 * @copyright Copyright (c) 2020 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Will start the (patient) portal OpenEMR session/cookie
//  (in case the request is from the patient portal; note it will get destroyed if request is not from patient portal).
require_once(dirname(__FILE__) . "/../../src/Common/Session/SessionUtil.php");
OpenEMR\Common\Session\SessionUtil::portalSessionStart();

if (isset($_SESSION['pid']) && isset($_SESSION['patient_portal_onsite_two'])) {
    // request is from patient portal
    $pid = $_SESSION['pid'];
    $ignoreAuth = true;
} else {
    // request is from openemr core
    OpenEMR\Common\Session\SessionUtil::portalSessionCookieDestroy();
    $ignoreAuth = false;
}

require_once(dirname(__FILE__) . "/../../interface/globals.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Easipro\Easipro;

// verify csrf
if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
    CsrfUtils::csrfNotVerified();
}

// process requested function
if ($_POST['function'] == 'request_assessment') {
    // Request assessment
    $expiration = date_format(date_create_from_format('n/j/Y g:i:s A', $_POST['expiration']), 'Y-m-d H:i:s');
    Easipro::requestAssessment($pid, $_SESSION['authUserID'], $_POST['formOID'], $_POST['formName'], $expiration, $_POST['assessmentOID'], $_POST['status']);
} else if ($_POST['function'] == 'start_assessment') {
    // Start assessment
    header('Content-Type: application/json');
    $easipro = new Easipro();
    echo $easipro->startAssessment($_POST['assessmentOID']);
} else if ($_POST['function'] == 'select_response') {
    // Render screen during assessment
    header('Content-Type: application/json');
    $easipro = new Easipro();
    echo $easipro->selectResponse($_POST['assessmentOID'], $_POST['ItemResponseOID'], $_POST['Response']);
} else if ($_POST['function'] == 'collect_results') {
    // Collect results after completing assessment
    header('Content-Type: application/json');
    $easipro = new Easipro();
    echo $easipro->collectResults($_POST['assessmentOID']);
} else if ($_POST['function'] == 'record_result') {
    // Record result of assessment
    Easipro::recordResult($pid, $_POST['score'], $_POST['assessmentOID'], $_POST['stdErr']);
} else if ($_POST['function'] == 'list_forms') {
    // Provide list of forms
    header('Content-Type: application/json');
    $easipro = new Easipro();
    echo $easipro->listForms();
} else if ($_POST['function'] == 'order_form') {
    // Order form
    header('Content-Type: application/json');
    $easipro = new Easipro();
    echo $easipro->orderForm($_POST['formOID']);
}
