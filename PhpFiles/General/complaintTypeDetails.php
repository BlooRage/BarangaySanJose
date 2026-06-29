<?php

function complaintTypeDefinitions(): array
{
    return [
        'Disturbance' => [
            'label' => 'Disturbance',
            'fields' => [
                ['name' => 'disturbance_source', 'label' => 'Source of Disturbance', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Neighbor, group, gathering'],
                ['name' => 'disturbance_frequency', 'label' => 'How Often It Happens', 'type' => 'select', 'required' => true, 'options' => ['One-time', 'Occasional', 'Frequent', 'Ongoing']],
                ['name' => 'disturbance_time_pattern', 'label' => 'Usual Time or Schedule', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Late at night, every weekend'],
            ],
        ],
        'Property Dispute' => [
            'label' => 'Property Dispute',
            'fields' => [
                ['name' => 'property_involved', 'label' => 'Property or Area Involved', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Fence line, parking space, lot boundary'],
                ['name' => 'property_relationship', 'label' => 'Relationship to the Property', 'type' => 'select', 'required' => true, 'options' => ['Owner', 'Tenant', 'Neighbor', 'Caretaker', 'Relative', 'Other']],
                ['name' => 'property_dispute_issue', 'label' => 'Main Issue', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Encroachment, access blockage, boundary conflict'],
            ],
        ],
        'Noise Complaint' => [
            'label' => 'Noise Complaint',
            'fields' => [
                ['name' => 'noise_source', 'label' => 'Source of Noise', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Videoke, construction, vehicle, event'],
                ['name' => 'noise_frequency', 'label' => 'How Often It Happens', 'type' => 'select', 'required' => true, 'options' => ['One-time', 'Occasional', 'Frequent', 'Ongoing']],
                ['name' => 'noise_time_pattern', 'label' => 'Usual Time or Schedule', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. 10:00 PM onwards, early morning'],
            ],
        ],
        'Physical Altercation' => [
            'label' => 'Physical Altercation',
            'fields' => [
                ['name' => 'altercation_injuries', 'label' => 'Were There Any Injuries?', 'type' => 'select', 'required' => true, 'options' => ['Yes', 'No', 'Unsure']],
                ['name' => 'altercation_medical_help', 'label' => 'Was Medical Assistance Needed?', 'type' => 'select', 'required' => true, 'options' => ['Yes', 'No', 'Unsure']],
                ['name' => 'altercation_weapons', 'label' => 'Were Any Weapons or Dangerous Objects Involved?', 'type' => 'select', 'required' => true, 'options' => ['Yes', 'No', 'Unsure']],
            ],
        ],
        'Other' => [
            'label' => 'Other',
            'fields' => [
                ['name' => 'other_specific_concern', 'label' => 'Specific Concern', 'type' => 'text', 'required' => true, 'placeholder' => 'Describe the concern briefly'],
                ['name' => 'other_how_long', 'label' => 'How Long Has This Been Happening?', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. 2 weeks, since last month'],
                ['name' => 'other_impact', 'label' => 'Impact on You or the Community', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Describe the effect or harm caused'],
            ],
        ],
    ];
}

function complaintTypePublicConfig(): array
{
    return complaintTypeDefinitions();
}

function complaintTypeNormalizeFieldValue($value): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function complaintTypeValidateAndCollect(?string $natureOfComplaint, ?string $natureOther, array $source): array
{
    $definitions = complaintTypeDefinitions();
    $natureOfComplaint = trim((string)$natureOfComplaint);
    $natureOther = trim((string)$natureOther);

    if ($natureOfComplaint === '' || !isset($definitions[$natureOfComplaint])) {
        throw new InvalidArgumentException('Nature of complaint is required.');
    }

    $complaintType = $natureOfComplaint === 'Other' ? $natureOther : $natureOfComplaint;
    $complaintType = trim((string)$complaintType);
    if ($complaintType === '') {
        throw new InvalidArgumentException('Please specify the complaint type.');
    }

    $definition = $definitions[$natureOfComplaint];
    $fields = [];
    foreach ($definition['fields'] as $fieldDef) {
        $name = (string)$fieldDef['name'];
        $label = (string)$fieldDef['label'];
        $value = complaintTypeNormalizeFieldValue($source[$name] ?? '');
        $required = !empty($fieldDef['required']);

        if ($required && $value === null) {
            throw new InvalidArgumentException($label . ' is required.');
        }

        if ($value !== null && isset($fieldDef['options']) && is_array($fieldDef['options'])) {
            $allowed = array_map('strval', $fieldDef['options']);
            if (!in_array($value, $allowed, true)) {
                throw new InvalidArgumentException($label . ' is invalid.');
            }
        }

        if ($value !== null) {
            $fields[] = [
                'name' => $name,
                'label' => $label,
                'value' => $value,
            ];
        }
    }

    return [
        'selected_type' => $natureOfComplaint,
        'complaint_type' => $complaintType,
        'fields' => $fields,
    ];
}

function complaintTypeBuildCaseDetails(?string $narration, array $meta): string
{
    $payload = [
        'version' => 1,
        'selected_type' => (string)($meta['selected_type'] ?? ''),
        'complaint_type' => (string)($meta['complaint_type'] ?? ''),
        'fields' => array_values(array_filter($meta['fields'] ?? [], static function ($field): bool {
            return is_array($field) && trim((string)($field['label'] ?? '')) !== '' && trim((string)($field['value'] ?? '')) !== '';
        })),
    ];

    $narration = trim((string)$narration);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return $narration;
    }

    return $narration
        . "\n\n--COMPLAINT_META_START--\n"
        . $json
        . "\n--COMPLAINT_META_END--";
}

function complaintTypeParseCaseDetails(?string $caseDetails): array
{
    $raw = (string)$caseDetails;
    $pattern = '/\n*--COMPLAINT_META_START--\n(.*?)\n--COMPLAINT_META_END--\s*$/s';
    if (!preg_match($pattern, $raw, $matches)) {
        return [
            'narration' => trim($raw),
            'meta' => null,
            'fields' => [],
        ];
    }

    $narration = trim((string)preg_replace($pattern, '', $raw));
    $decoded = json_decode((string)$matches[1], true);
    if (!is_array($decoded)) {
        return [
            'narration' => $narration,
            'meta' => null,
            'fields' => [],
        ];
    }

    $fields = [];
    foreach (($decoded['fields'] ?? []) as $field) {
        if (!is_array($field)) {
            continue;
        }
        $label = trim((string)($field['label'] ?? ''));
        $value = trim((string)($field['value'] ?? ''));
        if ($label === '' || $value === '') {
            continue;
        }
        $fields[] = [
            'name' => trim((string)($field['name'] ?? '')),
            'label' => $label,
            'value' => $value,
        ];
    }

    return [
        'narration' => $narration,
        'meta' => $decoded,
        'fields' => $fields,
    ];
}
