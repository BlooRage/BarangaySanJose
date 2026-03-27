<?php
require_once __DIR__ . '/security.php';

if (!function_exists('cms_content_pages_table')) {
    function cms_content_pages_table(): string
    {
        return 'websitecontenttbl';
    }
}

if (!function_exists('cms_content_requests_table')) {
    function cms_content_requests_table(): string
    {
        return 'websitecontentrequeststbl';
    }
}

if (!function_exists('cms_content_page_definitions')) {
    function cms_content_page_definitions(): array
    {
        static $definitions = null;
        if ($definitions !== null) {
            return $definitions;
        }

        $definitions = [
            'announcements' => [
                'label' => 'News Page',
                'image_fields' => [
                    'banner_image' => ['ratio' => [4500, 1281]],
                ],
            ],
            'home' => [
                'label' => 'Home Page',
                'image_fields' => [
                    'banner_image' => ['ratio' => [1500, 800]],
                    'about_image' => ['ratio' => [1125, 1575]],
                ],
            ],
            'government' => [
                'label' => 'Government Page',
                'image_fields' => [
                    'banner_image' => ['ratio' => [1440, 410]],
                    'punong_barangay_image' => ['ratio' => [4000, 6000]],
                    'officials[*].image' => ['ratio' => [4000, 6000]],
                ],
            ],
            'services' => [
                'label' => 'Services',
                'image_fields' => [
                    'banner_image' => ['ratio' => [4500, 1281]],
                ],
            ],
            'faq' => [
                'label' => 'FAQ',
                'image_fields' => [
                    'banner_image' => ['ratio' => [1440, 410]],
                ],
            ],
            'contact' => [
                'label' => 'Contact',
                'image_fields' => [
                    'banner_image' => ['ratio' => [4499, 1281]],
                ],
            ],
            'login' => [
                'label' => 'Login',
                'image_fields' => [
                    'login_image' => ['ratio' => [1587, 2245]],
                    'register_image' => ['ratio' => [1587, 2245]],
                ],
            ],
        ];

        return $definitions;
    }
}

if (!function_exists('cms_content_nav_page_keys')) {
    function cms_content_nav_page_keys(): array
    {
        return ['requests', 'home', 'government', 'services', 'faq', 'contact', 'login'];
    }
}

if (!function_exists('cms_content_editable_page_keys')) {
    function cms_content_editable_page_keys(): array
    {
        return ['announcements', 'home', 'government', 'services', 'faq', 'contact', 'login'];
    }
}

if (!function_exists('cms_content_normalize_page_key')) {
    function cms_content_normalize_page_key(string $pageKey): string
    {
        $pageKey = strtolower(trim($pageKey));
        return in_array($pageKey, cms_content_editable_page_keys(), true) ? $pageKey : '';
    }
}

if (!function_exists('cms_content_page_label')) {
    function cms_content_page_label(string $pageKey): string
    {
        $definitions = cms_content_page_definitions();
        return (string)($definitions[$pageKey]['label'] ?? $pageKey);
    }
}

if (!function_exists('cms_content_request_statuses')) {
    function cms_content_request_statuses(): array
    {
        return ['draft', 'pending', 'approved', 'denied', 'archived'];
    }
}

if (!function_exists('cms_content_default_payloads')) {
    function cms_content_default_payloads(): array
    {
        static $defaults = null;
        if ($defaults !== null) {
            return $defaults;
        }

        $defaults = [
            'announcements' => [
                'banner_image' => 'Images/News_Banner.jpg',
                'banner_title_html' => 'News and Announcements',
                'banner_message_html' => 'Barangay San Jose values an informed and aware community. The barangay ensures residents receive timely updates on matters that affect daily life and safety.',
            ],
            'home' => [
                'banner_image' => 'Images/Home_Banner.png',
                'about_message_html' => '<p>Ipinapakita nito ang matibay na paninindigan ng Barangay San Jose sa maayos, tapat, at makabagong pamamahala bilang simula ng tunay na pagbabago. Through responsible leadership and innovation, ang barangay ay nagsisilbing gabay at sandigan ng komunidad tungo sa mas maayos na kinabukasan, kung saan ang bawat desisyon ay may malinaw na layuning maghatid ng pag-asa at serbisyo sa mamamayan.</p><p>By strengthening systems and aligning programs with the social and economic realities of the community, Barangay San Jose ensures that public service is not only efficient but also compassionate and inclusive. Sa patuloy na pakikinig sa boses ng mamamayan at pag-angkop sa kanilang pangangailangan, nagiging daan ang pamahalaang barangay sa mas patas na oportunidad at mas maayos na kalidad ng pamumuhay.</p><p>Through shared responsibility and collective action, Barangay San Jose envisions a resilient, empowered, and united community. Sa pagtutulungan ng pamunuan at ng mga residente, binubuo ang isang komunidad na may tiwala, pagkakaisa, at lakas ng loob na harapin ang mga hamon—isang bagong simula patungo sa pangmatagalang kaunlaran para sa kasalukuyan at sa mga susunod na henerasyon.</p>',
                'about_image' => 'Images/About_US.jpg',
                'mission_message_html' => 'The mission of Barangay San Jose centers on serving residents with integrity and responsibility. The barangay commits to fair and equal access to public services and deliver timely assistance for documents, concerns, and community needs. The mission emphasizes peace, order, and public safety. Leaders act with transparency in every decision. Public welfare remains the priority of every program. Service to the people defines every action of the barangay.',
                'vision_message_html' => 'Barangay San Jose envisions a safe, orderly, and united community. The barangay aims to build strong cooperation among residents and leaders. A peaceful environment supports daily living and local progress. Transparent governance strengthens public trust. Organized services support long term community stability. Residents remain informed and involved in local matters. Development aligns with community values and needs. The vision reflects a future built on service and unity.',
                'history_message_html' => '<p>Barangay San Jose began as a rural settlement formed by early families who relied on farming and shared labor. The community developed through cooperation, kinship, and mutual support among residents. Local elders guided decisions to maintain order and resolve concerns. These early practices shaped a strong sense of unity and responsibility. Community life centered on shared values and collective effort.</p><p>Barangay San Jose later gained formal recognition under the municipality of Rodriguez, Rizal. Local governance structures formed to manage public records, basic services, and community order. Barangay officials coordinated programs and addressed disputes to support daily needs. The establishment of a barangay office strengthened service delivery. Public service became more organized and accessible to residents.</p><p>As Rodriguez (formerly known as Montalban) continued to grow, Barangay San Jose also expanded in population and development. Services adapted to meet changing social and administrative needs. Infrastructure, programs, and leadership roles improved to support community welfare. The barangay maintained its service oriented role amid growth. Today, Barangay San Jose continues to uphold its history of service, cooperation, and local governance.</p>',
                'history_images' => [
                    'Images/Our_History_1.jpg',
                    'Images/Our_History_2.jpg',
                ],
            ],
            'government' => [
                'banner_image' => 'Images/Government_Banner.png',
                'banner_title_html' => 'Government',
                'banner_message_html' => 'Barangay San Jose continues to serve the people with dedication, fairness, and responsibility. We deliver programs that protect safety, support needs, and keep the community together. Expect transparent, responsive service every day.',
                'punong_barangay_image' => 'Images/Officials/Hon Kap Glen_MAC8515 copy 2.jpg',
                'punong_barangay_name_html' => 'Glenn S. Evangelista',
                'punong_barangay_position_html' => 'Barangay Captain',
                'punong_barangay_welcome_message_html' => '<p>Welcome to the official website of Barangay San Jose. This platform represents the barangay’s commitment to embracing digital systems to improve public service delivery and strengthen community engagement. In an increasingly digital world, the use of technology allows the barangay to provide faster, more organized, and more accessible services to its residents.</p><p>Through this website, our residents can conveniently access important information, submit requests, and stay updated on barangay announcements and activities. Digital systems help reduce paperwork, shorten processing time, and ensure that records are properly managed and secured. These improvements are essential in serving a growing community and responding efficiently to its needs.</p><p>This initiative reflects the barangay’s dedication to transparency, efficiency, and inclusive governance. By integrating digital solutions into everyday operations, Barangay San Jose aims to make public services more accessible to everyone and to build a more connected and responsive community.</p>',
                'officials' => [
                    ['name_html' => 'Minerva D. Quita', 'position_html' => 'Barangay Secretary', 'image' => 'Images/Officials/Sec Minnie_MAC8682 copy2.jpg'],
                    ['name_html' => 'Janet B. De Vera', 'position_html' => 'Sangguniang Barangay Member', 'image' => 'Images/Officials/Hon Kagawad Janet_MAC8871 copy2.jpg'],
                    ['name_html' => 'Elmer E. Espiritu', 'position_html' => 'Sangguniang Barangay Member', 'image' => 'Images/Officials/Hon Kagawad Elmer_MAC9326 copy2.jpg'],
                    ['name_html' => 'Roland C. Nery', 'position_html' => 'Sangguniang Barangay Member', 'image' => 'Images/Officials/Hon Kagawad Nery_MAC8919 copy 2.jpg'],
                    ['name_html' => 'James Philip C. Marcelo', 'position_html' => 'Sangguniang Barangay Member', 'image' => 'Images/Officials/Hon Kagawad Philip_MAC9117 copy2.jpg'],
                    ['name_html' => 'Bernardo B. Leona', 'position_html' => 'Sangguniang Barangay Member', 'image' => 'Images/Officials/Hon Kagawad Bernard_MAC9048 copy 2.jpg'],
                    ['name_html' => 'Marcial C. Pastoral', 'position_html' => 'Sangguniang Barangay Member', 'image' => 'Images/Officials/Hon Kagawad Mar_MAC8791 copy 2.jpg'],
                    ['name_html' => 'Vernon E. Relox Jr.', 'position_html' => 'Sangguniang Barangay Member', 'image' => 'Images/Officials/Hon Kagawad Vernon_MAC9089 copy3.jpg'],
                    ['name_html' => 'Generous Joy J. Bauto', 'position_html' => 'SK Chairperson', 'image' => 'Images/Officials/SK Chairman Joy_MAC9288 copy3.jpg'],
                ],
                'areas' => [
                    ['title_html' => 'Area 01', 'description_html' => 'San Jose Proper'],
                    ['title_html' => 'Area 1A', 'description_html' => 'Litex Village | Abatex Christine Creek | Med. Heights'],
                    ['title_html' => 'Area 02', 'description_html' => 'VFW | Amychelle | Christine Villa Parnshey | Villa Ana | Zaniga Farm'],
                    ['title_html' => 'Area 03', 'description_html' => 'Relocation'],
                    ['title_html' => 'Area 04', 'description_html' => 'Kasiglahan: Phase 1-B | Phase 1-C | Phase 1-D | Phase 1-M | Phase 1-A'],
                    ['title_html' => 'Area 05', 'description_html' => 'Kasiglahan: Phase 1-K | Phase 1K1 | Phase 1K2 | Phase 1-E | Phase 1-G'],
                    ['title_html' => 'Area 06', 'description_html' => 'Sub-Urban | Metro Manila Hills'],
                ],
            ],
            'services' => [
                'banner_image' => 'Images/Services_Banner.jpg',
                'banner_title_html' => 'Services',
                'banner_message_html' => 'Barangay San Jose maintains fast, fair, and accessible front-line services. We handle clearances, certificates, IDs, and help with safety or community concerns.',
                'services' => [
                    ['title_html' => 'Certificates', 'description_html' => 'Request official barangay certificates for school, work, or personal use. Verified and officially recorded.'],
                    ['title_html' => 'Clearances', 'description_html' => 'Apply for barangay clearance for employment, business, or legal purposes.'],
                    ['title_html' => 'Barangay ID', 'description_html' => 'Apply for a Barangay San Jose ID as proof of residency. Recognized for local transactions.'],
                    ['title_html' => 'Appointments', 'description_html' => 'Schedule a visit with the barangay office for specific services or concerns. Organized and time efficient.'],
                    ['title_html' => 'Complaint', 'description_html' => 'Submit complaints to report issues affecting peace, order, or community welfare.'],
                ],
            ],
            'faq' => [
                'banner_image' => 'Images/FAQ_Banner.png',
                'banner_title_html' => 'Frequently Asked Questions',
                'banner_message_html' => 'Find straightforward answers about barangay certificates, ID applications, appointments, and other services. We keep responses short so you can quickly move on to filing requests or reaching out to the office.',
                'faq_items' => [
                    ['question' => '', 'answer' => ''],
                ],
            ],
            'contact' => [
                'banner_image' => 'Images/Contact_Banner.jpg',
                'banner_title_html' => 'Contact',
                'banner_message_html' => 'Barangay San Jose is committed to providing accessible and reliable channels of communication for all residents.',
                'emergency_title_html' => 'Barangay Health Emergency Response Team',
                'emergency_description_html' => 'Listed below are the official Barangay Health Response Team emergency hotlines for each area. These contact numbers are provided to ensure immediate response to health-related emergencies and urgent situations within the community.',
                'emergency_hotlines' => [
                    ['title_html' => 'Area 01', 'number_html' => '+63 963 164 4357'],
                    ['title_html' => 'Area 02', 'number_html' => '+63 963 164 4358'],
                    ['title_html' => 'Area 03', 'number_html' => '+63 963 164 4359'],
                    ['title_html' => 'Area 04 & 05', 'number_html' => '+63 938 455 2877'],
                    ['title_html' => 'Area 06', 'number_html' => '+63 963 164 4356'],
                ],
                'area_hotlines' => [
                    ['title_html' => 'COMMAND CENTER', 'location_html' => 'Montalban Municipality', 'number_html' => '+63 951 188 7878'],
                    ['title_html' => 'AREA 01', 'location_html' => 'SAN JOSE PROPER', 'number_html' => '+63 981 331 0263'],
                    ['title_html' => 'AREA 1A', 'location_html' => 'LITEX Village | ABATEX Christine Creek | MED. HEIGHTS', 'number_html' => '+63 951 210 1957'],
                    ['title_html' => 'AREA 02', 'location_html' => 'VFW | Amychelle | Christine Villa Parnshey | Villa Ana | Zaniga Farm', 'number_html' => '+63 930 636 7957'],
                    ['title_html' => 'AREA 03', 'location_html' => 'RELOCATION', 'number_html' => '+63 961 331 0286'],
                    ['title_html' => 'AREA 04', 'location_html' => 'Kasiglahan: Phase 1-B | Phase 1-C | Phase 1-D | Phase 1-M | Phase 1-A', 'number_html' => '+63 970 306 3523'],
                    ['title_html' => 'AREA 05', 'location_html' => 'Kasiglahan: Phase 1-K | Phase 1K1 | Phase 1K2 | Phase 1-E | Phase 1-G', 'number_html' => '+63 930 457 7488'],
                    ['title_html' => 'AREA 06', 'location_html' => 'Sub-Urban | Metro Manila Hills', 'number_html' => '+63 963 460 5277'],
                ],
            ],
            'login' => [
                'login_image' => 'Images/LoginSignIn.png',
                'register_image' => 'Images/LoginRegister.png',
            ],
        ];

        return $defaults;
    }
}

if (!function_exists('cms_content_default_payload')) {
    function cms_content_default_payload(string $pageKey): array
    {
        $defaults = cms_content_default_payloads();
        return is_array($defaults[$pageKey] ?? null) ? $defaults[$pageKey] : [];
    }
}

if (!function_exists('cms_content_encode_json')) {
    function cms_content_encode_json(array $value): string
    {
        return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('cms_content_decode_json')) {
    function cms_content_decode_json(?string $json): array
    {
        $decoded = json_decode((string)$json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('cms_content_clean_html')) {
    function cms_content_clean_html(?string $value): string
    {
        $html = trim((string)$value);
        if ($html === '') {
            return '';
        }

        $html = preg_replace('#<\s*(script|style|iframe|object|embed)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html) ?? $html;
        $html = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/isu', '', $html) ?? $html;
        $html = preg_replace('/\son[a-z]+\s*=\s*[^ >]+/isu', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/iu', '', $html) ?? $html;
        return trim($html);
    }
}

if (!function_exists('cms_content_clean_text')) {
    function cms_content_clean_text(?string $value): string
    {
        return trim(strip_tags((string)$value));
    }
}

if (!function_exists('cms_content_normalize_rows')) {
    function cms_content_normalize_rows(array $rows, array $fieldMap, int $minRows = 0): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [];
            $hasAny = false;
            foreach ($fieldMap as $field => $type) {
                $value = (string)($row[$field] ?? '');
                if ($type === 'html') {
                    $item[$field] = cms_content_clean_html($value);
                } else {
                    $item[$field] = cms_content_clean_text($value);
                }
                if ($item[$field] !== '') {
                    $hasAny = true;
                }
            }
            if ($hasAny) {
                $normalized[] = $item;
            }
        }

        while (count($normalized) < $minRows) {
            $empty = [];
            foreach ($fieldMap as $field => $type) {
                $empty[$field] = '';
            }
            $normalized[] = $empty;
        }

        return $normalized;
    }
}

if (!function_exists('cms_content_normalize_payload')) {
    function cms_content_normalize_payload(string $pageKey, array $payload): array
    {
        $defaults = cms_content_default_payload($pageKey);

        switch ($pageKey) {
            case 'announcements':
                return [
                    'banner_image' => trim((string)($payload['banner_image'] ?? $defaults['banner_image'] ?? '')),
                    'banner_title_html' => cms_content_clean_html((string)($payload['banner_title_html'] ?? $defaults['banner_title_html'] ?? '')),
                    'banner_message_html' => cms_content_clean_html((string)($payload['banner_message_html'] ?? $defaults['banner_message_html'] ?? '')),
                ];

            case 'home':
                return [
                    'banner_image' => trim((string)($payload['banner_image'] ?? $defaults['banner_image'] ?? '')),
                    'about_message_html' => cms_content_clean_html((string)($payload['about_message_html'] ?? $defaults['about_message_html'] ?? '')),
                    'about_image' => trim((string)($payload['about_image'] ?? $defaults['about_image'] ?? '')),
                    'mission_message_html' => cms_content_clean_html((string)($payload['mission_message_html'] ?? $defaults['mission_message_html'] ?? '')),
                    'vision_message_html' => cms_content_clean_html((string)($payload['vision_message_html'] ?? $defaults['vision_message_html'] ?? '')),
                    'history_message_html' => cms_content_clean_html((string)($payload['history_message_html'] ?? $defaults['history_message_html'] ?? '')),
                    'history_images' => is_array($defaults['history_images'] ?? null) ? array_values($defaults['history_images']) : [],
                ];

            case 'government':
                return [
                    'banner_image' => trim((string)($payload['banner_image'] ?? $defaults['banner_image'] ?? '')),
                    'banner_title_html' => cms_content_clean_html((string)($payload['banner_title_html'] ?? $defaults['banner_title_html'] ?? '')),
                    'banner_message_html' => cms_content_clean_html((string)($payload['banner_message_html'] ?? $defaults['banner_message_html'] ?? '')),
                    'punong_barangay_image' => trim((string)($payload['punong_barangay_image'] ?? $defaults['punong_barangay_image'] ?? '')),
                    'punong_barangay_name_html' => cms_content_clean_html((string)($payload['punong_barangay_name_html'] ?? $defaults['punong_barangay_name_html'] ?? '')),
                    'punong_barangay_position_html' => cms_content_clean_html((string)($payload['punong_barangay_position_html'] ?? $defaults['punong_barangay_position_html'] ?? '')),
                    'punong_barangay_welcome_message_html' => cms_content_clean_html((string)($payload['punong_barangay_welcome_message_html'] ?? $defaults['punong_barangay_welcome_message_html'] ?? '')),
                    'officials' => cms_content_normalize_rows((array)($payload['officials'] ?? $defaults['officials'] ?? []), [
                        'name_html' => 'html',
                        'position_html' => 'html',
                        'image' => 'text',
                    ], 1),
                    'areas' => cms_content_normalize_rows((array)($payload['areas'] ?? $defaults['areas'] ?? []), [
                        'title_html' => 'html',
                        'description_html' => 'html',
                    ], 1),
                ];

            case 'services':
                $serviceDefaults = is_array($defaults['services'] ?? null) ? $defaults['services'] : [];
                $incomingServices = is_array($payload['services'] ?? null) ? $payload['services'] : [];
                $services = [];
                foreach ($serviceDefaults as $idx => $defaultService) {
                    $incoming = is_array($incomingServices[$idx] ?? null) ? $incomingServices[$idx] : [];
                    $services[] = [
                        'title_html' => cms_content_clean_html((string)($defaultService['title_html'] ?? '')),
                        'description_html' => cms_content_clean_html((string)($incoming['description_html'] ?? $defaultService['description_html'] ?? '')),
                    ];
                }
                return [
                    'banner_image' => trim((string)($payload['banner_image'] ?? $defaults['banner_image'] ?? '')),
                    'banner_title_html' => cms_content_clean_html((string)($payload['banner_title_html'] ?? $defaults['banner_title_html'] ?? '')),
                    'banner_message_html' => cms_content_clean_html((string)($payload['banner_message_html'] ?? $defaults['banner_message_html'] ?? '')),
                    'services' => $services,
                ];

            case 'faq':
                return [
                    'banner_image' => trim((string)($payload['banner_image'] ?? $defaults['banner_image'] ?? '')),
                    'banner_title_html' => cms_content_clean_html((string)($payload['banner_title_html'] ?? $defaults['banner_title_html'] ?? '')),
                    'banner_message_html' => cms_content_clean_html((string)($payload['banner_message_html'] ?? $defaults['banner_message_html'] ?? '')),
                    'faq_items' => cms_content_normalize_rows((array)($payload['faq_items'] ?? $defaults['faq_items'] ?? []), [
                        'question' => 'text',
                        'answer' => 'html',
                    ], 1),
                ];

            case 'contact':
                return [
                    'banner_image' => trim((string)($payload['banner_image'] ?? $defaults['banner_image'] ?? '')),
                    'banner_title_html' => cms_content_clean_html((string)($payload['banner_title_html'] ?? $defaults['banner_title_html'] ?? '')),
                    'banner_message_html' => cms_content_clean_html((string)($payload['banner_message_html'] ?? $defaults['banner_message_html'] ?? '')),
                    'emergency_title_html' => cms_content_clean_html((string)($payload['emergency_title_html'] ?? $defaults['emergency_title_html'] ?? '')),
                    'emergency_description_html' => cms_content_clean_html((string)($payload['emergency_description_html'] ?? $defaults['emergency_description_html'] ?? '')),
                    'emergency_hotlines' => cms_content_normalize_rows((array)($payload['emergency_hotlines'] ?? $defaults['emergency_hotlines'] ?? []), [
                        'title_html' => 'html',
                        'number_html' => 'html',
                    ], 1),
                    'area_hotlines' => cms_content_normalize_rows((array)($payload['area_hotlines'] ?? $defaults['area_hotlines'] ?? []), [
                        'title_html' => 'html',
                        'location_html' => 'html',
                        'number_html' => 'html',
                    ], 1),
                ];

            case 'login':
                return [
                    'login_image' => trim((string)($payload['login_image'] ?? $defaults['login_image'] ?? '')),
                    'register_image' => trim((string)($payload['register_image'] ?? $defaults['register_image'] ?? '')),
                ];
        }

        return $defaults;
    }
}

if (!function_exists('cms_content_public_asset_url')) {
    function cms_content_public_asset_url(?string $path): string
    {
        $path = trim((string)$path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^(?:https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }
        return appUrl($path);
    }
}

if (!function_exists('cms_content_request_id')) {
    function cms_content_request_id(): string
    {
        return 'CMS-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }
}

if (!function_exists('cms_content_column_exists')) {
    function cms_content_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $tableEsc = $conn->real_escape_string($table);
        $columnEsc = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

if (!function_exists('cms_content_ensure_schema')) {
    function cms_content_ensure_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $pagesTable = cms_content_pages_table();
        $requestsTable = cms_content_requests_table();

        $conn->query("
            CREATE TABLE IF NOT EXISTS {$pagesTable} (
                page_key VARCHAR(50) NOT NULL,
                page_label VARCHAR(120) NOT NULL,
                content_json LONGTEXT NOT NULL,
                updated_by_user_id VARCHAR(20) DEFAULT NULL,
                updated_by_label VARCHAR(190) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (page_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS {$requestsTable} (
                request_id VARCHAR(40) NOT NULL,
                page_key VARCHAR(50) NOT NULL,
                page_label VARCHAR(120) NOT NULL,
                content_json LONGTEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                created_by_user_id VARCHAR(20) DEFAULT NULL,
                created_by_label VARCHAR(190) DEFAULT NULL,
                created_by_role VARCHAR(100) DEFAULT NULL,
                reviewed_by_user_id VARCHAR(20) DEFAULT NULL,
                reviewed_by_label VARCHAR(190) DEFAULT NULL,
                review_note TEXT DEFAULT NULL,
                submitted_at DATETIME DEFAULT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (request_id),
                KEY idx_page_status (page_key, status),
                KEY idx_creator_status (created_by_user_id, status),
                KEY idx_updated_at (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        if (!cms_content_column_exists($conn, $requestsTable, 'archived_from_status')) {
            $conn->query("ALTER TABLE {$requestsTable} ADD COLUMN archived_from_status VARCHAR(20) DEFAULT NULL AFTER status");
        }
        if (!cms_content_column_exists($conn, $requestsTable, 'archived_by_user_id')) {
            $conn->query("ALTER TABLE {$requestsTable} ADD COLUMN archived_by_user_id VARCHAR(20) DEFAULT NULL AFTER reviewed_by_label");
        }
        if (!cms_content_column_exists($conn, $requestsTable, 'archived_by_label')) {
            $conn->query("ALTER TABLE {$requestsTable} ADD COLUMN archived_by_label VARCHAR(190) DEFAULT NULL AFTER archived_by_user_id");
        }
        if (!cms_content_column_exists($conn, $requestsTable, 'archived_at')) {
            $conn->query("ALTER TABLE {$requestsTable} ADD COLUMN archived_at DATETIME DEFAULT NULL AFTER reviewed_at");
        }

        $defaults = cms_content_default_payloads();
        foreach ($defaults as $pageKey => $payload) {
            $pageLabel = cms_content_page_label($pageKey);
            $normalized = cms_content_normalize_payload($pageKey, $payload);
            $stmt = $conn->prepare("
                INSERT INTO {$pagesTable} (page_key, page_label, content_json, updated_by_user_id, updated_by_label)
                VALUES (?, ?, ?, NULL, NULL)
                ON DUPLICATE KEY UPDATE page_label = VALUES(page_label)
            ");
            if ($stmt) {
                $json = cms_content_encode_json($normalized);
                $stmt->bind_param('sss', $pageKey, $pageLabel, $json);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

if (!function_exists('cms_content_page')) {
    function cms_content_page(mysqli $conn, string $pageKey): array
    {
        cms_content_ensure_schema($conn);
        $pageKey = cms_content_normalize_page_key($pageKey);
        if ($pageKey === '') {
            return [];
        }

        $table = cms_content_pages_table();
        $stmt = $conn->prepare("SELECT content_json FROM {$table} WHERE page_key = ? LIMIT 1");
        if (!$stmt) {
            return cms_content_default_payload($pageKey);
        }
        $stmt->bind_param('s', $pageKey);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return cms_content_default_payload($pageKey);
        }

        return cms_content_normalize_payload($pageKey, cms_content_decode_json((string)($row['content_json'] ?? '')));
    }
}

if (!function_exists('cms_content_page_with_context')) {
    function cms_content_page_with_context(mysqli $conn, string $pageKey): array
    {
        $payload = cms_content_page($conn, $pageKey);
        return cms_content_payload_with_context($conn, $pageKey, $payload);
    }
}

if (!function_exists('cms_content_payload_with_context')) {
    function cms_content_payload_with_context(mysqli $conn, string $pageKey, array $payload): array
    {
        $pageKey = cms_content_normalize_page_key($pageKey);
        if ($pageKey === '') {
            return [];
        }

        $payload = cms_content_normalize_payload($pageKey, $payload);
        if ($pageKey !== 'home') {
            return $payload;
        }

        $government = cms_content_page($conn, 'government');
        $council = [];
        $punongName = cms_content_clean_text((string)($government['punong_barangay_name_html'] ?? ''));
        $punongPosition = cms_content_clean_text((string)($government['punong_barangay_position_html'] ?? 'Punong Barangay'));
        $punongImage = trim((string)($government['punong_barangay_image'] ?? ''));
        if ($punongName !== '') {
            $council[] = [
                'name' => $punongName,
                'position' => $punongPosition,
                'image' => $punongImage,
            ];
        }
        foreach ((array)($government['officials'] ?? []) as $official) {
            if (!is_array($official)) {
                continue;
            }
            $name = cms_content_clean_text((string)($official['name_html'] ?? ''));
            $position = cms_content_clean_text((string)($official['position_html'] ?? ''));
            $image = trim((string)($official['image'] ?? ''));
            if ($name === '') {
                continue;
            }
            $council[] = [
                'name' => $name,
                'position' => $position,
                'image' => $image,
            ];
        }
        $payload['council_members'] = $council;
        return $payload;
    }
}

if (!function_exists('cms_content_current_user_display')) {
    function cms_content_current_user_display(mysqli $conn, string $userId, string $fallback): string
    {
        $userId = trim($userId);
        if ($userId === '') {
            return $fallback;
        }

        $hasPositionAccess = cms_content_column_exists($conn, 'officialinformationtbl', 'position_access');
        $selectPosition = $hasPositionAccess ? "position_access" : "NULL AS position_access";

        $stmt = $conn->prepare("
            SELECT firstname, middlename, lastname, suffix, role_access, {$selectPosition}
            FROM officialinformationtbl
            WHERE user_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return $fallback;
        }

        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return $fallback;
        }

        $firstName = trim((string)($row['firstname'] ?? ''));
        $middleName = trim((string)($row['middlename'] ?? ''));
        $lastName = trim((string)($row['lastname'] ?? ''));
        $suffix = trim((string)($row['suffix'] ?? ''));
        $parts = array_filter([$lastName, trim($firstName . ' ' . $middleName), $suffix], static fn($value) => trim((string)$value) !== '');
        $name = trim(implode(', ', array_filter([
            $lastName,
            trim($firstName . ' ' . $middleName) . ($suffix !== '' ? ' ' . $suffix : ''),
        ], static fn($value) => trim((string)$value) !== '')));
        if ($name === '') {
            $name = $fallback;
        }
        return $name;
    }
}

if (!function_exists('cms_content_current_user_position')) {
    function cms_content_current_user_position(mysqli $conn, string $userId): string
    {
        $userId = trim($userId);
        if ($userId === '') {
            return '';
        }

        $hasPositionAccess = cms_content_column_exists($conn, 'officialinformationtbl', 'position_access');
        $selectPosition = $hasPositionAccess ? "position_access" : "role_access";
        $stmt = $conn->prepare("SELECT {$selectPosition} AS position_access FROM officialinformationtbl WHERE user_id = ? LIMIT 1");
        if (!$stmt) {
            return '';
        }
        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return trim((string)($row['position_access'] ?? ''));
    }
}

if (!function_exists('cms_content_can_review')) {
    function cms_content_can_review(mysqli $conn, string $userId, string $sessionRole): bool
    {
        $role = strtolower(trim($sessionRole));
        if ($role === 'superadmin') {
            return true;
        }
        $position = strtolower(cms_content_current_user_position($conn, $userId));
        return $position === 'barangay secretary';
    }
}

if (!function_exists('cms_content_request_is_owned_by')) {
    function cms_content_request_is_owned_by(array $request, string $userId): bool
    {
        $userId = trim($userId);
        if ($userId === '') {
            return false;
        }

        return trim((string)($request['created_by_user_id'] ?? '')) === $userId;
    }
}

if (!function_exists('cms_content_request_is_viewable_by')) {
    function cms_content_request_is_viewable_by(array $request, string $userId, bool $canReview): bool
    {
        return $canReview || cms_content_request_is_owned_by($request, $userId);
    }
}

if (!function_exists('cms_content_request_is_editable_by')) {
    function cms_content_request_is_editable_by(array $request, string $userId): bool
    {
        if (!cms_content_request_is_owned_by($request, $userId)) {
            return false;
        }

        $status = strtolower(trim((string)($request['status'] ?? 'draft')));
        return in_array($status, ['draft', 'denied'], true);
    }
}

if (!function_exists('cms_content_request_is_archivable_by')) {
    function cms_content_request_is_archivable_by(array $request, string $userId, bool $canReview, bool $isLiveVersion = false): bool
    {
        $status = strtolower(trim((string)($request['status'] ?? 'draft')));
        if ($status === 'archived') {
            return false;
        }
        if (!in_array($status, ['draft', 'pending', 'approved', 'denied'], true)) {
            return false;
        }
        if ($status === 'approved' && $isLiveVersion) {
            return false;
        }

        return $canReview || cms_content_request_is_owned_by($request, $userId);
    }
}

if (!function_exists('cms_content_request_is_restorable_by')) {
    function cms_content_request_is_restorable_by(array $request, string $userId, bool $canReview): bool
    {
        if (strtolower(trim((string)($request['status'] ?? 'draft'))) !== 'archived') {
            return false;
        }

        return $canReview || cms_content_request_is_owned_by($request, $userId);
    }
}

if (!function_exists('cms_content_requests')) {
    function cms_content_requests(mysqli $conn): array
    {
        cms_content_ensure_schema($conn);
        $table = cms_content_requests_table();
        $result = $conn->query("SELECT * FROM {$table} ORDER BY updated_at DESC, created_at DESC");
        if (!($result instanceof mysqli_result)) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $pageKey = cms_content_normalize_page_key((string)($row['page_key'] ?? ''));
            if ($pageKey === '') {
                continue;
            }
            $rows[] = [
                'request_id' => trim((string)($row['request_id'] ?? '')),
                'page_key' => $pageKey,
                'page_label' => trim((string)($row['page_label'] ?? cms_content_page_label($pageKey))),
                'content' => cms_content_normalize_payload($pageKey, cms_content_decode_json((string)($row['content_json'] ?? ''))),
                'status' => strtolower(trim((string)($row['status'] ?? 'draft'))),
                'archived_from_status' => strtolower(trim((string)($row['archived_from_status'] ?? ''))),
                'created_by_user_id' => trim((string)($row['created_by_user_id'] ?? '')),
                'created_by_label' => trim((string)($row['created_by_label'] ?? '')),
                'created_by_role' => trim((string)($row['created_by_role'] ?? '')),
                'reviewed_by_user_id' => trim((string)($row['reviewed_by_user_id'] ?? '')),
                'reviewed_by_label' => trim((string)($row['reviewed_by_label'] ?? '')),
                'archived_by_user_id' => trim((string)($row['archived_by_user_id'] ?? '')),
                'archived_by_label' => trim((string)($row['archived_by_label'] ?? '')),
                'review_note' => trim((string)($row['review_note'] ?? '')),
                'submitted_at' => trim((string)($row['submitted_at'] ?? '')),
                'reviewed_at' => trim((string)($row['reviewed_at'] ?? '')),
                'archived_at' => trim((string)($row['archived_at'] ?? '')),
                'created_at' => trim((string)($row['created_at'] ?? '')),
                'updated_at' => trim((string)($row['updated_at'] ?? '')),
            ];
        }
        $result->free();

        return $rows;
    }
}

if (!function_exists('cms_content_request')) {
    function cms_content_request(mysqli $conn, string $requestId): ?array
    {
        $requestId = trim($requestId);
        if ($requestId === '') {
            return null;
        }

        foreach (cms_content_requests($conn) as $row) {
            if ($row['request_id'] === $requestId) {
                return $row;
            }
        }
        return null;
    }
}

if (!function_exists('cms_content_request_sort_timestamp')) {
    function cms_content_request_sort_timestamp(array $request): int
    {
        foreach (['archived_at', 'reviewed_at', 'updated_at', 'submitted_at', 'created_at'] as $field) {
            $value = trim((string)($request[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        return 0;
    }
}

if (!function_exists('cms_content_payload_signature')) {
    function cms_content_payload_signature(string $pageKey, array $payload): string
    {
        $pageKey = cms_content_normalize_page_key($pageKey);
        if ($pageKey === '') {
            return '';
        }

        return hash('sha256', cms_content_encode_json(cms_content_normalize_payload($pageKey, $payload)));
    }
}

if (!function_exists('cms_content_payloads_match')) {
    function cms_content_payloads_match(string $pageKey, array $left, array $right): bool
    {
        $leftSignature = cms_content_payload_signature($pageKey, $left);
        if ($leftSignature === '') {
            return false;
        }

        return hash_equals($leftSignature, cms_content_payload_signature($pageKey, $right));
    }
}

if (!function_exists('cms_content_append_audit_note')) {
    function cms_content_append_audit_note(?string $existingNote, string $entry): string
    {
        $existing = trim((string)$existingNote);
        $entry = trim($entry);
        if ($entry === '') {
            return $existing;
        }

        return $existing === '' ? $entry : ($existing . "\n" . $entry);
    }
}

if (!function_exists('cms_content_approved_requests_for_page')) {
    function cms_content_approved_requests_for_page(mysqli $conn, string $pageKey): array
    {
        $pageKey = cms_content_normalize_page_key($pageKey);
        if ($pageKey === '') {
            return [];
        }

        $rows = array_values(array_filter(cms_content_requests($conn), static function (array $request) use ($pageKey): bool {
            return (string)($request['page_key'] ?? '') === $pageKey
                && strtolower(trim((string)($request['status'] ?? 'draft'))) === 'approved';
        }));

        usort($rows, static function (array $left, array $right): int {
            return cms_content_request_sort_timestamp($right) <=> cms_content_request_sort_timestamp($left);
        });

        return $rows;
    }
}

if (!function_exists('cms_content_live_request')) {
    function cms_content_live_request(mysqli $conn, string $pageKey): ?array
    {
        $pageKey = cms_content_normalize_page_key($pageKey);
        if ($pageKey === '') {
            return null;
        }

        $livePayload = cms_content_page($conn, $pageKey);
        foreach (cms_content_approved_requests_for_page($conn, $pageKey) as $request) {
            if (cms_content_payloads_match($pageKey, $livePayload, (array)($request['content'] ?? []))) {
                return $request;
            }
        }

        return null;
    }
}

if (!function_exists('cms_content_previous_approved_request')) {
    function cms_content_previous_approved_request(mysqli $conn, string $pageKey, string $requestId): ?array
    {
        $requestId = trim($requestId);
        if ($requestId === '') {
            return null;
        }

        $rows = cms_content_approved_requests_for_page($conn, $pageKey);
        foreach ($rows as $index => $request) {
            if ((string)($request['request_id'] ?? '') !== $requestId) {
                continue;
            }

            return $rows[$index + 1] ?? null;
        }

        return null;
    }
}

if (!function_exists('cms_content_save_image_data_url')) {
    function cms_content_save_image_data_url(string $pageKey, string $fieldKey, string $dataUrl): string
    {
        if (!preg_match('#^data:image/(png|jpeg|jpg|webp);base64,(.+)$#i', $dataUrl, $matches)) {
            return '';
        }

        $ext = strtolower((string)$matches[1]);
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $binary = base64_decode((string)$matches[2], true);
        if ($binary === false || $binary === '') {
            return '';
        }

        $safePage = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($pageKey)) ?: 'page';
        $safeField = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($fieldKey)) ?: 'field';
        $relativeDir = 'Images/ContentManagement/' . $safePage;
        $absoluteDir = dirname(__DIR__, 2) . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            return '';
        }

        $filename = $safeField . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $absolutePath = $absoluteDir . '/' . $filename;
        if (file_put_contents($absolutePath, $binary) === false) {
            return '';
        }

        return $relativeDir . '/' . $filename;
    }
}

if (!function_exists('cms_content_resolve_payload_images')) {
    function cms_content_resolve_payload_images(string $pageKey, array $payload): array
    {
        switch ($pageKey) {
            case 'announcements':
            case 'home':
            case 'services':
            case 'faq':
            case 'contact':
                foreach (['banner_image', 'about_image', 'login_image', 'register_image'] as $field) {
                    if (!isset($payload[$field]) || !is_string($payload[$field])) {
                        continue;
                    }
                    $value = trim($payload[$field]);
                    if (str_starts_with($value, 'data:image/')) {
                        $saved = cms_content_save_image_data_url($pageKey, $field, $value);
                        if ($saved !== '') {
                            $payload[$field] = $saved;
                        }
                    }
                }
                break;
        }

        if ($pageKey === 'government') {
            foreach (['banner_image', 'punong_barangay_image'] as $field) {
                $value = trim((string)($payload[$field] ?? ''));
                if (str_starts_with($value, 'data:image/')) {
                    $saved = cms_content_save_image_data_url($pageKey, $field, $value);
                    if ($saved !== '') {
                        $payload[$field] = $saved;
                    }
                }
            }
            foreach ((array)($payload['officials'] ?? []) as $idx => $official) {
                if (!is_array($official)) {
                    continue;
                }
                $image = trim((string)($official['image'] ?? ''));
                if (!str_starts_with($image, 'data:image/')) {
                    continue;
                }
                $saved = cms_content_save_image_data_url($pageKey, 'official_' . ($idx + 1), $image);
                if ($saved !== '') {
                    $payload['officials'][$idx]['image'] = $saved;
                }
            }
        }

        if ($pageKey === 'login') {
            foreach (['login_image', 'register_image'] as $field) {
                $value = trim((string)($payload[$field] ?? ''));
                if (str_starts_with($value, 'data:image/')) {
                    $saved = cms_content_save_image_data_url($pageKey, $field, $value);
                    if ($saved !== '') {
                        $payload[$field] = $saved;
                    }
                }
            }
        }

        return $payload;
    }
}

if (!function_exists('cms_content_archive_request')) {
    function cms_content_archive_request(mysqli $conn, string $requestId, string $archivedByUserId = '', string $archivedByLabel = ''): bool
    {
        cms_content_ensure_schema($conn);
        $request = cms_content_request($conn, $requestId);
        if (!$request) {
            return false;
        }

        $currentStatus = strtolower(trim((string)($request['status'] ?? 'draft')));
        if ($currentStatus === 'archived' || !in_array($currentStatus, ['draft', 'pending', 'approved', 'denied'], true)) {
            return false;
        }

        $table = cms_content_requests_table();
        $auditNote = cms_content_append_audit_note(
            (string)($request['review_note'] ?? ''),
            'Archived by ' . ($archivedByLabel !== '' ? $archivedByLabel : 'System') . ' on ' . date('F d, Y g:i A') . '.'
        );
        $stmt = $conn->prepare("
            UPDATE {$table}
            SET status = 'archived',
                archived_from_status = ?,
                archived_by_user_id = NULLIF(?, ''),
                archived_by_label = NULLIF(?, ''),
                archived_at = NOW(),
                review_note = ?
            WHERE request_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sssss', $currentStatus, $archivedByUserId, $archivedByLabel, $auditNote, $requestId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('cms_content_restore_request')) {
    function cms_content_restore_request(mysqli $conn, string $requestId, string $restoredByLabel = ''): bool
    {
        cms_content_ensure_schema($conn);
        $request = cms_content_request($conn, $requestId);
        if (!$request) {
            return false;
        }

        if (strtolower(trim((string)($request['status'] ?? 'draft'))) !== 'archived') {
            return false;
        }

        $restoreStatus = strtolower(trim((string)($request['archived_from_status'] ?? 'draft')));
        if ($restoreStatus === '' || $restoreStatus === 'archived' || !in_array($restoreStatus, cms_content_request_statuses(), true)) {
            $restoreStatus = 'draft';
        }

        $table = cms_content_requests_table();
        $auditNote = cms_content_append_audit_note(
            (string)($request['review_note'] ?? ''),
            'Restored from archive by ' . ($restoredByLabel !== '' ? $restoredByLabel : 'System') . ' on ' . date('F d, Y g:i A') . '.'
        );
        $stmt = $conn->prepare("
            UPDATE {$table}
            SET status = ?,
                archived_from_status = NULL,
                archived_by_user_id = NULL,
                archived_by_label = NULL,
                archived_at = NULL,
                review_note = ?
            WHERE request_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sss', $restoreStatus, $auditNote, $requestId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('cms_content_upsert_request')) {
    function cms_content_upsert_request(
        mysqli $conn,
        string $pageKey,
        array $payload,
        string $status,
        string $currentUserId,
        string $currentUserLabel,
        string $currentRole,
        string $requestId = '',
        string $reviewNote = '',
        string $reviewedByUserId = '',
        string $reviewedByLabel = ''
    ): string {
        cms_content_ensure_schema($conn);

        $pageKey = cms_content_normalize_page_key($pageKey);
        $status = strtolower(trim($status));
        if ($pageKey === '' || !in_array($status, cms_content_request_statuses(), true)) {
            return '';
        }

        $payload = cms_content_normalize_payload($pageKey, cms_content_resolve_payload_images($pageKey, $payload));
        $pageLabel = cms_content_page_label($pageKey);
        $table = cms_content_requests_table();
        $requestId = trim($requestId);
        $existing = $requestId !== '' ? cms_content_request($conn, $requestId) : null;

        $submittedAt = in_array($status, ['pending', 'approved'], true) ? date('Y-m-d H:i:s') : null;
        $reviewedAt = in_array($status, ['approved', 'denied'], true) ? date('Y-m-d H:i:s') : null;
        $json = cms_content_encode_json($payload);

        if ($existing) {
            $stmt = $conn->prepare("
                UPDATE {$table}
                SET page_key = ?, page_label = ?, content_json = ?, status = ?, created_by_role = ?,
                    review_note = ?, reviewed_by_user_id = NULLIF(?, ''), reviewed_by_label = NULLIF(?, ''),
                    submitted_at = ?, reviewed_at = ?, archived_from_status = NULL,
                    archived_by_user_id = NULL, archived_by_label = NULL, archived_at = NULL
                WHERE request_id = ?
                LIMIT 1
            ");
            if (!$stmt) {
                return '';
            }
            $stmt->bind_param(
                'sssssssssss',
                $pageKey,
                $pageLabel,
                $json,
                $status,
                $currentRole,
                $reviewNote,
                $reviewedByUserId,
                $reviewedByLabel,
                $submittedAt,
                $reviewedAt,
                $requestId
            );
            $stmt->execute();
            $stmt->close();
            return $requestId;
        }

        $requestId = cms_content_request_id();
        $stmt = $conn->prepare("
            INSERT INTO {$table}
                (request_id, page_key, page_label, content_json, status, created_by_user_id, created_by_label, created_by_role, reviewed_by_user_id, reviewed_by_label, review_note, submitted_at, reviewed_at)
            VALUES
                (?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?)
        ");
        if (!$stmt) {
            return '';
        }
        $stmt->bind_param(
            'sssssssssssss',
            $requestId,
            $pageKey,
            $pageLabel,
            $json,
            $status,
            $currentUserId,
            $currentUserLabel,
            $currentRole,
            $reviewedByUserId,
            $reviewedByLabel,
            $reviewNote,
            $submittedAt,
            $reviewedAt
        );
        $stmt->execute();
        $stmt->close();
        return $requestId;
    }
}

if (!function_exists('cms_content_apply_live_page')) {
    function cms_content_apply_live_page(mysqli $conn, string $pageKey, array $payload, string $updatedByUserId, string $updatedByLabel): bool
    {
        cms_content_ensure_schema($conn);
        $pageKey = cms_content_normalize_page_key($pageKey);
        if ($pageKey === '') {
            return false;
        }
        $payload = cms_content_normalize_payload($pageKey, $payload);
        $table = cms_content_pages_table();
        $json = cms_content_encode_json($payload);
        $pageLabel = cms_content_page_label($pageKey);
        $stmt = $conn->prepare("
            INSERT INTO {$table} (page_key, page_label, content_json, updated_by_user_id, updated_by_label)
            VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))
            ON DUPLICATE KEY UPDATE
                page_label = VALUES(page_label),
                content_json = VALUES(content_json),
                updated_by_user_id = VALUES(updated_by_user_id),
                updated_by_label = VALUES(updated_by_label),
                updated_at = CURRENT_TIMESTAMP
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sssss', $pageKey, $pageLabel, $json, $updatedByUserId, $updatedByLabel);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
