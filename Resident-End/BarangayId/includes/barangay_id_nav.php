<?php
$barangayIdNavActive = trim((string)($barangayIdNavActive ?? 'overview'));
$barangayIdNavRequestId = trim((string)($barangayIdNavRequestId ?? ''));

$barangayIdNavItems = [
    [
        'key' => 'overview',
        'label' => 'Overview',
        'hint' => 'Requirements and sample ID',
        'icon' => 'fa-regular fa-id-card',
        'href' => appUrl('Resident-End/BarangayId/BarangayIdLandingPage.php'),
    ],
    [
        'key' => 'apply',
        'label' => 'Application Form',
        'hint' => 'Submit your request',
        'icon' => 'fa-solid fa-file-signature',
        'href' => appUrl('Resident-End/BarangayId/BarangayIdForm.php'),
    ],
    [
        'key' => 'requests',
        'label' => 'My Requests',
        'hint' => 'Track approval and release',
        'icon' => 'fa-solid fa-clock-rotate-left',
        'href' => appUrl('Resident-End/document_requests.php'),
    ],
];

if ($barangayIdNavActive === 'digital' || $barangayIdNavRequestId !== '') {
    $barangayIdNavItems[] = [
        'key' => 'digital',
        'label' => 'Digital ID',
        'hint' => 'Approved issued view',
        'icon' => 'fa-solid fa-mobile-screen-button',
        'href' => $barangayIdNavRequestId !== ''
            ? appUrl('Resident-End/BarangayId/DigitalId.php?request_id=' . rawurlencode($barangayIdNavRequestId))
            : appUrl('Resident-End/document_requests.php'),
    ];
}
?>
<section class="barangay-id-nav-shell" aria-label="Barangay ID section navigation">
    <p class="barangay-id-nav-title">Barangay ID Navigation</p>
    <nav class="barangay-id-nav">
        <?php foreach ($barangayIdNavItems as $navItem): ?>
            <?php $isActive = $navItem['key'] === $barangayIdNavActive; ?>
            <a
                href="<?= htmlspecialchars($navItem['href'], ENT_QUOTES, 'UTF-8') ?>"
                class="barangay-id-nav__link<?= $isActive ? ' is-active' : '' ?>"
                <?= $isActive ? 'aria-current="page"' : '' ?>
            >
                <span class="barangay-id-nav__icon"><i class="<?= htmlspecialchars($navItem['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                <span class="barangay-id-nav__meta">
                    <span class="barangay-id-nav__label"><?= htmlspecialchars($navItem['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="barangay-id-nav__hint"><?= htmlspecialchars($navItem['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </nav>
</section>
