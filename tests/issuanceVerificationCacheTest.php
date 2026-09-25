<?php
namespace QrRegression;
// Run with php tests/issuanceVerificationCacheTest.php. Uses in-memory doubles; no database writes.
class mysqli {
    public $rows = ['issuancerequesttbl' => ['certificate_type'=>'Barangay ID','certificate_number'=>'ID-123','verification_code'=>'']];
    public function prepare($sql) { return new TestStatement($this, $sql); }
}
class TestStatement {
    private $db; private $sql; private $args;
    public function __construct($db, $sql) { $this->db=$db; $this->sql=$sql; }
    public function bind_param($types, &...$args) { $this->args=&$args; return true; }
    public function execute() {
        if (strpos($this->sql, 'INSERT INTO') !== false) {
            $this->db->rows['issuancerequesttbl']=['certificate_type'=>$this->args[2], 'certificate_number'=>$this->args[3], 'verification_code'=>$this->args[4]];
        }
        return true;
    }
    public function get_result() { return new TestResult($this->db->rows['issuancerequesttbl']); }
    public function close() {}
}
class TestResult {
    private $row;
    public function __construct($row) { $this->row=$row; }
    public function fetch_assoc() { return $this->row; }
}
function dr_request_child_id_column($table) { return 'issuance_id'; }
function dr_resolve_request_child_id($conn,$table,$id) { return 'CHILD-TEST'; }
function dr_ensure_certificate_request_table($conn) {}
function dr_issuance_table_candidates($conn) { return ['issuancerequesttbl']; }
function dr_column_exists($conn,$table,$column) { return $column !== 'updated_at'; }
$source=file_get_contents(__DIR__ . '/../PhpFiles/General/documentRequestWorkflow.php');
foreach (['dr_get_issuance_request_meta','dr_upsert_issuance_identifiers'] as $name) {
    $start=strpos($source,'function '.$name.'(');
    $end=strpos($source,"\nfunction ",$start+1);
    eval('namespace QrRegression;' . substr($source,$start,$end-$start));
}
$db=new mysqli();
if(dr_get_issuance_request_meta($db,'TEST')['verification_code']!=='') throw new \Exception('Initial state incorrect');
dr_upsert_issuance_identifiers($db,'TEST',null,'NEW-VERIFICATION-CODE');
$after=dr_get_issuance_request_meta($db,'TEST');
if($after['verification_code']!=='NEW-VERIFICATION-CODE') throw new \Exception('Stale verification code after save');
if($after['certificate_number']!=='ID-123') throw new \Exception('Assigned number changed');
dr_upsert_issuance_identifiers($db,'TEST','ID-456',null);
$after=dr_get_issuance_request_meta($db,'TEST');
if($after['verification_code']!=='NEW-VERIFICATION-CODE'||$after['certificate_number']!=='ID-456') throw new \Exception('Partial update lost identifiers');
echo "PASS: cached empty code refreshes after save; assigned number and existing code preserved.\n";
