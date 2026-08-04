<?php
declare(strict_types=1);

/** Populate the clean testing database with a high-volume, linked demo dataset. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--execute', $argv, true)) { fwrite(STDERR, "Use --execute to populate the database.\n"); exit(2); }

require_once __DIR__ . '/../PhpFiles/General/connection.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+08:00'");

function runq(mysqli $db, string $sql, array $args = []): mysqli_stmt {
    static $queryNumber = 0;
    $queryNumber++;
    try {
        $stmt = $db->prepare($sql);
        if ($args) {
            $types = '';
            foreach ($args as $v) $types .= is_int($v) ? 'i' : (is_float($v) ? 'd' : 's');
            $stmt->bind_param($types, ...$args);
        }
        $stmt->execute();
        return $stmt;
    } catch (Throwable $e) {
        $summary = preg_replace('/\s+/', ' ', trim($sql)) ?: 'unknown SQL';
        throw new RuntimeException('Query '.$queryNumber.' failed ['.substr($summary, 0, 100).']: '.$e->getMessage(), 0, $e);
    }
}
function one(mysqli $db, string $sql, array $args = []): ?array {
    $stmt = runq($db, $sql, $args); $row = $stmt->get_result()->fetch_assoc() ?: null; $stmt->close(); return $row;
}
function status_id(mysqli $db, string $type, string $name): int {
    $row = one($db, 'SELECT status_id FROM statuslookuptbl WHERE status_type=? AND status_name=? LIMIT 1', [$type, $name]);
    if (!$row) throw new RuntimeException("Missing status: $type / $name");
    return (int)$row['status_id'];
}
function enc(?string $v): ?string { return $v === null ? null : (string)pii_encrypt_string($v); }
function dt(int $days, int $hour = 9): string { return (new DateTimeImmutable("$days days", new DateTimeZone('Asia/Manila')))->setTime($hour, 0)->format('Y-m-d H:i:s'); }
function day(int $days): string { return substr(dt($days), 0, 10); }

$admin = '202602S00001';
if (!(one($conn, 'SELECT user_id FROM useraccountstbl WHERE user_id=? AND role_access=\'SuperAdmin\'', [$admin]))) {
    throw new RuntimeException('Required SuperAdmin 202602S00001 is missing.');
}
if (one($conn, "SELECT user_id FROM useraccountstbl WHERE user_id LIKE '202608R1%' LIMIT 1")) {
    throw new RuntimeException('Bulk demo dataset already exists; no changes made.');
}

$st = [
    'account' => [1, 2, 3, 4, 88],
    'resident' => [9, 10, 11, 15],
    'address' => [12, 13, 14],
    'edit' => [29, 30, 31],
    'sector' => [58, 59, 60],
    'document' => [40, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53],
    'payment' => [61, 62, 63, 64, 65],
    'complaint' => [75, 77, 78, 79, 92, 93, 94, 95, 96],
    'blotter' => [66, 67, 68, 69],
];
$stages = ['submitted','for_interview','for_inspection','inspection_failed','for_payment','payment_submitted','payment_rejected','payment_verified','ready_for_claim','completed','cancelled'];
$firstNames = ['Maria','Jose','Ana','Miguel','Liza','Carlo','Elena','Paolo','Rosa','Daniel','Sofia','Gabriel','Angela','Ramon','Teresa','Marco','Julia','Luis','Carmen','Noel'];
$lastNames = ['Dela Cruz','Santos','Reyes','Garcia','Mendoza','Bautista','Ramos','Flores','Aquino','Castillo','Navarro','Torres','Villanueva','Rivera','Gonzales'];
$areas = ['Area01','Area1A','Area02','Area03','Area04','Area05','Area06'];
$sectors = ['senior_citizen','pwd','solo_parent','youth','indigenous_people','women','lgbtqia','farmer','unemployed'];
$password = password_hash('Resident@12345', PASSWORD_DEFAULT);

$conn->begin_transaction();
try {
    // 55 resident accounts and profiles with varied account, resident, and address statuses.
    for ($i = 1; $i <= 55; $i++) {
        $userId = '202608R1' . str_pad((string)$i, 4, '0', STR_PAD_LEFT);
        $residentId = '2610' . str_pad((string)$i, 6, '0', STR_PAD_LEFT);
        $first = $firstNames[($i - 1) % count($firstNames)];
        $last = $lastNames[($i - 1) % count($lastNames)];
        $sex = $i % 2 ? 'Female' : 'Male';
        $phone = '0918' . str_pad((string)(1000000 + $i), 7, '0', STR_PAD_LEFT);
        $email = "resident{$i}@demo.test";
        $contacts = pii_prepare_useraccount_contacts($email, $phone);
        // Keep every seeded resident eligible for operational tracker joins.
        $accountStatus = 1;
        $residentStatus = 11;
        runq($conn, 'INSERT INTO useraccountstbl (user_id,phone_number,phone_lookup_hash,phoneNum_verify,email,email_lookup_hash,email_verify,password_hash,status_id_account,role_access,account_created,last_login,last_password_changed,failed_logins) VALUES (?,?,?,?,?,?,?,?,?,\'Resident\',?,?,?,?)', [
            $userId,$contacts['phone_number'],$contacts['phone_lookup_hash'],1,$contacts['email'],$contacts['email_lookup_hash'],1,$password,(string)$accountStatus,dt(-90 + $i),dt(-($i % 20)),day(-30),0
        ])->close();
        runq($conn, 'INSERT INTO residentinformationtbl (resident_id,user_id,lastname,firstname,middlename,sex,birthdate,birthplace,baranagayresidency,civil_status,family_role,head_of_family,voter_status,occupation,occupation_detail,religion,sector_membership,privacy_consent,status_id_resident) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            $residentId,$userId,enc($last),enc($first),enc('Demo'),$sex,enc(sprintf('%04d-%02d-%02d', 1955 + ($i % 48), 1 + ($i % 12), 1 + ($i % 27))),enc('Rodriguez, Rizal'),enc(($i % 15 + 1).' years'),enc($i % 3 ? 'Single' : 'Married'),enc($i % 12 === 1 ? 'Household Head' : 'Member'),$i <= 12 ? 1 : 0,$i % 4 ? 1 : 0,1,enc(['Teacher','Driver','Vendor','Student','Office Worker','Farmer'][$i % 6]),enc('Roman Catholic'),enc($sectors[$i % count($sectors)]),1,$residentStatus
        ])->close();
        runq($conn, 'INSERT INTO residentaddresstbl (address_id,resident_id,unit_number,street_number,street_name,phase_number,subdivision,area_number,house_type,house_ownership,residency_duration,status_id_residency) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [
            'A1'.str_pad((string)$i,8,'0',STR_PAD_LEFT),$residentId,enc((string)$i),enc((string)(100+$i)),enc(['Mabini','Rizal','Luna','Bonifacio','Del Pilar'][$i%5].' Street'),enc((string)(1+$i%3)),enc('San Jose Village'),$areas[$i%count($areas)],enc($i%3?'Concrete':'Semi-concrete'),enc($i%2?'Owned':'Rented'),enc(($i%20+1).' years'),12
        ])->close();
        runq($conn, 'INSERT INTO emergencycontacttbl (emergency_id,user_id,last_name,first_name,phone_number,relationship,address) VALUES (?,?,?,?,?,?,?)', [
            110000+$i,$userId,enc($last),enc('Emergency '.$first),enc('0919'.str_pad((string)(2000000+$i),7,'0',STR_PAD_LEFT)),enc($i%2?'Sibling':'Parent'),enc('Barangay San Jose, Rodriguez, Rizal')
        ])->close();
    }

    // 15 edit requests (<20), evenly distributed among request and review statuses.
    for ($i=1; $i<=15; $i++) {
        $residentId='2610'.str_pad((string)$i,6,'0',STR_PAD_LEFT); $userId='202608R1'.str_pad((string)$i,4,'0',STR_PAD_LEFT);
        $reviewed = $i%3 !== 1;
        runq($conn, 'INSERT INTO resident_edit_requesttbl (request_id,resident_id,user_id,request_type,status_id,requested_changes,admin_notes,created_at,reviewed_at,reviewed_by) VALUES (?,?,?,?,?,?,?,?,?,?)', [
            120000+$i,$residentId,$userId,['profile','address','emergency'][($i-1)%3],$st['edit'][($i-1)%3],json_encode(['field'=>$i%2?'occupation_detail':'street_name','old'=>'Old demo value','new'=>'Updated demo value '.$i]),$reviewed?'Reviewed sample request':null,dt(-20+$i),$reviewed?dt(-10+$i):null,$reviewed?$admin:null
        ])->close();
    }

    // 35 sector memberships, including 25 pending verification records.
    for ($i=1; $i<=35; $i++) {
        $residentId='2610'.str_pad((string)$i,6,'0',STR_PAD_LEFT);
        $sectorStatus = $i<=25 ? 58 : ($i<=31 ? 59 : 60);
        runq($conn, 'INSERT INTO residentsectormembershiptbl (resident_id,sector_key,sector_status_id,remarks,upload_timestamp,last_update_user_id) VALUES (?,?,?,?,?,?)', [
            $residentId,$sectors[($i-1)%count($sectors)],$sectorStatus,$sectorStatus===58?'Awaiting verification':($sectorStatus===59?'Verified membership':'Inactive membership'),dt(-35+$i),$sectorStatus===58?null:$admin
        ])->close();
    }

    // 12 household profiles, each with a head and members, plus review workflows.
    for ($h=1; $h<=12; $h++) {
        $headIndex=$h; $headResident='2610'.str_pad((string)$headIndex,6,'0',STR_PAD_LEFT); $householdId=130000+$h;
        runq($conn, 'INSERT INTO householdtbl (household_id,head_resident_id,status_id,created_at) VALUES (?,?,?,?)', [$householdId,$headResident,$h===12?27:26,dt(-50+$h)])->close();
        for ($m=0;$m<3;$m++) {
            $residentIndex=$m===0 ? $headIndex : 12+(($h-1)*2+$m); $rid='2610'.str_pad((string)$residentIndex,6,'0',STR_PAD_LEFT);
            runq($conn, 'INSERT IGNORE INTO householdmemberresidenttbl (household_id,resident_id,role,status_id,invited_by_resident_id,joined_at) VALUES (?,?,?,?,?,?)', [$householdId,$rid,$m===0?'Head':'Member',$m===2&&$h%4===0?25:24,$m===0?null:$headResident,dt(-30+$h)])->close();
        }
        runq($conn, 'INSERT INTO householdmemberinfotbl (household_member_id,fam_head_id,last_name,first_name,middle_name,birthdate) VALUES (?,?,?,?,?,?)', [140000+$h,$headResident,'Dependent','Child '.$h,'Demo',sprintf('201%d-%02d-15',$h%9,1+$h%12)])->close();
        runq($conn, 'INSERT INTO householdheadverificationtbl (verification_id,group_key,address_id,address_display,area_number,selected_resident_id,decision_status,remarks,decided_by_user_id,decided_at) VALUES (?,?,?,?,?,?,?,?,?,?)', [150000+$h,'BULK-HH-'.$h,'A1'.str_pad((string)$headIndex,8,'0',STR_PAD_LEFT),'Demo household address '.$h,$areas[$h%7],$headResident,['Pending','Approved','Declined'][($h-1)%3],'Sample household head review',$h%3===1?null:$admin,$h%3===1?null:dt(-5)])->close();
    }

    $certTypes=['Certificate of Residency','Certificate of Indigency','Certificate of Good Moral','First Time Job Seeker Certificate','Certificate of Cohabitation','Certificate of Identity'];
    $clearanceTypes=['Barangay Clearance for Business Permit','Barangay Clearance for Tricycle Permit','Barangay Clearance for Electrical Permit','Barangay Clearance for Water Permit','Barangay Clearance for Residential Permit','Barangay Clearance for Residential Building Permit','Barangay Clearance for Commercial Permit','Barangay Clearance for Commercial Building Permit'];
    $stageStatus=array_combine($stages,$st['document']);
    $requestIndex=0; $financeIndex=0;
    $insertRequest = function(string $requestId,int $residentNo,string $docType,string $purpose,string $channel,int $sequence) use ($conn,$admin,$stages,$stageStatus): array {
        $stage=$stages[($sequence-1)%count($stages)]; $status=$stageStatus[$stage];
        $manual=$channel==='manual'; $userId='202608R1'.str_pad((string)$residentNo,4,'0',STR_PAD_LEFT); $residentId='2610'.str_pad((string)$residentNo,6,'0',STR_PAD_LEFT);
        $payload=['_request_channel'=>$manual?'manual_issuance':'online','_submission_channel'=>$manual?'manual_admin_walkin':'resident_portal','_resident_link_mode'=>'registered','intake_channel'=>$channel,'purpose'=>$purpose,'seed_group'=>'bulk-volume'];
        $reviewed=!in_array($stage,['submitted'],true); $completed=in_array($stage,['completed'],true); $released=in_array($stage,['ready_for_claim','completed'],true);
        runq($conn, 'INSERT INTO documentrequesttbl (request_id,resident_user_id,resident_name,request_details,status_id_request,user_id_official_reviewed_by,user_id_official_released_by,request_timestamp,review_timestamp,release_timestamp,document_validity,submitted_at,personnel_user_id,personnel_decision_at,ready_at,completed_at,resident_id,document_type,purpose,payload_json,stage,status_reason,certificate_number,verification_code) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            $requestId,$userId,'Demo Resident '.$residentNo,json_encode($payload),$status,$reviewed?$admin:null,$released?$admin:null,dt(-80+$sequence),$reviewed?dt(-40+$sequence):null,$completed?dt(-10):null,$completed?dt(355):null,dt(-80+$sequence),$reviewed?$admin:null,$reviewed?dt(-40+$sequence):null,$released?dt(-15):null,$completed?dt(-10):null,$residentId,$docType,$purpose,json_encode($payload),$stage,$stage==='cancelled'?'Cancelled demonstration request':($stage==='inspection_failed'?'Inspection requirements not met':null),$completed?'CERT-2026-'.str_pad((string)$sequence,5,'0',STR_PAD_LEFT):null,$completed?'VERIFY-'.str_pad((string)$sequence,6,'0',STR_PAD_LEFT):null
        ])->close();
        return [$stage,$status,$userId,$residentId];
    };
    $insertFinance = function(string $requestId,int $sequence,string $label,float $amount,string $userId) use ($conn,$admin,$st,&$financeIndex): void {
        $financeIndex++; $payStatus=$st['payment'][($sequence-1)%count($st['payment'])]; $verified=$payStatus===63;
        runq($conn, 'INSERT INTO financetransactiontbl (transaction_id,request_id,transaction_amount,applicant_lastname,applicant_firstname,payment_method,payment_proof_path,transaction_details,or_number,transaction_status_id,payment_deadline,payment_timestamp,finance_decision_at,user_id_employee_process) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            'BF'.str_pad((string)$financeIndex,8,'0',STR_PAD_LEFT),$requestId,$amount,'Demo','Applicant '.$sequence,['Cash','GCash','Bank Transfer'][($sequence-1)%3],$payStatus===62?'Uploads/demo/payment-'.$sequence.'.jpg':null,json_encode(['document'=>$label,'seed_group'=>'bulk-volume']),$verified?'OR-2026-'.str_pad((string)$financeIndex,5,'0',STR_PAD_LEFT):null,$payStatus,dt(7+$sequence%10),in_array($payStatus,[62,63,64],true)?dt(-5):null,in_array($payStatus,[63,64],true)?dt(-3):null,in_array($payStatus,[63,64],true)?$admin:null
        ])->close();
        runq($conn, 'INSERT INTO residenttransactiontbl (transaction_id,user_id,resident_user_id,source_type,source_id,transaction_type,title,details,amount,payment_method,or_number,payment_deadline,payment_timestamp,status_id,reviewed_by,reviewed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            'RT'.str_pad((string)$financeIndex,8,'0',STR_PAD_LEFT),$userId,$userId,'DocumentRequest',$requestId,'Payment',$label,'Linked finance transaction',$amount,['Cash','GCash','Bank Transfer'][($sequence-1)%3],$verified?'OR-2026-'.str_pad((string)$financeIndex,5,'0',STR_PAD_LEFT):null,dt(7+$sequence%10),$verified?dt(-5):null,$payStatus,in_array($payStatus,[63,64],true)?$admin:null,in_array($payStatus,[63,64],true)?dt(-3):null
        ])->close();
    };

    // 60 certificates, 30 online and 30 manual.
    for ($i=1;$i<=60;$i++) {
        $requestIndex++; $rid=1+(($i-1)%55); $requestId='BULK-CERT-'.str_pad((string)$i,3,'0',STR_PAD_LEFT); $type=$certTypes[($i-1)%count($certTypes)]; $channel=$i%2?'online':'manual';
        [$stage,$docStatus,$userId]=$insertRequest($requestId,$rid,$type,['Employment','Scholarship','Medical Assistance','Bank Requirement','Legal Purpose'][$i%5],$channel,$requestIndex);
        $certId='C1'.str_pad((string)$i,8,'0',STR_PAD_LEFT); $details=json_encode(['channel'=>$channel,'certificate_type'=>$type]);
        runq($conn, 'INSERT INTO certificatesrequesttbl (certificate_id,request_id,certificate_type,certificate_details,certificate_number,verification_code) VALUES (?,?,?,?,?,?)', [$certId,$requestId,$type,$details,$stage==='completed'?'CERT-'.$i:null,$stage==='completed'?'VC-'.$i:null])->close();
        runq($conn, 'INSERT INTO issuancerequesttbl (certificate_id,request_id,certificate_type,certificate_details,certificate_number,verification_code) VALUES (?,?,?,?,?,?)', ['I1'.str_pad((string)$i,8,'0',STR_PAD_LEFT),$requestId,$type,$details,$stage==='completed'?'CERT-'.$i:null,$stage==='completed'?'VC-'.$i:null])->close();
        $insertFinance($requestId,$requestIndex,$type,in_array($type,['Certificate of Indigency','First Time Job Seeker Certificate'],true)?0.0:50.0,$userId);
    }

    // 30 Barangay ID issuances, split online/manual with varied workflow stages.
    for ($i=1;$i<=30;$i++) {
        $requestIndex++; $rid=1+(($i+9)%55); $requestId='BULK-ID-'.str_pad((string)$i,3,'0',STR_PAD_LEFT); $channel=$i%2?'online':'manual';
        [$stage,$docStatus,$userId]=$insertRequest($requestId,$rid,'Barangay ID',$i%3===0?'Renewal':($i%3===1?'New Application':'Lost Replacement'),$channel,$requestIndex);
        runq($conn, 'INSERT INTO barangayidrequesttbl (barangay_id,request_id,id_details) VALUES (?,?,?)', ['D1'.str_pad((string)$i,8,'0',STR_PAD_LEFT),$requestId,json_encode(['channel'=>$channel,'application_type'=>$i%3===0?'renewal':'new','blood_type'=>['O+','A+','B+','AB+'][$i%4]])])->close();
        $insertFinance($requestId,$requestIndex,'Barangay ID',100.0,$userId);
    }

    // 30 clearances, split online/manual, with fees and inspections where applicable.
    for ($i=1;$i<=30;$i++) {
        $requestIndex++; $rid=1+(($i+24)%55); $requestId='BULK-CLR-'.str_pad((string)$i,3,'0',STR_PAD_LEFT); $type=$clearanceTypes[($i-1)%count($clearanceTypes)]; $channel=$i%2?'manual':'online';
        [$stage,$docStatus,$userId]=$insertRequest($requestId,$rid,$type,$i%2?'New application':'Renewal',$channel,$requestIndex);
        runq($conn, 'INSERT INTO clearancerequesttbl (request_id,clearance_type,application_type,clearance_details) VALUES (?,?,?,?)', [$requestId,$type,$i%2?'New':'Renewal',json_encode(['channel'=>$channel,'business_name'=>'Demo Enterprise '.$i,'plate_number'=>'TRI-'.str_pad((string)$i,4,'0',STR_PAD_LEFT)])])->close();
        $clearanceId=(int)$conn->insert_id;
        runq($conn, 'INSERT INTO clearancefeestbl (clearance_fee_id,clearance_id,fee_type,amount) VALUES (?,?,?,?)', [200000+$i,$clearanceId,'Processing Fee',150.0+($i%4)*50])->close();
        if ($i<=20) runq($conn, 'INSERT INTO clearanceinspectiontbl (inspection_id,clearance_id,inspector_name,date_inspected,remarks) VALUES (?,?,?,?,?)', ['IN'.str_pad((string)$i,7,'0',STR_PAD_LEFT),(string)$clearanceId,'Inspector '.(($i%4)+1),dt(-20+$i),$i%4===0?'Failed initial inspection':'Compliant inspection'])->close();
        $insertFinance($requestId,$requestIndex,$type,150.0+($i%4)*50,$userId);
    }

    // 40 complaints and 40 incidents/e-blotters, each distributed across valid statuses.
    $complaintTypes=['Noise Complaint','Harassment','Property Dispute','Public Disturbance','Neighbor Dispute','Online Harassment'];
    for ($i=1;$i<=40;$i++) {
        $residentNo=1+(($i-1)%55); $uid='202608R1'.str_pad((string)$residentNo,4,'0',STR_PAD_LEFT); $caseId='BCMP'.str_pad((string)$i,8,'0',STR_PAD_LEFT);
        $caseStatus=$st['complaint'][($i-1)%count($st['complaint'])]; $level=$i%5===0?80:76;
        runq($conn, 'INSERT INTO casereportstbl (case_id,resident_user_id,report_type,incident_date,incident_time,incident_place,incident_area_number,complaint_type,case_details,case_remarks,resolution_remarks,case_status_id,case_level_id,report_timestamp,user_id_official_update_by,user_id_official_reviewed_by,user_id_official_record_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            $caseId,$uid,'Complaint',day(-60+$i),sprintf('%02d:00:00',8+$i%10),'Location '.$i.', Barangay San Jose',$areas[$i%7],$complaintTypes[$i%count($complaintTypes)],'Detailed complaint narrative for demonstration record '.$i,$i%3===0?'Follow-up required':null,in_array($caseStatus,[77,78,94],true)?'Case disposition recorded':null,$caseStatus,$level,dt(-60+$i),$admin,$i%2===0?$admin:null,$admin
        ])->close();
        runq($conn, 'INSERT INTO complaintstbl (complaint_id,case_id,complaint_origin,subject_kind,subject_display_name,subject_contact_number,subject_address,witness_summary,intake_notes,screening_notes,escalated_to_blotter,escalated_to_blotter_at,escalated_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            'CP'.str_pad((string)$i,8,'0',STR_PAD_LEFT),$caseId,['ResidentPortal','AdminEncoded','WalkIn','Referral'][($i-1)%4],['Resident','NonResident','Business','Organization','Unknown','GeneralConcern'][($i-1)%6],'Subject Person '.$i,'0920'.str_pad((string)(3000000+$i),7,'0',STR_PAD_LEFT),$areas[$i%7].', San Jose','Witness statement summary '.$i,'Intake notes '.$i,$i%2===0?'Screened by desk officer':null,$i%5===0?1:0,$i%5===0?dt(-10):null,$i%5===0?$admin:null
        ])->close();
        foreach ([['Complainant','Complainant'],['Respondent','Respondent']] as [$role,$name]) runq($conn, 'INSERT INTO caseparticipantstbl (case_id,participant_role,lastname,firstname,contact_number,address,area_number,age,sex) VALUES (?,?,?,?,?,?,?,?,?)', [$caseId,$role,$lastNames[$i%15],$name.' '.$i,'0921'.str_pad((string)(4000000+$i),7,'0',STR_PAD_LEFT),$areas[$i%7].', San Jose',$areas[$i%7],20+$i%45,$i%2?'Female':'Male'])->close();
        runq($conn, 'INSERT INTO caseupdateslogtbl (case_id,log_entry,logged_by_user_id,logged_at) VALUES (?,?,?,?)', [$caseId,'Complaint workflow update '.$i,$admin,dt(-20+$i%10)])->close();
    }
    for ($i=1;$i<=40;$i++) {
        $residentNo=1+(($i+14)%55); $uid='202608R1'.str_pad((string)$residentNo,4,'0',STR_PAD_LEFT); $caseId='BBLT'.str_pad((string)$i,8,'0',STR_PAD_LEFT);
        $caseStatus=$st['blotter'][($i-1)%count($st['blotter'])]; $level=[70,71,72,73,74][($i-1)%5];
        runq($conn, 'INSERT INTO casereportstbl (case_id,resident_user_id,report_type,incident_date,incident_time,incident_place,incident_area_number,complaint_type,case_details,case_remarks,resolution_remarks,case_status_id,case_level_id,report_timestamp,user_id_official_update_by,user_id_official_reviewed_by,user_id_official_record_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            $caseId,$uid,'Blotter',day(-70+$i),sprintf('%02d:30:00',7+$i%12),'Incident site '.$i.', Barangay San Jose',$areas[$i%7],['Physical Altercation','Theft','Threat','Property Damage','Domestic Dispute'][$i%5],'Incident narrative and circumstances for e-blotter record '.$i,$i%2?'Mediation scheduled':'Under desk review',in_array($caseStatus,[67,68],true)?'Incident closed with recorded resolution':null,$caseStatus,$level,dt(-70+$i),$admin,$admin,$admin
        ])->close();
        runq($conn, 'INSERT INTO barangayblottertbl (blotter_id,case_id,blotter_number,logbook_id,date_filed,time_filed) VALUES (?,?,?,?,?,?)', ['BL'.str_pad((string)$i,8,'0',STR_PAD_LEFT),$caseId,'BLT-2026-'.str_pad((string)$i,4,'0',STR_PAD_LEFT),'LOG-'.str_pad((string)(1+(int)(($i-1)/10)),2,'0',STR_PAD_LEFT),day(-70+$i),'09:00:00'])->close();
        foreach ([['Complainant','Reporter'],['Respondent','Subject'],['Witness','Witness']] as [$role,$name]) runq($conn, 'INSERT INTO caseparticipantstbl (case_id,participant_role,lastname,firstname,contact_number,address,area_number,age,sex) VALUES (?,?,?,?,?,?,?,?,?)', [$caseId,$role,$lastNames[($i+2)%15],$name.' '.$i,'0922'.str_pad((string)(5000000+$i),7,'0',STR_PAD_LEFT),$areas[$i%7].', San Jose',$areas[$i%7],18+$i%55,$i%2?'Male':'Female'])->close();
        runq($conn, 'INSERT INTO casestatushistorytbl (case_id,status_id,changed_by,changed_at,remarks) VALUES (?,?,?,?,?)', [$caseId,$caseStatus,$admin,dt(-30+$i%20),'Initial e-blotter status'])->close();
        runq($conn, 'INSERT INTO caseupdateslogtbl (case_id,log_entry,logged_by_user_id,logged_at) VALUES (?,?,?,?)', [$caseId,'E-blotter action logged '.$i,$admin,dt(-25+$i%15)])->close();
    }

    $conn->commit();
    echo "Bulk demo population completed.\n";
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    fwrite(STDERR, 'Population failed and was rolled back: '.$e->getMessage()."\n");
    exit(1);
}
