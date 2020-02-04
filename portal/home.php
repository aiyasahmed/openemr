<?php
/**
 * Patient Portal Home
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Shiqiang Tao <shiqiang.tao@uky.edu>
 * @copyright Copyright (c) 2016-2019 Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2019-2020 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2020 Shiqiang Tao <shiqiang.tao@uky.edu>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */


require_once("verify_session.php");
require_once("$srcdir/patient.inc");
require_once("$srcdir/options.inc.php");
require_once("lib/portal_mail.inc");
require_once(dirname(__FILE__)."/../library/appointments.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;

if ($_SESSION['register'] === true) {
    require_once(dirname(__FILE__) . "/../src/Common/Session/SessionUtil.php");
    OpenEMR\Common\Session\SessionUtil::portalSessionCookieDestroy();
    header('Location: ' . $landingpage . '&w');
    exit();
}

if (!isset($_SESSION['portal_init'])) {
    $_SESSION['portal_init'] = true;
}

$whereto = 'profilecard';
if (isset($_SESSION['whereto'])) {
    $whereto = $_SESSION['whereto'];
}

$user = isset($_SESSION['sessionUser']) ? $_SESSION['sessionUser'] : 'portal user';
$result = getPatientData($pid);

$msgs = getPortalPatientNotes($_SESSION['portal_username']);
$msgcnt = count($msgs);
$newcnt = 0;
foreach ($msgs as $i) {
    if ($i['message_status'] == 'New') {
        $newcnt += 1;
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('OpenEMR Portal'); ?> | <?php echo xlt('Home'); ?></title>
    <meta name="description" content="Developed By sjpadgett@gmail.com" />

    <script type="text/javascript">
        var tab_mode = true;

        function restoreSession() {
            //dummy functions so the dlgopen function will work in the patient portal
            return true;
        }

        var isPortal = 1;
    </script>
    <?php
    echo "<script>var cpid=" . js_escape($pid) . ";var cuser=" . js_escape($user) . ";var webRoot=" . js_escape($GLOBALS['web_root']) . ";var ptName=" . js_escape($_SESSION['ptName']) . ";var webroot_url = webRoot;</script>";

    Header::setupHeader(['no_main-theme', 'datetime-picker', 'patientportal-style']); ?>

    <script type="text/javascript" src="../interface/main/tabs/js/dialog_utils.js?v=<?php echo $v_js_includes; ?>"></script>
    <link href="<?php echo $GLOBALS['web_root']; ?>/portal/sign/css/signer_modal.css?v=<?php echo $v_js_includes; ?>" rel="stylesheet" type="text/css" />

    <script src="<?php echo $GLOBALS['web_root']; ?>/portal/sign/assets/signature_pad.umd.js?v=<?php echo $v_js_includes; ?>" type="text/javascript"></script>
    <script src="<?php echo $GLOBALS['web_root']; ?>/portal/sign/assets/signer_api.js?v=<?php echo $v_js_includes; ?>" type="text/javascript"></script>

    <?php if ($GLOBALS['payment_gateway'] == 'Stripe') { ?>
        <script type="text/javascript" src="https://js.stripe.com/v3/"></script>
    <?php } ?>
    <?php if ($GLOBALS['payment_gateway'] == 'AuthorizeNet') {
        // Must be loaded from their server
        $script = "https://jstest.authorize.net/v1/Accept.js"; // test script
        if ($GLOBALS['gateway_mode_production']) {
            $script = "https://js.authorize.net/v1/Accept.js"; // Production script
        } ?>
        <script type="text/javascript" src="<?php echo $script; ?>" charset="utf-8"></script>
    <?php } ?>

    <script type="text/javascript">
        $(function() {
            $("#profilereport").load("get_profile.php", {}, function () {
                $("table").addClass("table");
                $(".demographics td").removeClass("label");
                $(".demographics td").addClass("bold");
                $(".insurance table").addClass("table-sm table-striped");
                $("#editDems").click(function () {
                    showProfileModal()
                });
            });

            $("#medicationlist").load("./get_medications.php", {}, function () {
            });
            $("#labresults").load("./get_lab_results.php", {}, function () {
            });
            $("#amendmentslist").load("./get_amendments.php", {}, function () {
            });
            $("#problemslist").load("./get_problems.php", {}, function () {
            });
            $("#allergylist").load("./get_allergies.php", {}, function () {
            });
            $("#reports").load("./report/portal_patient_report.php?pid='<?php echo attr_url($pid) ?>'", {}, function () {
            });

            <?php if ($GLOBALS['portal_two_payments']) { ?>
            $("#payment").load("./portal_payment.php", {}, function () {
            });
            <?php } ?>

            <?php if ($GLOBALS['easipro_enable']) { ?>
                $("#pro").load("./get_pro.php", {}, function () {});
            <?php } ?>

            $(".generateDoc_download").click(function () {
                $("#doc_form").submit();
            });

            function showProfileModal() {
                var title = <?php echo xlj('Demographics Legend Red: Charted Values. Blue: Patient Edits'); ?> +' ';

                var params = {
                    buttons: [
                        {text: <?php echo xlj('Help'); ?>, close: false, style: 'info', id: 'formHelp'},
                        {text: <?php echo xlj('Cancel'); ?>, close: true, style: 'default'},
                        {text: <?php echo xlj('Revert Edits'); ?>, close: false, style: 'danger', id: 'replaceAllButton'},
                        {text: <?php echo xlj('Send for Review'); ?>, close: false, style: 'success', id: 'donePatientButton'}
                    ],
                    allowDrag: false,
                    onClosed: 'reload',
                    type: 'GET',
                    url: webRoot + '/portal/patient/patientdata?pid=' + encodeURIComponent(cpid) + '&user=' + encodeURIComponent(cuser)
                };
                dlgopen('', '', 'modal-xl', 500, '', title, params);
            }

            function saveProfile() {
                page.updateModel();
            }

            var gowhere = '#' + <?php echo js_escape($whereto); ?>;
            $(gowhere).collapse('show');

            $('#cardgroup').on('show.bs.collapse', '.collapse', function () {
                $('#cardgroup').find('.collapse.show').collapse('hide');
            });
            $("[data-toggle='pill']").on("click", function (e) {
                e.preventDefault();
                // don't toggle if already active.
                if ($(this).hasClass('active')) {
                    return false;
                }
                $(".nav-item").removeClass("active");
                let canHide = $(".navbar-toggler-icon").is(":visible");
                if (canHide) {
                    $("[data-toggle='offcanvas']").click();
                }
            });
            //Enable sidebar toggle
            $("[data-toggle='offcanvas']").click(function (e) {
                e.preventDefault();
                //If window is small enough, enable sidebar push menu
                if ($(window).width() <= 992) {
                    $('.row-offcanvas').toggleClass('active');
                    $('.left-side').removeClass("collapse-left");
                    $(".right-side").removeClass("strech");
                    $('.row-offcanvas').toggleClass("relative");
                } else {
                    //Else, enable content streching
                    $('.left-side').toggleClass("collapse-left");
                    $(".right-side").toggleClass("strech");
                }
            });
            $(function () {
                $('#popwait').hide();
                $('#callccda').click(function () {
                    $('#popwait').show();
                })
            });
        });

        function editAppointment(mode, deid) {
            let mdata = {};
            let title = '';
            if (mode === 'add') {
                title = <?php echo xlj('Request New Appointment'); ?>;
                mdata = {pid: deid};
            } else if (mode === 'recurring'){
                let msg = <?php echo xlj("A Recurring Appointment. Please contact your appointment desk for any changes."); ?>;
                signerAlertMsg(msg, 10000);
                return false;
            } else {
                title = <?php echo xlj('Edit Appointment'); ?>;
                mdata = {eid: deid};
            }
            let params = {
                dialogId: 'editpop',
                buttons: [
                    {text: <?php echo xlj('Cancel'); ?>, close: true, style: 'default'}
                ],
                allowDrag: false,
                type: 'GET',
                dataType: 'text',
                url: './add_edit_event_user.php',
                data: mdata
            };

            dlgopen('', 'apptModal', 750, 400, '', title, params);
        }

        function changeCredentials(e) {
            title = <?php echo xlj('Please Enter New Credentials'); ?>;
            dlgopen("./account/index_reset.php", '', 600, 360, null, title, {});
        }

        <?php if ($GLOBALS['easipro_enable']) { ?>
            function writeResult(score, stdErr, assessmentOID){
                $.ajax({
                    url: '../library/ajax/easipro_util.php',
                    data: {
                        'csrf_token_form': <?php echo js_escape(CsrfUtils::collectCsrfToken()); ?>,
                        'function': 'record_result',
                        'score': score,
                        'stdErr':stdErr,
                        'assessmentOID': assessmentOID
                    },
                    type: 'POST',
                    dataType: 'script'
                    // success: function(data){
                    //     alert('data written');
                    // }
                });
            }

            function selectResponse(obj, assessmentOID){
                $.ajax({
                    url: '../library/ajax/easipro_util.php',
                    type: "POST",
                    data: {
                        'csrf_token_form': <?php echo js_escape(CsrfUtils::collectCsrfToken()); ?>,
                        'function': 'select_response',
                        'assessmentOID': assessmentOID,
                        'ItemResponseOID': obj.name,
                        'Response': + obj.id
                    },
                    dataType: "json",
                    success: function(data) {
                        if (data.DateFinished !='') {
                            document.getElementById("Content").innerHTML = "<?php xla('You have finished the assessment.'); ?>" + "<br /> " + "<?php echo xla('Thank you'); ?>";
                            document.getElementById("asst_"+assessmentOID).innerHTML = "<i class='fa fa-check-circle'></i>";
                            document.getElementById("asst_status_"+assessmentOID).innerHTML = "completed";
                            $.ajax({
                                url: '../library/ajax/easipro_util.php',
                                type: "POST",
                                data: {
                                    'csrf_token_form': <?php echo js_escape(CsrfUtils::collectCsrfToken()); ?>,
                                    'function': 'collect_results',
                                    'assessmentOID': assessmentOID
                                },
                                dataType: "json",
                                success: function(data){
                                    writeResult(data.Items[0].Theta, data.Items[0].StdError, assessmentOID);
                                }
                            });
                            return
                        }
                        var screen ="";
                        for (var j=0; j < data.Items[0].Elements.length; j++) {
                            if (typeof(data.Items[0].Elements[j].Map) == 'undefined') {
                                screen = screen +"<div style=\'height: 30px\' >" +  data.Items[0].Elements[j].Description + "</div>"
                            } else {
                                for (var k=0; k < data.Items[0].Elements[j].Map.length; k++) {
                                    screen = screen + "<div style=\'height: 50px\' ><input type=\'button\' class='btn-submit' id=\'" + data.Items[0].Elements[j].Map[k].Value + "\' name=\'" + data.Items[0].Elements[j].Map[k].ItemResponseOID + "\' value=\'" + data.Items[0].Elements[j].Map[k].Description +  "\' onclick=selectResponse(this,'"+assessmentOID+"') />"    + "</div>";
                                }
                            }
                        }
                        document.getElementById("Content").innerHTML = screen;
                    },
                    error: function(jqXHR, textStatus, errorThrown){
                        //document.write(jqXHR.responseText + ':' + textStatus + ':' + errorThrown);
                        alert("An error occurred");
                    }
                })
            }

            function startAssessment(assessmentOID){
                $.ajax({
                    url: '../library/ajax/easipro_util.php',
                    type: "POST",
                    data: {
                        'csrf_token_form': <?php echo js_escape(CsrfUtils::collectCsrfToken()); ?>,
                        'function': 'start_assessment',
                        'assessmentOID': assessmentOID
                    },
                    dataType: "json",
                    success: function(data) {
                        var screen ="";
                        for (var j=0; j < data.Items[0].Elements.length; j++) {
                            if (typeof(data.Items[0].Elements[j].Map) == 'undefined') {
                                screen = screen + "<div style=\'height: 30px\' >" + data.Items[0].Elements[j].Description + "</div>"
                            } else {
                                for (var k=0; k < data.Items[0].Elements[j].Map.length; k++) {
                                    screen = screen + "<div style=\'height: 50px\' ><input type=\'button\' class='btn-submit' id=\'" + data.Items[0].Elements[j].Map[k].Value + "\' name=\'" + data.Items[0].Elements[j].Map[k].ItemResponseOID + "\' value=\'" + data.Items[0].Elements[j].Map[k].Description +  "\' onclick=selectResponse(this,'"+assessmentOID+"') />"    + "</div>";
                                }
                            }
                        }
                        document.getElementById("Content").innerHTML = screen;
                    },
                    error: function(jqXHR, textStatus, errorThrown){
                        //document.write(jqXHR.responseText);
                        alert("An error occurred");
                    }
                })
            }
        <?php } // end if $GLOBALS['easipro_enable'] ?>
    </script>
</head>

<body class="fixed">
    <header class="header">
        <nav class="navbar navbar-expand-md navbar-fixed-top navbar-light bg-light" role="navigation">
            <div class="container-fluid">
                <a href="home.php" class="navbar-brand d-none d-sm-block">
                    <img class="img-fluid" width="140" src='<?php echo $GLOBALS['images_static_relative']; ?>/logo-full-con.png' />
                </a>
                <button class="navbar-toggler" type="button" data-toggle="offcanvas" data-target="#pillCollapse" aria-controls="pillCollapse" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span>
                </button>
                <!-- Sidebar toggle button-->
                <ul class="nav navbar-nav flex-row">
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" id="newmsgs" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="true"> <i class="fa fa-envelope"></i> <span class="badge badge-pill badge-success"><?php echo text($newcnt); ?></span></a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="newmsgs">
                            <h6 class="dropdown-header"><?php echo xlt('You have '); ?><?php echo text($newcnt); ?><?php echo xlt(' new messages'); ?></h6>
                            <!-- inner menu: contains the actual data -->
                            <?php
                            foreach ($msgs as $i) {
                                if ($i['message_status'] == 'New') {
                                    echo "<div><a class='dropdown-item' href='" . $GLOBALS['web_root'] . "/portal/messaging/messages.php'><h4>" . text($i['title']) . "</h4></a></div>";
                                }
                            }
                            ?>
                            <div>
                                <a class="dropdown-item" href="<?php echo $GLOBALS['web_root']; ?>/portal/messaging/messages.php"><?php echo xlt('See All Messages'); ?></a>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" id="profiletab" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false"> <i class="fa fa-user"></i> <span><?php echo text($result['fname'] . " " . $result['lname']); ?> <i class="caret"></i></span></a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="profiletab">
                            <div class="dropdown-header text-center"><?php echo xlt('Account'); ?></div>
                            <div><a class="dropdown-item" href="<?php echo $GLOBALS['web_root']; ?>/portal/messaging/messages.php"> <i class="fa fa-envelope-o fa-fw"></i> <?php echo xlt('Messages'); ?>
                                    <span class="badge badge-pill badge-danger"><?php echo text($msgcnt); ?></span></a></div>
                            <div class="dropdown-divider"></div>
                            <?php if ($GLOBALS['allow_portal_chat']) { ?>
                                <div><a class="dropdown-item" href="<?php echo $GLOBALS['web_root']; ?>/portal/messaging/secure_chat.php?fullscreen=true"> <i class="fa fa-user fa-fw"></i><?php echo xlt('Chat'); ?></a></div>
                            <?php } ?>
                            <div><a class="dropdown-item" href="javascript:changeCredentials(event)"> <i class="fa fa-cog fa-fw"></i> <?php echo xlt('Change Credentials'); ?></a></div>
                            <div class="dropdown-divider"></div>
                            <div><a class="dropdown-item" href="logout.php"><i class="fa fa-ban fa-fw"></i> <?php echo xlt('Logout'); ?></a></div>
                        </div>
                    </li>
                </ul>
            </div>
        </nav>
    </header>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <!-- Left side column. contains the logo and sidebar -->
        <aside class="left-side sidebar-offcanvas">
            <section class="sidebar">
                <!-- Sidebar user panel -->
                <div class="user-panel">
                    <div class="float-left image">
                        <i class="fa fa-user"></i>
                    </div>
                    <div class="float-left info">
                        <p><?php echo xlt('Welcome') . ' ' . text($result['fname'] . " " . $result['lname']); ?></p>
                        <a href="#"><i class="fa fa-circle text-success"></i> <?php echo xlt('Online'); ?></a>
                    </div>
                </div>
                <ul class="nav nav-pills flex-column text-dark" id="pillCollapse">
                    <!-- css class was sidebar-menu -->
                    <li class="nav-item" data-toggle="pill"><a class="nav-link" href="#profilecard" data-toggle="collapse" data-parent="#cardgroup"> <i class="fa fa-calendar-o"></i> <span><?php echo xlt('Profile'); ?></span>
                        </a></li>
                    <li class="nav-item" data-toggle="pill"><a class="nav-link" href="#lists" data-toggle="collapse" data-parent="#cardgroup"> <i class="fa fa-list"></i><?php echo xlt('Lists'); ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $GLOBALS['web_root']; ?>/portal/patient/onsitedocuments?pid=<?php echo attr_url($pid); ?>"> <i class="fa fa-gavel"></i><span><?php echo xlt('Patient Documents'); ?></span></a></li>
                    <?php if ($GLOBALS['allow_portal_appointments']) { ?>
                        <li class="nav-item" data-toggle="pill"><a class="nav-link" href="#appointmentcard" data-toggle="collapse"
                                data-parent="#cardgroup"> <i class="fa fa-calendar-o"></i> <span><?php echo xlt("Appointment"); ?></span>
                            </a></li>
                    <?php } ?>
                    <?php if ($GLOBALS['portal_two_ledger'] || $GLOBALS['portal_two_payments']) { ?>
                        <li class="nav-item dropdown accounting-menu"><a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown"> <i class="fa fa-book"></i> <span><?php echo xlt('Accountings'); ?></span></a>
                            <div class="dropdown-menu">
                                <?php if ($GLOBALS['portal_two_ledger']) { ?>
                                    <span data-toggle="pill"><a class="dropdown-item" href="#ledgercard" data-toggle="collapse" data-parent="#cardgroup"> <i class="fa fa-folder-open"></i> <span><?php echo xlt('Ledger'); ?></span></a></span>
                                <?php } ?>
                                <?php if ($GLOBALS['portal_two_payments']) { ?>
                                    <span data-toggle="pill"><a class="dropdown-item" href="#paymentcard" data-toggle="collapse" data-parent="#cardgroup"> <i class="fa fa-credit-card"></i> <span><?php echo xlt('Make Payment'); ?></span></a></span>
                                <?php } ?>
                            </div>
                        </li>
                    <?php } ?>
                    <li class="nav-item dropdown reporting-menu"><a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown"> <i class="fa fa-calendar"></i> <span><?php echo xlt('Reports'); ?></span></a>
                        <div class="dropdown-menu">
                            <?php if ($GLOBALS['ccda_alt_service_enable'] > 1) { ?>
                                <a class="dropdown-item" id="callccda" href="<?php echo $GLOBALS['web_root']; ?>/ccdaservice/ccda_gateway.php?action=startandrun">
                                    <i class="fa fa-envelope" aria-hidden="true"></i><span><?php echo xlt('View CCD'); ?></span></a>
                            <?php } ?>
                            <?php if (!empty($GLOBALS['portal_onsite_document_download'])) { ?>
                                <span data-toggle="pill"><a class="dropdown-item" href="#reportcard" data-toggle="collapse"
                                        data-parent="#cardgroup"> <i class="fa fa-folder-open"></i> <span><?php echo xlt('Report Content'); ?></span></a></span>

                                <span data-toggle="pill"><a class="dropdown-item" href="#downloadcard" data-toggle="collapse"
                                        data-parent="#cardgroup"> <i class="fa fa-download"></i> <span><?php echo xlt('Download Lab Documents'); ?></span></a></span>
                            <?php } ?>
                        </div>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $GLOBALS['web_root']; ?>/portal/messaging/messages.php"><i class="fa fa-envelope" aria-hidden="true"></i>
                            <span><?php echo xlt('Secure Messaging'); ?></span>
                        </a></li>
                    <?php if ($GLOBALS['allow_portal_chat']) { ?>
                        <li class="nav-item" data-toggle="pill"><a class="nav-link" href="#messagescard" data-toggle="collapse"
                                data-parent="#cardgroup"> <i class="fa fa-envelope"></i> <span><?php echo xlt("Secure Chat"); ?></span>
                            </a></li>
                    <?php } ?>
                    <li class="nav-item" data-toggle="pill"><a class="nav-link" href="#openSignModal" data-toggle="modal" data-type="patient-signature">
                            <i class="fa fa-sign-in"></i><span><?php echo xlt('Signature on File'); ?></span>
                        </a></li>

                    <?php if ($GLOBALS['easipro_enable']) { ?>
                        <li class="nav-item" data-toggle="pill"><a href="#procard" data-toggle="collapse" data-parent="#cardgroup"> <i class="fa fa-edit"></i> <span><?php echo xlt("Patient Reported Outcomes"); ?></span></a></li>
                    <?php } ?>

                    <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fa fa-ban fa-fw"></i> <span><?php echo xlt('Logout'); ?></span></a></li>
                </ul>
            </section>
            <!-- /.sidebar -->
        </aside>
        <!-- Right side column. Contains content of the page -->
        <aside class="right-side">
            <!-- Main content -->
            <section class="container-fluid" id="cardgroup">
                <div id="popwait" class="alert alert-warning"><strong><?php echo xlt('Working!'); ?></strong> <?php echo xlt('Please wait...'); ?></div>
                <div class="collapse" id="lists">
                    <div class="card">
                        <header class="card-header bg-primary text-light"><?php echo xlt('Medications'); ?> </header>
                        <div id="medicationlist" class="card-body bg-light"></div>
                    </div>
                    <div class="card">
                        <header class="card-header bg-primary text-light"><?php echo xlt('Medications Allergy List'); ?>  </header>
                        <div id="allergylist" class="card-body bg-light"></div>
                    </div>
                    <div class="card">
                        <header class="card-header bg-primary text-light"><?php echo xlt('Issues List'); ?></header>
                        <div id="problemslist" class="card-body bg-light"></div>
                    </div>
                    <div class="card">
                        <header class="card-header bg-primary text-light"><?php echo xlt('Amendment List'); ?> </header>
                        <div id="amendmentslist" class="card-body bg-light"></div>
                    </div>
                    <div class="card">
                        <header class="card-header bg-primary text-light"><?php echo xlt('Lab Results'); ?>  </header>
                        <div id="labresults" class="card-body bg-light"></div>
                    </div>
                </div><!-- /.lists -->
                <?php if ($GLOBALS['allow_portal_appointments']) { ?>
                <div class="collapse w-100 mt-2" id="appointmentcard">
                    <div class="jumbotron jumbotron-fluid m-5 p-3">
                        <div class="container-fluid">
                            <h3 class="text-center"><?php echo xlt('Appointments'); ?></h3>
                            <?php
                            $current_date2 = date('Y-m-d');
                            $apptLimit = 30;
                            $appts = fetchNextXAppts($current_date2, $pid, $apptLimit);
                            if ($appts) {
                                $stringCM = "(" . xl("Comments field entry present") . ")";
                                $stringR = "(" . xl("Recurring appointment") . ")";
                                $count = 0;
                                foreach ($appts as $row) {
                                    $status_title = getListItemTitle('apptstat', $row['pc_apptstatus']);
                                    $count++;
                                    $dayname = xl(date("l", strtotime($row ['pc_eventDate'])));
                                    $dispampm = "am";
                                    $disphour = substr($row ['pc_startTime'], 0, 2) + 0;
                                    $dispmin = substr($row ['pc_startTime'], 3, 2);
                                    if ($disphour >= 12) {
                                        $dispampm = "pm";
                                        if ($disphour > 12) {
                                            $disphour -= 12;
                                        }
                                    }

                                    if ($row ['pc_hometext'] != "") {
                                        $etitle = xlt('Comments') . ": " . $row ['pc_hometext'] . "\r\n";
                                    } else {
                                        $etitle = "";
                                    }

                                    echo '<div class="card p-2">';
                                    $mode = (int)$row['pc_recurrtype'] > 0 ? text("recurring") : $row['pc_recurrtype'];
                                    $appt_type_icon = (int)$row['pc_recurrtype'] > 0 ? "<i class='float-right fa fa-edit text-danger bg-light'></i>" : "<i class='float-right fa fa-edit text-success bg-light'></i>";
                                    echo "<div class='card-header clearfix'><a href='#' onclick='editAppointment(" . attr_js($mode) . "," . attr_js($row ['pc_eid']) . ")'"  . "title='" . attr($etitle) . "'>" . $appt_type_icon . "</a></div>";
                                    echo "<div class='body font-weight-bold'><p>" . text($dayname . ", " . $row ['pc_eventDate']) . "&nbsp;";
                                    echo text($disphour . ":" . $dispmin . " " . $dispampm) . "<br />";
                                    echo xlt("Type") . ": " . text($row ['pc_catname']) . "<br />";
                                    echo xlt("Provider") . ": " . text($row ['ufname'] . " " . $row ['ulname']) . "<br />";
                                    echo xlt("Status") . ": " . text($status_title);
                                    echo "</p></div></div>";
                                }
                                if ($count == $apptLimit) {
                                    echo "<p>" . xlt("Display limit reached") . "<br>" . xlt("More appointments may exist") . "</p>";
                                }
                            } else { // if no appts
                                echo "<h3 class='text-center'>" . xlt('No Appointments') . "</h3>";
                            }
                            echo '</div>';
                            ?>
                            <span><a class='btn btn-primary btn-block' href='#' onclick="editAppointment('add',<?php echo attr_js($pid); ?>)"><?php echo xlt('Schedule A New Appointment'); ?></a>
                            </span>
                        </div>
                    </div><!-- /.row -->
                    <?php } ?>
                    <?php if ($GLOBALS['portal_two_payments']) { ?>
                        <div class="row collapse" id="paymentcard">
                            <div class="card w-100">
                                <header class="card-header bg-primary text-light"> <?php echo xlt('Payments'); ?> </header>
                                <div id="payment" class="card-body bg-light"></div>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if ($GLOBALS['allow_portal_chat']) { ?>
                        <div class="collapse w-100" id="messagescard">
                            <div class="card pt-0 pb-0">
                                <header class="card-header bg-primary text-light"><?php echo xlt('Secure Chat'); ?>  </header>
                                <div id="messages" class="card-body p-0" style="height:calc(100vh - 120px); overflow:auto;">
                                    <iframe src="./messaging/secure_chat.php" width="100%" height="100%"></iframe>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="row">
                        <div class="card collapse w-100" id="reportcard">
                            <header class="card-header bg-primary text-light"><?php echo xlt('Reports'); ?></header>
                            <div id="reports" class="card-body"></div>
                        </div>
                        <?php if (!empty($GLOBALS['portal_onsite_document_download'])) { ?>
                            <div class="card collapse w-100" id="downloadcard">
                                <header class="card-header bg-primary text-light"> <?php echo xlt('Download Documents'); ?> </header>
                                <div id="docsdownload" class="card-body">
                                    <div>
                                        <span class="text"><?php echo xlt('Download all patient documents'); ?></span>
                                        <form name='doc_form' id='doc_form' action='./get_patient_documents.php' method='post'>
                                            <input type="button" class="generateDoc_download" value="<?php echo xla('Download'); ?>" />
                                        </form>
                                    </div>
                                </div><!-- /.card-body -->
                            </div>
                        <?php } ?>
                    </div>
                    <?php if ($GLOBALS['portal_two_ledger']) { ?>
                        <div class="row collapse" id="ledgercard">
                            <div class="card w-100">
                                <header class="card-header bg-primary text-light"><?php echo xlt('Ledger'); ?></header>
                                <div id="patledger" class="card-body">
                                    <iframe src="./report/pat_ledger.php" width="100%" height="475" scrolling="yes"></iframe>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if ($GLOBALS['easipro_enable']) { ?>
                        <div class="row card collapse" id="procard">
                            <header class="card-header bg-primary text-light"> <?php echo xlt('Patient Reported Outcomes'); ?> </header>
                            <div id="pro" class="card-body bg-light"></div>
                        </div>
                    <?php } ?>
                    <div class="row card collapse" id="profilecard">
                        <header class="card-header bg-primary text-light"><?php echo xlt('Profile'); ?></header>
                        <div id="profilereport" class="card-body bg-light"></div>
                    </div>
            </section>
        </aside><!-- /.right-side -->
    </div><!-- ./wrapper -->
</body>
</html>
