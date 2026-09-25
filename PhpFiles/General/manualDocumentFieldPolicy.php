<?php
declare(strict_types=1);

/** Common personal fields needed by the current manual document templates.
 * Name, address, purpose, document-specific details and fee eligibility are
 * handled separately. Keep the browser and submission validation on this map.
 */
function manual_document_personal_field_policies(): array
{
    return [
        'barangay_id' => ['birthdate', 'birthplace', 'sex', 'contact_number'],
        'residency' => ['birthdate', 'birthplace'],
        'general_certification' => ['birthdate', 'birthplace'],
        'identity' => ['birthdate', 'birthplace'],
        'good_moral' => [],
        'indigency' => [],
        'cohabitation' => ['birthdate'],
        'jail_visit' => [],
        'first_time_job_seeker' => ['sex'],
        'general_clearance' => [],
        'business_clearance' => [],
        'tricycle_clearance' => [],
    ];
}

function manual_document_personal_fields(string $documentType, array $payload = []): array
{
    $token = strtolower((string)preg_replace('/[^a-z0-9]+/i', '', $documentType));
    $kind = '';
    if (str_contains($token, 'barangayid')) {
        $kind = 'barangay_id';
    } elseif (str_contains($token, 'clearance')) {
        $kind = 'general_clearance';
    } elseif (str_contains($token, 'cohabitation')) {
        $variant = strtolower(trim((string)($payload['cohabitation_variant'] ?? '')));
        $kind = in_array($variant, ['relationship_jail_visit', 'conjugal_visit'], true) ? 'jail_visit' : 'cohabitation';
    } elseif (str_contains($token, 'firsttimejobseeker')) {
        $kind = 'first_time_job_seeker';
    } elseif (str_contains($token, 'residency') || str_contains($token, 'generalcertificat')) {
        $kind = 'residency';
    } elseif (str_contains($token, 'identity')) {
        $kind = 'identity';
    }
    return manual_document_personal_field_policies()[$kind] ?? [];
}

/** Apply after linking/autofilling a resident so unused fields cannot return
 * through the profile snapshot or legacy payload aliases and fail validation.
 */
function manual_document_filter_personal_fields(string $documentType, array $payload): array
{
    $needed = manual_document_personal_fields($documentType, $payload);
    $aliases = [
        'birthdate' => ['birthdate', 'date_of_birth', 'birthDate', 'child_dob', 'age'],
        'birthplace' => ['birthplace', 'place_of_birth', 'child_birthplace'],
        'sex' => ['sex', 'gender', 'child_sex'],
        'civil_status' => ['civil_status'],
        'contact_number' => ['contact_number', 'phone_number'],
        'occupation' => ['occupation', 'occupation_display'],
        'religion' => ['religion'],
    ];
    foreach ($aliases as $field => $keys) {
        if (!in_array($field, $needed, true)) {
            foreach ($keys as $key) {
                unset($payload[$key]);
            }
        }
    }
    return $payload;
}
