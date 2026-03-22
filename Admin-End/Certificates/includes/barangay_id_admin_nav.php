<?php
$barangayIdAdminNavActive = trim((string)($barangayIdAdminNavActive ?? 'applications'));

$barangayIdAdminNavItems = [
    [
        'key' => 'applications',
        'label' => 'Applications',
        'hint' => 'Open the full request tracker',
        'icon' => 'fa-regular fa-id-card',
        'href' => appUrl('Admin-End/Certificates/CertificateTracker.php?entry=id_issuance'),
    ],
    [
        'key' => 'payments',
        'label' => 'Payments',
        'hint' => 'Verify Barangay ID payment transactions',
        'icon' => 'fa-solid fa-wallet',
        'href' => appUrl('Admin-End/Certificates/FinancePayments.php?section=tracker&filter_document=' . rawurlencode('Barangay ID')),
    ],
    [
        'key' => 'release',
        'label' => 'Release Queue',
        'hint' => 'Handle ready-for-claim Barangay IDs',
        'icon' => 'fa-solid fa-box-open',
        'href' => appUrl('Admin-End/Certificates/CertificateTracker.php?stage=release&filter_document=' . rawurlencode('Barangay ID')),
    ],
];
?>
<section class="barangay-id-admin-nav-shell" aria-label="Barangay ID admin navigation">
    <p class="barangay-id-admin-nav-title">Barangay ID Workflow</p>
    <nav class="barangay-id-admin-nav">
        <?php foreach ($barangayIdAdminNavItems as $navItem): ?>
            <?php $isActive = $navItem['key'] === $barangayIdAdminNavActive; ?>
            <a
                href="<?= htmlspecialchars($navItem['href'], ENT_QUOTES, 'UTF-8') ?>"
                class="barangay-id-admin-nav__link<?= $isActive ? ' is-active' : '' ?>"
                <?= $isActive ? 'aria-current="page"' : '' ?>
            >
                <span class="barangay-id-admin-nav__icon"><i class="<?= htmlspecialchars($navItem['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                <span class="barangay-id-admin-nav__meta">
                    <span class="barangay-id-admin-nav__label"><?= htmlspecialchars($navItem['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="barangay-id-admin-nav__hint"><?= htmlspecialchars($navItem['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </nav>
</section>
