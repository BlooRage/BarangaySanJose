<?php
declare(strict_types=1);
require_once __DIR__.'/admin_guard.php';
require_once __DIR__.'/../../PhpFiles/General/documentModuleSettings.php';
require_once __DIR__.'/../../PhpFiles/General/audit.php';

$sectionRoutes=[
 'general'=>'Admin-End/Certificates/IssuanceGeneralSettings.php','certificates'=>'Admin-End/Certificates/IssuanceCertificateSettings.php',
 'notifications'=>'Admin-End/Certificates/IssuanceNotificationSettings.php','indigency'=>'Admin-End/Certificates/IndigencyRecipientSettings.php',
 'fees'=>'Admin-End/Certificates/IssuanceFeeSettings.php',
];
if (!isset($sectionRoutes[$issuanceSection])) throw new RuntimeException('Unknown issuance settings section.');
$sectionActionUrl=appUrl($sectionRoutes[$issuanceSection]);
$settingsOverviewUrl=appUrl('Admin-End/Certificates/CertificateIssuanceSettings.php');
$issuanceSettings=dms_resolve_issuance_settings($conn);
$governmentOfficialRows=dms_list_government_official_dropdown($conn);
$successMessage=trim((string)($_GET['success']??''));
$errorMessage=trim((string)($_GET['error']??''));

if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
 verifyCsrfToken(false);
 try {
  $action=(string)($_POST['action']??'');
  if (in_array($issuanceSection,['general','certificates','notifications'],true) && $action==='save_issuance_section') {
   $_POST['settings_scope']=$issuanceSection;
   $result=dms_save_issuance_settings($conn,$_POST,trim((string)($_SESSION['user_id']??'')));
   $message=ucfirst($issuanceSection).' settings saved.';
   insertUnifiedAuditLog($conn,trim((string)($_SESSION['user_id']??''))?:null,trim((string)($_SESSION['role']??'Official'))?:'Official','document_settings','issuance_'.$issuanceSection,'issuance','update_settings','configuration',json_encode($result['before']??[]),json_encode($result['after']??[]),$message);
  } elseif ($issuanceSection==='indigency' && $action==='save_indigency_government_official') {
   $saved=dms_save_government_official_dropdown($conn,$_POST); $message=(int)($_POST['government_official_id']??0)>0?'Government official updated.':'Government official added.';
  } elseif ($issuanceSection==='indigency' && $action==='delete_indigency_government_official') {
   if(!dms_delete_government_official_dropdown($conn,(int)($_POST['government_official_id']??0))) throw new RuntimeException('Government official could not be deleted.'); $message='Government official deleted.';
  } else throw new RuntimeException('Unsupported settings action.');
  header('Location: '.$sectionActionUrl.'?success='.rawurlencode($message)); exit;
 } catch(Throwable $e) { header('Location: '.$sectionActionUrl.'?error='.rawurlencode($e->getMessage())); exit; }
}

include __DIR__.'/issuance_settings_section_page.php';
